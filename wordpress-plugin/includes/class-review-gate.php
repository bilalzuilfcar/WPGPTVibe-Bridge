<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Human-in-the-loop publishing controls.
 *
 * API operations may prepare drafts, but only WordPress administrators
 * can authorize publication. API credentials cannot approve their own work.
 */
final class WPGPTVibe_Review_Gate {
    private const THEME_APPROVAL_OPTION = 'wpgptvibe_theme_publish_approval';

    public static function assert_draft_status(array $data): void {
        if (isset($data['status']) && !in_array(sanitize_key((string) $data['status']), ['draft', 'pending', 'auto-draft'], true)) {
            throw new RuntimeException('Manual review required. API content must remain draft or pending; publish in WordPress admin after review.');
        }
    }

    public static function assert_unpublished(int $id): void {
        $post = get_post($id);
        if (!$post instanceof WP_Post) {
            throw new RuntimeException('Content not found.');
        }
        if (!in_array($post->post_status, ['draft', 'pending', 'auto-draft'], true)) {
            throw new RuntimeException('Manual review required. Published content cannot be changed through the Bridge API. Prepare a separate draft for review.');
        }
    }

    public static function assert_editable(int $id): void {
        $post=get_post($id);
        if($post instanceof WP_Post && $post->post_type==='wp_block'){
            $impact=WPGPTVibe_Shared_Patterns::impact($id);
            foreach($impact['items'] as $item){
                if(($item['type']??'')==='widget'||(($item['post_type']??'')!=='wp_block'&&!in_array($item['status']??'', ['draft','pending','auto-draft'],true))){
                    throw new RuntimeException('Manual review required. This shared section affects live content. Edit it in WordPress after reviewing its usage.');
                }
            }
            return;
        }
        self::assert_unpublished($id);
    }

    public static function theme_fingerprint(?string $release_root=null): string {
        $draft = WPGPTVibe_Theme_Manager::draft_status();
        if (empty($draft['exists']) || empty($draft['stylesheet'])) {
            throw new RuntimeException('No draft theme available for review.');
        }
        $theme = wp_get_theme(sanitize_key((string) $draft['stylesheet']));
        if (!$theme->exists()) {
            throw new RuntimeException('Draft theme not found.');
        }
        $root = realpath($release_root??$theme->get_stylesheet_directory());
        if (!$root) {
            throw new RuntimeException('Draft theme path unavailable.');
        }
        $entries = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                continue;
            }
            $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root)));
            $entries[$path] = hash_file('sha256', $file->getPathname());
        }
        ksort($entries, SORT_STRING);
        return hash('sha256', wp_json_encode($entries));
    }

    public static function theme_lock(callable $operation) {
        global $wpdb;$key='wpgv_theme_review_'.substr(hash('sha256',(string)get_current_blog_id()),0,44);
        if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 3)',$key))!==1){throw new RuntimeException('Theme review is busy. Try again.');}
        try{return $operation();}finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$key));}
    }

    public static function approve_theme(): void {
        if(!current_user_can('manage_options')||!current_user_can('switch_themes')){throw new RuntimeException('A WordPress administrator with theme switching permission must approve this draft.');}
        self::theme_lock(static function(){
            update_option(self::THEME_APPROVAL_OPTION,['fingerprint'=>self::theme_fingerprint(),'approved_by'=>get_current_user_id(),'expires'=>time()+15*MINUTE_IN_SECONDS],false);
            WPGPTVibe_Audit_Log::record('theme_review_approved','draft_theme','success');
        });
    }

    private static function valid_approval(array $approval,?string $release_root=null): bool {
        $user=get_userdata((int)($approval['approved_by']??0));
        return !empty($approval['expires'])&&(int)$approval['expires']>=time()&&$user&&user_can($user,'manage_options')&&user_can($user,'switch_themes')
            &&!empty($approval['fingerprint'])&&hash_equals((string)$approval['fingerprint'],self::theme_fingerprint())
            &&($release_root===null||hash_equals((string)$approval['fingerprint'],self::theme_fingerprint($release_root)));
    }

    public static function consume_theme_approval(?string $release_root=null): void {
        self::theme_lock(static function()use($release_root){
            $approval=get_option(self::THEME_APPROVAL_OPTION,[]);
            try{$valid=is_array($approval)&&self::valid_approval($approval,$release_root);}catch(Throwable $error){$valid=false;}
            // Consume under the shared publication lock so concurrent calls cannot reuse approval.
            delete_option(self::THEME_APPROVAL_OPTION);
            if(!$valid){throw new RuntimeException('A WordPress administrator must review and approve this exact draft theme in Tools > WPGPTVibe. Approval lasts 15 minutes and expires if draft files change.');}
        });
    }

    public static function theme_approval_status(): array {
        $approval=get_option(self::THEME_APPROVAL_OPTION,[]);
        try{$valid=is_array($approval)&&self::valid_approval($approval);}catch(Throwable $error){$valid=false;}
        return ['approved'=>$valid,'expires'=>is_array($approval)?(int)($approval['expires']??0):0];
    }
}

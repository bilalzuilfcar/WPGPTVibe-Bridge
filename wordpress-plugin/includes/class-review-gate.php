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
            throw new RuntimeException('Manual review required. Existing synced sections must be edited in WordPress because theme-file and concurrent publication references cannot be proven absent.');
        }
        self::assert_unpublished($id);
    }

    public static function assert_css_write(string $path, ?string $content = null): void {
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'css') {
            throw new RuntimeException('Only CSS theme changes are supported until non-executing staging is available.');
        }
        if ($content !== null && (str_contains($content, '<') || preg_match('/javascript\s*:|expression\s*\(/i', $content))) {
            throw new RuntimeException('CSS must not contain executable markup.');
        }
    }

    private static function file_manifest(string $root): array {
        $root = realpath($root);
        if (!$root || !is_dir($root)) { throw new RuntimeException('Theme staging source unavailable.'); }
        $entries = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isLink()) { throw new RuntimeException('Theme staging cannot contain symbolic links.'); }
            if (!$file->isFile()) { continue; }
            $hash = hash_file('sha256', $file->getPathname());
            if ($hash === false) { throw new RuntimeException('Theme staging file cannot be read.'); }
            $entries[ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/')] = $hash;
        }
        return $entries;
    }

    public static function assert_safe_theme_preview(string $draft_root, string $source_root): void {
        $draft = self::file_manifest($draft_root); $source = self::file_manifest($source_root);
        foreach (array_unique(array_merge(array_keys($draft), array_keys($source))) as $path) {
            if (($draft[$path] ?? null) === ($source[$path] ?? null)) { continue; }
            if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'css') {
                throw new RuntimeException('Non-executing staging is required for executable theme changes.');
            }
            if (isset($draft[$path])) { self::assert_css_write($path, file_get_contents($draft_root . '/' . $path)); }
        }
        $draft_header = get_file_data($draft_root . '/style.css', ['template' => 'Template'], 'theme');
        $source_header = get_file_data($source_root . '/style.css', ['template' => 'Template'], 'theme');
        if (($draft_header['template'] ?? '') !== ($source_header['template'] ?? '')) {
            throw new RuntimeException('Non-executing staging is required to change the theme parent.');
        }
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
        global $wpdb;$key='wpgv_theme_review_'.substr(hash('sha256',(string)get_current_blog_id().home_url('/')),0,44);
        if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 3)',$key))!==1){throw new RuntimeException('Theme review is busy. Try again.');}
        try{return $operation();}finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$key));}
    }

    public static function approve_theme(): void {
        if(!current_user_can('manage_options')||!current_user_can('switch_themes')){throw new RuntimeException('A WordPress administrator with theme switching permission must approve this draft.');}
        self::theme_lock(static function(){
            $draft = WPGPTVibe_Theme_Manager::draft_status();
            if (empty($draft['source_stylesheet'])) { throw new RuntimeException('Theme staging source unavailable.'); }
            self::assert_safe_theme_preview(wp_get_theme($draft['stylesheet'])->get_stylesheet_directory(), wp_get_theme($draft['source_stylesheet'])->get_stylesheet_directory());
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

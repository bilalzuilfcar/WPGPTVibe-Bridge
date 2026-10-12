<?php
if (!defined('ABSPATH')) { exit; }

/** Native block editing. Preserves parsed markup and rejects stale writes. */
final class WPGPTVibe_Block_Manager {
    private const MAX_CONTENT = 1048576;

    private static function post(int $id): WP_Post {
        $post = get_post($id);
        if (!$post instanceof WP_Post || wp_is_post_revision($id)) { throw new RuntimeException('Page not found.'); }
        if (!current_user_can('edit_post', $id)) { throw new RuntimeException('You do not have permission to edit this page.'); }
        if (get_post_meta($id, '_elementor_edit_mode', true) === 'builder' || get_post_meta($id, '_et_pb_use_builder', true) === 'on' || str_contains($post->post_content, '[vc_row')) {
            throw new RuntimeException('This page uses an unsupported builder. Its content has not been changed.');
        }
        if (strlen($post->post_content) > self::MAX_CONTENT) { throw new RuntimeException('Page exceeds the 1 MB editing limit.'); }
        return $post;
    }

    public static function get(int $id): array {
        $post = self::post($id);
        return ['id'=>$id, 'title'=>$post->post_title, 'status'=>$post->post_status,
            'hash'=>hash('sha256', $post->post_content), 'blocks'=>parse_blocks($post->post_content),
            'editor_url'=>get_edit_post_link($id, 'raw'), 'preview_url'=>get_preview_post_link($id),
            'revisions_enabled'=>wp_revisions_enabled($post),
            'content_only'=>(bool)get_post_meta($id, '_wpgptvibe_content_only', true)];
    }

    public static function preview(int $id, string $expected_hash, array $operations): array {
        $post = self::post($id);
        if (!preg_match('/^[a-f0-9]{64}$/', $expected_hash) || !hash_equals(hash('sha256', $post->post_content), $expected_hash)) {
            throw new RuntimeException('This page has newer edits. Read the page again before changing it.');
        }
        if (!$operations || count($operations) > 50) { throw new RuntimeException('Provide between 1 and 50 block changes.'); }
        $blocks = parse_blocks($post->post_content);
        $summary = [];
        foreach ($operations as $operation) {
            if (!is_array($operation)) { throw new RuntimeException('Invalid block operation.'); }
            $type = $operation['type'] ?? '';
            $path = $operation['path'] ?? [];
            if (!is_array($path) || !$path || count($path) > 20 || array_filter($path, static fn($n)=>!is_int($n)||$n<0)) { throw new RuntimeException('Invalid block path.'); }
            if (get_post_meta($id, '_wpgptvibe_content_only', true) && $type !== 'update') { throw new RuntimeException('Unlock layout editing in WordPress before moving or inserting sections.'); }
            $index = array_pop($path);
            $siblings =& self::children($blocks, $path);
            if ($type === 'insert') {
                if ($index > count($siblings)) { throw new RuntimeException('Invalid insertion position.'); }
                $new = self::new_block($operation['block'] ?? []);
                array_splice($siblings, $index, 0, [$new]);
            } else {
                if (!isset($siblings[$index])) { throw new RuntimeException('Block not found at the requested path.'); }
                if ($type === 'duplicate') { array_splice($siblings, $index+1, 0, [$siblings[$index]]); }
                elseif ($type === 'move') {
                    $to = $operation['to'] ?? null;
                    if (!is_int($to) || $to < 0 || $to >= count($siblings)) { throw new RuntimeException('Invalid move position.'); }
                    $moved = array_splice($siblings, $index, 1); array_splice($siblings, $to, 0, $moved);
                } elseif ($type === 'remove') {
                    if (($operation['confirm'] ?? '') !== 'REMOVE') { throw new RuntimeException('Please confirm removal with REMOVE.'); }
                    array_splice($siblings, $index, 1);
                } elseif ($type === 'update') {
                    $name=$siblings[$index]['blockName'] ?? '';
                    // Editable dynamic blocks save attributes; native/static blocks are never rewritten blindly.
                    if (!str_starts_with($name, 'wpgptvibe/')) { throw new RuntimeException('Use the WordPress editor to change this native or third-party block. Reordering remains available.'); }
                    $attrs=$operation['attributes'] ?? [];
                    if (!is_array($attrs)) { throw new RuntimeException('Attributes must be an object.'); }
                    if (get_post_meta($id, '_wpgptvibe_content_only', true) && array_diff(array_keys($attrs), ['heading','body','label','url','imageId','imageUrl','alt'])) {
                        throw new RuntimeException('Content-only editing allows text, links and images.');
                    }
                    $siblings[$index]['attrs']=WPGPTVibe_Patterns::attributes($name, array_merge($siblings[$index]['attrs'], $attrs));
                } else { throw new RuntimeException('Unknown block operation.'); }
            }
            $summary[]=['type'=>$type,'path'=>$operation['path']];
            unset($siblings);
        }
        self::normalize($blocks);
        $content = serialize_blocks($blocks);
        if (strlen($content)>self::MAX_CONTENT) { throw new RuntimeException('Result exceeds the 1 MB editing limit.'); }
        return ['id'=>$id,'before_hash'=>$expected_hash,'hash'=>hash('sha256',$content),'blocks'=>$blocks,'content'=>$content,'changes'=>$summary];
    }

    private static function &children(array &$blocks, array $path): array {
        $current =& $blocks;
        foreach ($path as $index) {
            if (!isset($current[$index]['innerBlocks']) || !$current[$index]['innerBlocks']) { throw new RuntimeException('Parent block not found or does not contain editable sections.'); }
            // Only rewrite child markers when the parent has a simple wrapper.
            $fragments=array_values(array_filter($current[$index]['innerContent'],static fn($x)=>is_string($x)&&trim($x)!==''));
            if (count($fragments)>2) { throw new RuntimeException('This parent has complex markup. Edit its layout in WordPress.'); }
            $before=$fragments[0]??''; $after=$fragments[1]??'';
            // Store wrapper fragments; refresh after all mutations through normalize().
            $current[$index]['_wpgptvibe_wrapper']=[$before,$after];
            $current =& $current[$index]['innerBlocks'];
        }
        return $current;
    }

    private static function normalize(array &$blocks): void {
        foreach ($blocks as &$block) {
            self::normalize($block['innerBlocks']);
            if (isset($block['_wpgptvibe_wrapper'])) {
                [$before,$after]=$block['_wpgptvibe_wrapper'];
                $block['innerContent']=array_merge([$before],array_fill(0,count($block['innerBlocks']),null),[$after]);
                unset($block['_wpgptvibe_wrapper']);
            }
        }
    }

    public static function new_block(array $descriptor,int $depth=0): array {
        if($depth>20){throw new RuntimeException('Block nesting exceeds 20 levels.');}
        if(!is_array($descriptor['children']??[])){throw new RuntimeException('Block children must be an array.');}
        $name=(string)($descriptor['name']??'');
        if (!in_array($name,WPGPTVibe_Patterns::block_names(),true)) { throw new RuntimeException('Unsupported new block. Use a WPGPTVibe editable block.'); }
        $children=[];
        foreach (($descriptor['children']??[]) as $child) { $children[]=self::new_block($child,$depth+1); }
        if (count($children)>100) { throw new RuntimeException('Too many child blocks.'); }
        return ['blockName'=>$name,'attrs'=>WPGPTVibe_Patterns::attributes($name,$descriptor['attributes']??[]),'innerBlocks'=>$children,'innerHTML'=>'','innerContent'=>array_fill(0,count($children),null)];
    }

    public static function change(int $id,string $expected_hash,array $operations,bool $confirm_live): array {
        return self::locked($id, static function() use($id,$expected_hash,$operations,$confirm_live) {
            clean_post_cache($id); $post=self::post($id);
            if (in_array($post->post_status,['publish','future','private'],true) && !$confirm_live) { throw new RuntimeException('This is published or scheduled content. Confirm the live change first.'); }
            if (!wp_revisions_enabled($post)) { throw new RuntimeException('Enable WordPress revisions before making recoverable page changes.'); }
            $preview=self::preview($id,$expected_hash,$operations);
            self::normalize($preview['blocks']); $preview['content']=serialize_blocks($preview['blocks']);
            wp_save_post_revision($id);
            $saved=wp_update_post(wp_slash(['ID'=>$id,'post_content'=>$preview['content']]),true);
            if(is_wp_error($saved)){throw new RuntimeException($saved->get_error_message());}
            WPGPTVibe_Audit_Log::record('blocks_update','post:'.$id,'success',['before_hash'=>$expected_hash,'after_hash'=>hash('sha256',$preview['content'])]);
            return self::get($id);
        });
    }

    public static function locked(int $id,callable $operation) {
        global $wpdb;
        $key='wpgv_post_'.substr(hash('sha256',get_current_blog_id().':'.$id),0,54);
        if ((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 3)',$key))!==1) {throw new RuntimeException('This page is busy. Retry after reading its current state.');}
        $wpdb->query('START TRANSACTION');
        try{
            // Lock the actual post row, so WordPress saves cannot race the read/write window.
            $wpdb->get_var($wpdb->prepare('SELECT ID FROM '.$wpdb->posts.' WHERE ID = %d FOR UPDATE',$id));
            clean_post_cache($id);
            $result=$operation();$wpdb->query('COMMIT');return $result;
        }catch(Throwable $e){$wpdb->query('ROLLBACK');clean_post_cache($id);throw $e;}
        finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$key));}
    }

    public static function revisions(int $id): array {
        self::post($id);$out=[];
        foreach(wp_get_post_revisions($id,['numberposts'=>20]) as $revision){$out[]=['id'=>$revision->ID,'date'=>$revision->post_modified_gmt,'author'=>(int)$revision->post_author];}
        return $out;
    }

    public static function restore(int $id,int $revision_id,string $expected_hash,bool $confirm_live): array {
        return self::locked($id,static function()use($id,$revision_id,$expected_hash,$confirm_live){
            clean_post_cache($id);$post=self::post($id);
            if(!hash_equals(hash('sha256',$post->post_content),$expected_hash)){throw new RuntimeException('This page has newer edits.');}
            if(!wp_revisions_enabled($post)){throw new RuntimeException('WordPress revisions are disabled.');}
            if($post->post_status!=='draft'&&!$confirm_live){throw new RuntimeException('Confirm restoration of published content.');}
            $revision=get_post($revision_id);
            if(!$revision||wp_is_post_revision($revision_id)!==$id){throw new RuntimeException('Revision does not belong to this page.');}
            wp_save_post_revision($id);
            if(!wp_restore_post_revision($revision_id,['post_content','post_title','post_excerpt'])){throw new RuntimeException('Revision restoration failed.');}
            WPGPTVibe_Audit_Log::record('revision_restore','post:'.$id,'success',['meta'=>['revision_id'=>$revision_id]]);
            return self::get($id);
        });
    }
}

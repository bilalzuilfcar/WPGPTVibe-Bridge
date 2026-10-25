<?php
if(!defined('ABSPATH')){exit;}

/** Review handoffs use ordinary WordPress draft pages, never overwrite public originals. */
final class WPGPTVibe_Draft_Manager {
    public static function copy(int $source_id,string $expected_hash,?string $title=null): array {
        if(!current_user_can('edit_pages')){throw new RuntimeException('No permission to create review drafts.');}
        return WPGPTVibe_Block_Manager::locked($source_id,static function()use($source_id,$expected_hash,$title){
            $source=WPGPTVibe_Block_Manager::get($source_id);$post=get_post($source_id);
            if($post->post_type!=='page'){throw new RuntimeException('Review copies currently support WordPress pages only.');}
            if(!hash_equals($source['hash'],$expected_hash)){throw new RuntimeException('This page has newer edits. Read it again before creating a review copy.');}
            $new_title=$title===null?$post->post_title.' (review draft)':sanitize_text_field($title);
            if($new_title===''||strlen($new_title)>250){throw new RuntimeException('Provide a draft title up to 250 bytes.');}
            $id=wp_insert_post(wp_slash(['post_type'=>'page','post_status'=>'draft','post_title'=>$new_title,'post_content'=>$post->post_content,'post_excerpt'=>$post->post_excerpt??'']),true);
            if(is_wp_error($id)){throw new RuntimeException($id->get_error_message());}
            $template=get_post_meta($source_id,'_wp_page_template',true);
            if(is_string($template)&&$template!==''){update_post_meta($id,'_wp_page_template',$template);}
            update_post_meta($id,'_wpgptvibe_review_source',$source_id);
            update_post_meta($id,'_wpgptvibe_source_hash',$source['hash']);
            WPGPTVibe_Audit_Log::record('review_copy_create','post:'.$id,'success',['source_id'=>$source_id]);
            $result=WPGPTVibe_Block_Manager::get($id);
            $result['review']=['source_id'=>$source_id,'source_hash'=>$source['hash'],'original_url'=>get_permalink($source_id),'original_unchanged'=>true,
                'instructions'=>'Edit this separate draft, then review it in WordPress. It does not replace the original URL or copy SEO/builder-specific metadata. Synced sections remain linked and require their own review.'];
            return $result;
        });
    }
    public static function submit(int $id,string $expected_hash): array {
        return WPGPTVibe_Block_Manager::locked($id,static function()use($id,$expected_hash){
            $page=WPGPTVibe_Block_Manager::get($id);$post=get_post($id);
            if($post->post_type!=='page'){throw new RuntimeException('Review submission currently supports pages only.');}
            WPGPTVibe_Review_Gate::assert_unpublished($id);
            if(!hash_equals($page['hash'],$expected_hash)){throw new RuntimeException('This page has newer edits.');}
            $saved=wp_update_post(wp_slash(['ID'=>$id,'post_status'=>'pending']),true);
            if(is_wp_error($saved)){throw new RuntimeException($saved->get_error_message());}
            update_post_meta($id,'_wpgptvibe_review_ready',1);
            WPGPTVibe_Audit_Log::record('page_review_requested','post:'.$id,'success');
            $page=WPGPTVibe_Block_Manager::get($id);$page['review_instructions']='Review and publish manually in the WordPress editor. Existing live pages are not replaced automatically.';return $page;
        });
    }
    public static function list(): array {
        $q=new WP_Query(['post_type'=>'page','post_status'=>['draft','pending'],'posts_per_page'=>100,'orderby'=>'modified','order'=>'DESC',
            'meta_query'=>['relation'=>'OR',['key'=>'_wpgptvibe_review_ready','compare'=>'EXISTS'],['key'=>'_wpgptvibe_review_source','compare'=>'EXISTS']]]);
        $out=[];foreach($q->posts as $post){if(!current_user_can('edit_post',$post->ID)){continue;}$out[]=['id'=>$post->ID,'title'=>$post->post_title,'status'=>$post->post_status,'source_id'=>(int)get_post_meta($post->ID,'_wpgptvibe_review_source',true),'editor_url'=>get_edit_post_link($post->ID,'raw'),'preview_url'=>get_preview_post_link($post->ID)];}return $out;
    }
}

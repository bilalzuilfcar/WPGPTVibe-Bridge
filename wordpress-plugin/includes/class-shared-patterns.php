<?php
if (!defined('ABSPATH')) { exit; }

/** Shared WordPress patterns, with a bounded and explicit impact report. */
final class WPGPTVibe_Shared_Patterns {
    public static function references(string $content,int $target,array $visited=[]): bool {
        if(count($visited)>20){throw new RuntimeException('Shared pattern nesting is too deep to assess safely.');}
        $walk=static function(array $blocks)use(&$walk,$target,$visited): bool {
            foreach($blocks as $block){
                if(($block['blockName']??'')==='core/block'){
                    $ref=(int)($block['attrs']['ref']??0);if($ref===$target){return true;}
                    if($ref&&!in_array($ref,$visited,true)){$post=get_post($ref);if($post&&$post->post_type==='wp_block'&&self::references($post->post_content,$target,array_merge($visited,[$ref]))){return true;}}
                }
                if(!empty($block['innerBlocks'])&&$walk($block['innerBlocks'])){return true;}
            }
            return false;
        };
        return $walk(parse_blocks($content));
    }
    public static function impact(int $id): array {
        $pattern=get_post($id);
        if(!$pattern||$pattern->post_type!=='wp_block'||!current_user_can('edit_post',$id)){throw new RuntimeException('Shared pattern is not accessible.');}
        $types=array_values(get_post_types(['public'=>true],'names'));
        $query=new WP_Query(['post_type'=>array_values(array_unique(array_merge($types,['wp_block','wp_template','wp_template_part','wp_navigation']))),'post_status'=>['publish','draft','pending','future','private'],'posts_per_page'=>501,'orderby'=>'ID','order'=>'ASC','no_found_rows'=>true]);
        if(count($query->posts)>500){throw new RuntimeException('This site has more than 500 items. Update the shared pattern in WordPress after reviewing its usage.');}
        $affected=[];
        foreach($query->posts as $post){if($post->ID!==$id&&self::references($post->post_content,$id,[$id])){$affected[]=['type'=>'post','id'=>$post->ID,'title'=>$post->post_title,'status'=>$post->post_status,'post_type'=>$post->post_type,'editor_url'=>get_edit_post_link($post->ID,'raw')];}}
        foreach((array)get_option('widget_block',[]) as $widget_id=>$widget){if(is_array($widget)&&self::references((string)($widget['content']??''),$id,[$id])){$affected[]=['type'=>'widget','id'=>(string)$widget_id,'title'=>'Block widget '.$widget_id];}}
        return ['items'=>$affected,'count'=>count($affected),'hash'=>hash('sha256',wp_json_encode($affected)),'complete'=>true,'scope'=>'database_content_and_block_widgets','includes_theme_files'=>false];
    }
    public static function usage(int $id): array {
        try{return self::impact($id);}catch(RuntimeException $e){return ['items'=>[],'count'=>null,'hash'=>null,'complete'=>false,'error'=>$e->getMessage()];}
    }
    public static function assert_acyclic(int $id,string $content): void {
        if(self::references($content,$id,[$id])){throw new RuntimeException('A shared section cannot contain recursive references to itself.');}
    }
    public static function list(): array {
        $query=new WP_Query(['post_type'=>'wp_block','post_status'=>['publish','draft','private'],'posts_per_page'=>100,'orderby'=>'modified','order'=>'DESC']);$out=[];
        foreach($query->posts as $p){if(current_user_can('edit_post',$p->ID)){$out[]=['id'=>$p->ID,'title'=>$p->post_title,'status'=>$p->post_status,'hash'=>hash('sha256',$p->post_content),'editor_url'=>get_edit_post_link($p->ID,'raw')];}}
        return $out;
    }
    public static function create(string $title,array $descriptors): array {
        if(!current_user_can('edit_posts')){throw new RuntimeException('No permission to create shared patterns.');}
        if(!$descriptors||count($descriptors)>50){throw new RuntimeException('Provide 1–50 shared pattern blocks.');}
        $blocks=[];foreach($descriptors as $descriptor){$blocks[]=WPGPTVibe_Block_Manager::new_block($descriptor);}
        $content=serialize_blocks($blocks);if(strlen($content)>1048576){throw new RuntimeException('Shared pattern exceeds the 1 MB limit.');}
        $id=wp_insert_post(wp_slash(['post_type'=>'wp_block','post_status'=>'publish','post_title'=>sanitize_text_field($title),'post_content'=>$content]),true);
        if(is_wp_error($id)){throw new RuntimeException($id->get_error_message());}
        WPGPTVibe_Audit_Log::record('shared_pattern_create','pattern:'.$id,'success');
        return WPGPTVibe_Block_Manager::get((int)$id);
    }
}

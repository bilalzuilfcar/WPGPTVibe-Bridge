<?php
if (!defined('ABSPATH')) { exit; }

/** Editor-native dynamic blocks: every text/image remains editable in Gutenberg. */
final class WPGPTVibe_Patterns {
    public static function block_names(): array { return ['wpgptvibe/section','wpgptvibe/text','wpgptvibe/image','wpgptvibe/button','wpgptvibe/columns','wpgptvibe/card']; }
    public static function schema(): array { return [
        'heading'=>['type'=>'string','default'=>'','role'=>'content'], 'body'=>['type'=>'string','default'=>'','role'=>'content'],
        'label'=>['type'=>'string','default'=>'','role'=>'content'], 'url'=>['type'=>'string','default'=>'','role'=>'content'],
        'imageId'=>['type'=>'integer','default'=>0,'role'=>'content'], 'imageUrl'=>['type'=>'string','default'=>'','role'=>'content'], 'alt'=>['type'=>'string','default'=>'','role'=>'content'],
        'align'=>['type'=>'string','default'=>''], 'padding'=>['type'=>'integer','default'=>32],
        'columns'=>['type'=>'integer','default'=>3], 'level'=>['type'=>'integer','default'=>2],
        'background'=>['type'=>'string','default'=>''], 'color'=>['type'=>'string','default'=>'']
    ]; }
    public static function attributes(string $name,array $attrs): array {
        if(!in_array($name,self::block_names(),true)){throw new RuntimeException('Unsupported block.');}
        $schema=self::schema();$out=[];
        foreach($attrs as $key=>$value){
            if(!isset($schema[$key])){throw new RuntimeException('Unsupported attribute: '.$key);}
            if($schema[$key]['type']==='integer'){
                if(!is_int($value)){throw new RuntimeException('Attribute '.$key.' must be an integer.');}
                $bounds=['padding'=>[0,160],'columns'=>[1,4],'level'=>[1,6],'imageId'=>[0,PHP_INT_MAX]];
                [$min,$max]=$bounds[$key];if($value<$min||$value>$max){throw new RuntimeException('Attribute '.$key.' is out of range.');}$out[$key]=$value;
            }else{
                if(!is_string($value)||strlen($value)>20000){throw new RuntimeException('Invalid text attribute.');}
                if(in_array($key,['url','imageUrl'],true)){
                    if($value!==''&&!preg_match('#^(https?://|/(?!/)|\#|mailto:|tel:)#i',$value)){throw new RuntimeException('Use a safe website, relative, email or phone URL.');}
                    $out[$key]=esc_url_raw($value);
                }elseif(in_array($key,['background','color'],true)){
                    if($value!==''&&!preg_match('/^#[a-f0-9]{3}([a-f0-9]{3})?$/i',$value)){throw new RuntimeException('Use a hexadecimal colour.');}$out[$key]=$value;
                }elseif($key==='align'){
                    if(!in_array($value,['','left','center','right','wide','full'],true)){throw new RuntimeException('Invalid alignment.');}$out[$key]=$value;
                }else{$out[$key]=in_array($key,['heading','body','label'],true)?wp_kses_post($value):sanitize_text_field($value);}
            }
        }
        return $out;
    }
    public static function init(): void { add_action('init',[self::class,'register']); add_action('enqueue_block_editor_assets',[self::class,'editor']); }
    public static function register(): void {
        wp_register_style('wpgptvibe-blocks',WPGPTVIBE_URL.'assets/blocks.css',[],WPGPTVIBE_VERSION);
        foreach(self::block_names() as $name){register_block_type($name,['api_version'=>3,'attributes'=>self::schema(),'render_callback'=>[self::class,'render'],'style'=>'wpgptvibe-blocks','supports'=>['html'=>false,'align'=>['wide','full']]]);}
        register_block_pattern_category('wpgptvibe',['label'=>'WPGPTVibe editable sections']);
        foreach(self::catalog() as $pattern){register_block_pattern('wpgptvibe/'.$pattern['slug'],['title'=>$pattern['title'],'categories'=>['wpgptvibe'],'content'=>$pattern['content']]);}
        register_post_meta('page','_wpgptvibe_content_only',['type'=>'boolean','single'=>true,'show_in_rest'=>true,'default'=>false,'auth_callback'=>static fn()=>current_user_can('manage_options')]);
    }
    public static function editor(): void {
        wp_enqueue_script('wpgptvibe-editor',WPGPTVIBE_URL.'assets/editor.js',['wp-blocks','wp-block-editor','wp-element','wp-components','wp-data','wp-plugins','wp-edit-post'],WPGPTVIBE_VERSION,true);
        wp_localize_script('wpgptvibe-editor','WPGPTVibeEditor',['canUnlock'=>current_user_can('manage_options'),'schema'=>self::schema()]);
        wp_enqueue_style('wpgptvibe-blocks');
    }
    public static function render(array $attrs,string $content,$block): string {
        $name=$block->name;$a=[];
        foreach($attrs as $key=>$value){try{$a=array_merge($a,self::attributes($name,[$key=>$value]));}catch(RuntimeException $e){/* Invalid editor input must not crash a public page. */}}
        $heading=$a['heading']??'';$body=$a['body']??'';$level=$a['level']??2;
        $style='padding:'.($a['padding']??32).'px;';
        if(!empty($a['background'])){$style.='background:'.$a['background'].';';}if(!empty($a['color'])){$style.='color:'.$a['color'].';';}
        $align=$a['align']??'';
        if(in_array($align,['left','center','right'],true)){$style.='text-align:'.$align.';';}
        $copy=($heading!==''?'<h'.$level.'>'.wp_kses_post($heading).'</h'.$level.'>':'').($body!==''?'<div class="wpgptvibe-copy">'.wp_kses_post($body).'</div>':'');
        if($name==='wpgptvibe/image'){
            $id=$a['imageId']??0;
            return $id?wp_get_attachment_image($id,'large',false,['alt'=>$a['alt']??'']):(!empty($a['imageUrl'])?'<img class="wpgptvibe-image" src="'.esc_url($a['imageUrl']).'" alt="'.esc_attr($a['alt']??'').'" loading="lazy">':'');
        }
        if($name==='wpgptvibe/button'){return '<div class="wp-block-buttons"><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="'.esc_url($a['url']??'').'">'.wp_kses_post($a['label']??'').'</a></div></div>';}
        $class='wpgptvibe-'.substr($name,10);
        if(in_array($align,['wide','full'],true)){$class.=' align'.$align;}
        if($name==='wpgptvibe/columns'){$style.='--wpgptvibe-columns:'.($a['columns']??3).';';}
        return '<div class="'.esc_attr($class).'" style="'.esc_attr($style).'">'.$copy.$content.'</div>';
    }
    public static function catalog(): array {
        $text=static fn($heading,$body)=>['name'=>'wpgptvibe/text','attributes'=>['heading'=>$heading,'body'=>$body]];
        $section=static fn($children)=>['name'=>'wpgptvibe/section','children'=>$children];
        $definitions=[
            'hero'=>['Hero',$section([$text('Your page headline','Explain what you offer and who it helps.'),['name'=>'wpgptvibe/button','attributes'=>['label'=>'Contact us','url'=>'/contact/']]])],
            'services'=>['Services',$section([$text('Our services','Describe the services available.'),['name'=>'wpgptvibe/columns','attributes'=>['columns'=>3],'children'=>array_map(static fn($n)=>['name'=>'wpgptvibe/card','attributes'=>['heading'=>'Service '.$n,'body'=>'Add a clear description.']],range(1,3))]])],
            'cards'=>['Four cards',$section([['name'=>'wpgptvibe/columns','attributes'=>['columns'=>4],'children'=>array_map(static fn($n)=>['name'=>'wpgptvibe/card','attributes'=>['heading'=>'Card '.$n,'body'=>'Add your content.']],range(1,4))]])],
            'faq'=>['Frequently asked questions',$section([$text('Frequently asked questions',''),$text('Your first question','Write a helpful answer.'),$text('Your next question','Write a helpful answer.')])],
            'cta'=>['Call to action',$section([$text('Ready to get started?','Explain the next step.'),['name'=>'wpgptvibe/button','attributes'=>['label'=>'Get in touch','url'=>'/contact/']]])]
        ];$out=[];
        foreach($definitions as $slug=>[$title,$descriptor]){$out[]=['slug'=>$slug,'title'=>$title,'content'=>serialize_blocks([WPGPTVibe_Block_Manager::new_block($descriptor)])];}return $out;
    }
}

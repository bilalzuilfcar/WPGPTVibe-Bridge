<?php
if (!defined('ABSPATH')) { exit; }

/** Explicit adapter for the verified dynamic Spectra Blocks subset (not uagb/Legacy). */
final class WPGPTVibe_Spectra_Adapter {
    private const VERSION = '1.0.11';
    private const NAMES = ['spectra/container','spectra/content','spectra/buttons','spectra/button'];

    public static function supports(string $name): bool { return in_array($name,self::NAMES,true); }
    public static function capabilities(): array {
        $version=defined('SPECTRA_BLOCKS_VER')?(string)SPECTRA_BLOCKS_VER:null;
        $registered=class_exists('WP_Block_Type_Registry')?array_values(array_filter(self::NAMES,static fn($n)=>WP_Block_Type_Registry::get_instance()->is_registered($n))):[];
        return ['builder'=>'spectra-blocks','version'=>$version,'tested_versions'=>[self::VERSION],
            'writable'=>$version===self::VERSION&&count($registered)===count(self::NAMES),'registered_blocks'=>$registered,
            'supported_blocks'=>self::NAMES,'images'=>'core/image (create from attachment id; replace visually or remove/insert)',
            'editable_controls'=>['container'=>['htmlTag','align','layout','width','maxWidth','minHeight','backgroundColor','style','anchor'],'content'=>['text','tagName','style','anchor'],'buttons'=>['layout','style'],'button'=>['text','linkURL','linkTarget','linkRel','style','anchor']],
            'style_restrictions'=>['Container background uses top-level backgroundColor, not style.color.background.','Buttons wrapper has no text colour or typography; style individual Button elements.','Only container/buttons support blockGap.'],
            'style_groups'=>['spacing'=>['padding','margin','blockGap'],'color'=>['text','background'],'typography'=>['fontSize','lineHeight','fontWeight','fontStyle','textAlign','textTransform'],'border'=>['radius','width','color','style']],
            'responsive_layout'=>'Grid minimumColumnWidth and wrapping flex; advanced per-device settings remain in the visual editor. Existing responsive overrides cause conflicting design patches to be refused.',
            'legacy_version'=>defined('UAGB_VERSION')?(string)UAGB_VERSION:null,
            'legacy_writable'=>false,'theme_settings_writable'=>false,
            'note'=>'Use native Spectra Blocks descriptors. Spectra Legacy (uagb/) attribute editing and Astra global/header/footer settings are not supported by this adapter.'];
    }
    public static function require_available(): void {
        if(!self::capabilities()['writable']){throw new RuntimeException('Install and activate the tested Spectra Blocks version '.self::VERSION.' before writing Spectra pages. Spectra Legacy is a separate plugin.');}
    }
    public static function container(string $name): bool {return in_array($name,['spectra/container','spectra/buttons'],true);}
    private static function parent(string $name,?string $parent): void {
        if($name==='spectra/button'&&$parent!=='spectra/buttons'){throw new RuntimeException('Place a Spectra button inside spectra/buttons.');}
        if($parent==='spectra/buttons'&&$name!=='spectra/button'){throw new RuntimeException('spectra/buttons can contain only spectra/button child blocks.');}
    }
    public static function create(array $descriptor,int $depth,?string $parent=null): array {
        self::require_available();$name=$descriptor['name'];self::parent($name,$parent);
        $attributes=self::attributes($name,$descriptor['attributes']??[]);
        $children=$descriptor['children']??[];
        if(!self::container($name)&&$children){throw new RuntimeException('This Spectra block cannot contain child blocks.');}
        if(count($children)>100){throw new RuntimeException('Too many child blocks.');}
        $blocks=[];foreach($children as $child){if(!is_array($child)){throw new RuntimeException('Invalid child block.');}$blocks[]=WPGPTVibe_Block_Manager::new_block($child,$depth+1,$name);}
        return ['blockName'=>$name,'attrs'=>$attributes,'innerBlocks'=>$blocks,'innerHTML'=>'','innerContent'=>array_fill(0,count($blocks),null)];
    }
    public static function update(array $block,array $patch): array {
        self::require_available();$validated=self::attributes($block['blockName'],$patch);
        $design=array_intersect(array_keys($patch),['style','layout','width','maxWidth','minHeight','backgroundColor','align']);
        if($design&&!empty($block['attrs']['responsiveControls'])){throw new RuntimeException('This block has stored responsive overrides. Change its design in the visual editor; text and links remain editable through MCP.');}
        foreach(['width','maxWidth','minHeight','backgroundColor'] as $key){
            if(array_key_exists($key,$patch)&&isset($block['attrs']['style'][$key])){throw new RuntimeException('This dimension or background has an authoritative responsive style. Change it in the visual editor.');}
        }
        // Validate only the requested patch; preserve builder settings outside it.
        foreach(['style','layout'] as $nested){if(isset($validated[$nested])&&is_array($block['attrs'][$nested]??null)){$validated[$nested]=array_replace_recursive($block['attrs'][$nested],$validated[$nested]);}}
        $block['attrs']=array_replace($block['attrs'],$validated);return $block;
    }
    public static function require_for_tree(array $blocks): void {
        foreach($blocks as $block){if(str_starts_with($block['blockName']??'','spectra/')){self::require_available();return;}self::require_for_tree($block['innerBlocks']);}
    }
    public static function validate_tree(array $blocks,?string $parent=null): void {
        foreach($blocks as $block){
            $name=$block['blockName']??'';
            self::parent($name,$parent);
            if(self::supports($name)&&!self::container($name)&&$block['innerBlocks']){throw new RuntimeException('This Spectra block cannot contain child blocks.');}
            self::validate_tree($block['innerBlocks'],$name);
        }
    }
    public static function fresh_ids(array $block): array {
        if(isset($block['attrs']['spectraId'])){$block['attrs']['spectraId']='spectra-'.bin2hex(random_bytes(10));}
        // Anchor IDs identify a single location and cannot be duplicated on one page.
        if(isset($block['attrs']['anchor'])&&self::supports($block['blockName']??'')){unset($block['attrs']['anchor']);}
        $block['innerBlocks']=array_map([self::class,'fresh_ids'],$block['innerBlocks']);return $block;
    }
    private static function attributes(string $name,array $attrs): array {
        $common=$name==='spectra/buttons'?['style']:['style','anchor'];
        $extra=match($name){
            'spectra/container'=>['htmlTag','align','layout','width','maxWidth','minHeight','backgroundColor'],
            'spectra/content'=>['tagName','text'],
            'spectra/buttons'=>['layout'],
            'spectra/button'=>['text','linkURL','linkTarget','linkRel'],
            default=>throw new RuntimeException('Unsupported Spectra block.')
        };
        if(array_diff(array_keys($attrs),array_merge($common,$extra))){throw new RuntimeException('Unsupported Spectra attribute. Use the visual editor for advanced controls.');}
        foreach($attrs as $key=>&$value){
            if($key==='style'){$value=self::style($value,$name);}
            elseif($key==='layout'){$value=self::layout($value,$name);}
            elseif($key==='text'){
                if(!is_string($value)||strlen($value)>20000){throw new RuntimeException('Spectra text must be a string up to 20000 bytes.');}
                $value=wp_kses($value,['a'=>['href'=>true,'title'=>true],'strong'=>[],'em'=>[],'b'=>[],'i'=>[],'br'=>[],'code'=>[],'s'=>[],'sub'=>[],'sup'=>[]]);
            }
            elseif($key==='backgroundColor'){self::colour($value);}
            elseif($key==='anchor'){if(!is_string($value)||!preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,99}$/D',$value)){throw new RuntimeException('Invalid anchor.');}}
            elseif(in_array($key,['width','maxWidth','minHeight'],true)){self::dimension($value);}
            elseif($key==='htmlTag'){self::one_of($value,['div','section','article','aside']);}
            elseif($key==='tagName'){self::one_of($value,['p','h1','h2','h3','h4','h5','h6']);}
            elseif($key==='align'){self::one_of($value,['wide','full','none']);}
            elseif($key==='linkURL'){
                if(!is_string($value)||strlen($value)>2048||preg_match('/[\x00-\x20\\\\]/',$value)||!preg_match('~^(https?://[^/]+|/(?!/)|\#[A-Za-z]|mailto:[^@]+@|tel:\+?[0-9])~i',$value)){throw new RuntimeException('Provide a safe HTTP, relative, anchor, mailto or tel URL.');}
                $value=esc_url_raw($value,['http','https','mailto','tel']);if($value===''){throw new RuntimeException('Invalid URL.');}
            }
            elseif($key==='linkTarget'){self::one_of($value,['_self','_blank']);}
            elseif($key==='linkRel'){
                if(!is_array($value)||!array_is_list($value)||count($value)>5){throw new RuntimeException('Invalid link relation.');}
                foreach($value as $rel){self::one_of($rel,['noopener','noreferrer','nofollow','sponsored','ugc']);}
            }
        }
        unset($value);
        return $attrs;
    }
    private static function one_of($value,array $values): void {if(!in_array($value,$values,true)){throw new RuntimeException('Invalid Spectra option.');}}
    private static function dimension($value): void {
        if(!is_string($value)||!preg_match('/^(?:0|(?:[0-9]+(?:\.[0-9]+)?)(?:px|em|rem|%|vw|vh))$/D',$value)){throw new RuntimeException('Invalid dimension. Use a positive px, rem, em, %, vw or vh value.');}
        $number=(float)$value;$limit=str_ends_with($value,'px')?1600:100;
        if($number>$limit){throw new RuntimeException('Spectra dimension exceeds its safe range.');}
    }
    private static function colour($value): void {if(!is_string($value)||!preg_match('/^#(?:[a-fA-F0-9]{3}|[a-fA-F0-9]{6}|[a-fA-F0-9]{8})$/D',$value)){throw new RuntimeException('Use a hexadecimal colour.');}}
    private static function style($style,string $name): array {
        if(!is_array($style)||array_diff(array_keys($style),['spacing','color','typography','border'])){throw new RuntimeException('Unsupported Spectra style.');}
        if($name==='spectra/container'&&isset($style['color']['background'])){throw new RuntimeException('Use backgroundColor for Spectra container backgrounds.');}
        if($name==='spectra/buttons'&&(isset($style['typography'])||isset($style['color']['text']))){throw new RuntimeException('Unsupported style for the Buttons wrapper. Style the individual buttons.');}
        if(!self::container($name)&&isset($style['spacing']['blockGap'])){throw new RuntimeException('Unsupported gap on a leaf block. Style its container.');}
        foreach($style as $group=>$settings){
            $allowed=match($group){'spacing'=>['padding','margin','blockGap'],'color'=>['text','background'],'typography'=>['fontSize','lineHeight','fontWeight','fontStyle','textAlign','textTransform'],'border'=>['radius','width','color','style']};
            if(!is_array($settings)||array_diff(array_keys($settings),$allowed)){throw new RuntimeException('Unsupported Spectra style property.');}
            foreach($settings as $key=>$value){
                if(($group==='color')||($group==='border'&&$key==='color')){self::colour($value);}
                elseif($group==='spacing'&&in_array($key,['padding','margin'],true)&&is_array($value)){
                    if(array_diff(array_keys($value),['top','right','bottom','left'])){throw new RuntimeException('Invalid spacing side.');}foreach($value as $dimension){self::dimension($dimension);}
                }
                elseif($key==='fontWeight'){self::one_of($value,['100','200','300','400','500','600','700','800','900']);}
                elseif($key==='fontStyle'){self::one_of($value,['normal','italic']);}
                elseif($key==='textAlign'){self::one_of($value,['left','center','right']);}
                elseif($key==='textTransform'){self::one_of($value,['none','uppercase','lowercase','capitalize']);}
                elseif($key==='lineHeight'){if(!is_string($value)||!preg_match('/^[1-3](?:\.[0-9]{1,2})?$/D',$value)){throw new RuntimeException('Invalid line height.');}}
                elseif($group==='border'&&$key==='style'){self::one_of($value,['solid','dashed','dotted','none']);}
                else{self::dimension($value);}
            }
        }
        return $style;
    }
    private static function layout($layout,string $name): array {
        if(!is_array($layout)||array_diff(array_keys($layout),['type','flexWrap','orientation','justifyContent','verticalAlignment','columnCount','minimumColumnWidth'])){throw new RuntimeException('Unsupported layout.');}
        foreach($layout as $key=>$value){
            if($key==='type'){self::one_of($value,$name==='spectra/buttons'?['flex']:['flex','grid','constrained']);}
            elseif($key==='flexWrap'){self::one_of($value,['wrap','nowrap']);}
            elseif($key==='orientation'){self::one_of($value,['horizontal','vertical']);}
            elseif($key==='justifyContent'){self::one_of($value,['left','center','right','space-between','stretch']);}
            elseif($key==='verticalAlignment'){self::one_of($value,['top','center','bottom','stretch']);}
            elseif($key==='columnCount'){if(!is_int($value)||$value<1||$value>6){throw new RuntimeException('Column count must be 1 to 6.');}}
            else{self::dimension($value);}
        }
        return $layout;
    }
    public static function patterns(): array {
        return [['slug'=>'partnerships','title'=>'Partnerships and Certifications','description'=>'Draft with demonstration credentials, native Spectra hero, responsive cards and contact CTA. Replace examples with verified credentials before publishing.','blocks'=>self::pattern('partnerships')]];
    }
    public static function pattern(string $slug): array {
        if($slug!=='partnerships'){throw new RuntimeException('Unknown Spectra pattern.');}
        $text=static fn($copy,$tag='p')=>['name'=>'spectra/content','attributes'=>['text'=>$copy,'tagName'=>$tag]];
        $cards=[];foreach(['Professional certification','Technology partnership','Industry membership'] as $label){$cards[]=['name'=>'spectra/container','attributes'=>['style'=>['spacing'=>['padding'=>['top'=>'24px','right'=>'24px','bottom'=>'24px','left'=>'24px']],'border'=>['radius'=>'12px','width'=>'1px','style'=>'solid','color'=>'#dce2ea']]],'children'=>[$text($label,'h3'),$text('Demonstration entry. Add your verified credential, issuing organisation and verification link.')]];}
        $section=static fn($children,$attrs=[])=>['name'=>'spectra/container','attributes'=>array_replace_recursive(['htmlTag'=>'section','layout'=>['type'=>'flex','orientation'=>'vertical'],'style'=>['spacing'=>['padding'=>['top'=>'40px','right'=>'24px','bottom'=>'40px','left'=>'24px'],'blockGap'=>'20px']]],$attrs),'children'=>$children];
        return [$section([$text('Partnerships and Certifications','h1'),$text('Our credentials, professional development and technology relationships.'),$text('Draft demonstration page. Replace all example entries with verified information before publication.')]),
            $section([$text('Credentials and professional standards','h2'),['name'=>'spectra/container','attributes'=>['layout'=>['type'=>'grid','minimumColumnWidth'=>'240px'],'style'=>['spacing'=>['blockGap'=>'24px']]],'children'=>$cards]]),
            $section([$text('Technology partnerships','h2'),$text('Add approved partner logos and links to your public partner profiles. State the exact relationship and its current status.')]),
            $section([$text('Discuss working together','h2'),$text('Contact our team to discuss a partnership or verify a listed credential.'),['name'=>'spectra/buttons','children'=>[['name'=>'spectra/button','attributes'=>['text'=>'Contact our team','linkURL'=>'/contact/']]]]])];
    }
}

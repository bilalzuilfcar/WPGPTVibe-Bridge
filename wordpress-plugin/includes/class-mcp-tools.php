<?php
if (!defined('ABSPATH')) { exit; }
final class WPGPTVibe_Scope_Exception extends RuntimeException {
    public function __construct(public string $scope){parent::__construct('Connection needs the '.$scope.' permission. Reauthorize ChatGPT.');}
}
final class WPGPTVibe_MCP_Tools {
    private static function string(string $description='',array $extra=[]): array{return array_merge(['type'=>'string','description'=>$description],$extra);}
    private static function integer(): array{return ['type'=>'integer','minimum'=>1];}
    private static function object(): array{return ['type'=>'object'];}
    private static function changes(): array{return ['type'=>'array','minItems'=>1,'maxItems'=>50,'items'=>['type'=>'object']];}
    public static function definitions(): array {
        $id=self::integer();$hash=self::string('Hash returned by the latest page read.',['pattern'=>'^[a-f0-9]{64}$']);$out=[];
        $add=static function($name,$description,$scope,$capability,$properties,$required,$write,$handler,$wp_cap='manage_options')use(&$out){
            if($write){$properties['operation_id']=['type'=>'string','minLength'=>8,'maxLength'=>100,'description'=>'Unique mutation ID. Reuse the same ID and identical arguments after a connection retry.'];$required[]='operation_id';}
            $out[$name]=['name'=>$name,'description'=>$description,'inputSchema'=>['type'=>'object','properties'=>$properties?:new stdClass(),'required'=>$required,'additionalProperties'=>false],
                'annotations'=>['readOnlyHint'=>!$write,'destructiveHint'=>$write,'idempotentHint'=>true,'openWorldHint'=>false],
                'securitySchemes'=>[['type'=>'oauth2','scopes'=>[$scope]]],
                '_meta'=>['securitySchemes'=>[['type'=>'oauth2','scopes'=>[$scope]]]],'scope'=>$scope,'capability'=>$capability,'write'=>$write,'handler'=>$handler,'wp_cap'=>$wp_cap];
        };
        $add('get_profile','Identify the connected WordPress account and website.','site:read','read_site',[],[],false,static fn()=>['id'=>(string)get_current_user_id(),'name'=>wp_get_current_user()->display_name,'site'=>home_url('/')]);$out['get_profile']['_meta']['openai/profile']=true;
        $add('site_info','Inspect this WordPress site and connection capabilities before working.','site:read','read_site',[],[],false,static fn()=>['site_id'=>WPGPTVibe_Auth::site_id(),'name'=>get_bloginfo('name'),'url'=>home_url('/'),'version'=>WPGPTVIBE_VERSION,'capabilities'=>WPGPTVibe_Auth::enabled_capabilities(),'block_names'=>WPGPTVibe_Patterns::block_names(),'block_theme'=>wp_is_block_theme()]);
        $add('get_design_settings','Read the active theme palette, typography presets and layout settings before designing pages. Preserve these brand choices.','site:read','read_site',[],[],false,static fn()=>wp_get_global_settings());
        $add('audit_log','Read recent changes and connection activity.','site:read','read_site',[],[],false,static fn()=>WPGPTVibe_Audit_Log::recent(50));
        $add('list_content','List accessible pages and posts. Content is not modified.','pages:read','read_content',['post_type'=>self::string(),'search'=>self::string()],[],false,static function($a){$items=WPGPTVibe_Content_Manager::list($a['post_type']??'page',['search'=>$a['search']??'']);return array_values(array_filter($items,static fn($p)=>current_user_can('edit_post',$p['id'])));});
        $add('get_page_blocks','Read a page, block tree, current content hash and editor link before editing.','pages:read','read_content',['id'=>$id],['id'],false,static fn($a)=>WPGPTVibe_Block_Manager::get($a['id']));
        $add('list_patterns','List starter sections editable in the WordPress visual editor.','pages:read','read_content',[],[],false,static fn()=>WPGPTVibe_Patterns::catalog());
        $add('list_shared_patterns','List reusable synced WordPress sections. Editing them affects every referencing page.','pages:read','read_content',[],[],false,static fn()=>WPGPTVibe_Shared_Patterns::list());
        $add('get_shared_pattern_usage','Read the complete affected-page report and affected_hash before changing a shared section.','pages:read','read_content',['id'=>$id],['id'],false,static fn($a)=>WPGPTVibe_Shared_Patterns::impact($a['id']));
        $add('create_shared_pattern','Create a reusable synced WordPress section. It does not publish a public page. Insert it with a core/block descriptor and its ref ID.','pages:write','manage_content',['title'=>self::string('Shared section title.',['minLength'=>1,'maxLength'=>200]),'blocks'=>['type'=>'array','minItems'=>1,'maxItems'=>50,'items'=>self::object()]],['title','blocks'],true,static fn($a)=>WPGPTVibe_Shared_Patterns::create($a['title'],$a['blocks']));
        $add('create_page','Create a DRAFT page from editable block descriptors or one starter pattern. No raw HTML is required.','pages:write','manage_content',['title'=>self::string('Page title.',['minLength'=>1,'maxLength'=>200]),'pattern'=>self::string('Starter pattern slug.',['enum'=>['hero','services','cards','faq','cta']]),'blocks'=>['type'=>'array','maxItems'=>100,'items'=>self::object()]],['title'],true,static function($a){
            if(!current_user_can('edit_pages')){throw new RuntimeException('No permission to create pages.');}
            if(isset($a['pattern'],$a['blocks'])){throw new RuntimeException('Choose either a pattern or block descriptors.');}
            $content='';if(isset($a['pattern'])){foreach(WPGPTVibe_Patterns::catalog() as $p){if($p['slug']===$a['pattern']){$content=$p['content'];}}}
            else{$blocks=[];foreach($a['blocks']??[] as $b){$blocks[]=WPGPTVibe_Block_Manager::new_block($b);}$content=serialize_blocks($blocks);}
            $post=WPGPTVibe_Content_Manager::create('page',['title'=>$a['title'],'content'=>$content,'status'=>'draft']);return WPGPTVibe_Block_Manager::get($post['id']);
        });
        $add('preview_page_changes','Preview changes without saving. Paths are zero-based block indices. Supports insert, update, move within a parent, duplicate and remove.','pages:read','read_content',['id'=>$id,'expected_hash'=>$hash,'operations'=>self::changes()],['id','expected_hash','operations'],false,static fn($a)=>WPGPTVibe_Block_Manager::preview($a['id'],$a['expected_hash'],$a['operations']));
        $add('apply_page_changes','Apply reviewed block changes. Requires current hash, enabled revisions and explicit confirmation for live content. Editable WPGPTVibe blocks accept attributes; other blocks can be moved or duplicated without conversion.','layout:write','edit_layout',['id'=>$id,'expected_hash'=>$hash,'operations'=>self::changes(),'confirm_live'=>['type'=>'boolean'],'affected_hash'=>$hash],['id','expected_hash','operations'],true,static fn($a)=>WPGPTVibe_Block_Manager::change($a['id'],$a['expected_hash'],$a['operations'],$a['confirm_live']??false,$a['affected_hash']??null));
        $add('list_revisions','List recovery revisions for an accessible page.','pages:read','read_content',['id'=>$id],['id'],false,static fn($a)=>WPGPTVibe_Block_Manager::revisions($a['id']));
        $add('restore_revision','Restore a chosen page revision. Read the latest hash first and confirm changes to live content.','pages:write','restore_content',['id'=>$id,'revision_id'=>$id,'expected_hash'=>$hash,'confirm_live'=>['type'=>'boolean'],'affected_hash'=>$hash],['id','revision_id','expected_hash'],true,static fn($a)=>WPGPTVibe_Block_Manager::restore($a['id'],$a['revision_id'],$a['expected_hash'],$a['confirm_live']??false,$a['affected_hash']??null));
        $add('publish_page','Publish a reviewed draft after the user explicitly requests publication.','pages:publish','publish_content',['id'=>$id,'expected_hash'=>$hash,'confirm'=>self::string('', ['const'=>'PUBLISH'])],['id','expected_hash','confirm'],true,static function($a){return WPGPTVibe_Block_Manager::locked($a['id'],static function()use($a){clean_post_cache($a['id']);$p=WPGPTVibe_Block_Manager::get($a['id']);if(!hash_equals($p['hash'],$a['expected_hash'])){throw new RuntimeException('This page has newer edits.');}$post=get_post($a['id']);$type=get_post_type_object($post->post_type);if(!current_user_can($type->cap->publish_posts)){throw new RuntimeException('No permission to publish this content.');}if($p['status']!=='draft'&&$p['status']!=='pending'){throw new RuntimeException('Only draft or pending content can be published.');}WPGPTVibe_Content_Manager::update($a['id'],['status'=>'publish']);return WPGPTVibe_Block_Manager::get($a['id']);});});
        $add('check_page','Check missing image descriptions, empty buttons and heading order in editable sections. This is not an accessibility certification.','pages:read','read_content',['id'=>$id],['id'],false,static function($a){$p=WPGPTVibe_Block_Manager::get($a['id']);$issues=[];$last=0;$walk=static function($blocks,$path=[])use(&$walk,&$issues,&$last){foreach($blocks as $i=>$b){$here=array_merge($path,[$i]);$v=$b['attrs'];if($b['blockName']==='wpgptvibe/image'&&empty($v['alt'])){$issues[]=['path'=>$here,'issue'=>'Check image alt text; decorative images may intentionally use an empty description.'];}if($b['blockName']==='wpgptvibe/button'&&(empty($v['label'])||empty($v['url']))){$issues[]=['path'=>$here,'issue'=>'Button needs text and a destination.'];}if(!empty($v['heading'])){$level=$v['level']??2;if($last&&$level>$last+1){$issues[]=['path'=>$here,'issue'=>'Heading skips a level.'];}$last=$level;}$walk($b['innerBlocks'],$here);}};$walk($p['blocks']);return ['issues'=>$issues,'scope'=>'Editable WPGPTVibe blocks only'];});
        $add('list_media','List media attachments.','pages:read','read_content',[],[],false,static fn()=>WPGPTVibe_Media_Manager::list());
        $add('import_media','Import an image or supported media file from an HTTPS URL.','media:write','manage_media',['url'=>self::string(),'filename'=>self::string()],['url'],true,static fn($a)=>WPGPTVibe_Media_Manager::import_url($a['url'],$a['filename']??null),'upload_files');
        $add('update_media','Update an image title, caption, description or alt text.','media:write','manage_media',['id'=>$id,'data'=>self::object()],['id','data'],true,static fn($a)=>WPGPTVibe_Media_Manager::update($a['id'],$a['data']),'upload_files');
        $add('get_seo','Read supported SEO fields for a page.','pages:read','read_content',['id'=>$id],['id'],false,static fn($a)=>WPGPTVibe_SEO_Manager::get($a['id']));
        $add('update_seo','Update SEO title, description, canonical and supported provider fields.','seo:write','manage_seo',['id'=>$id,'seo'=>self::object()],['id','seo'],true,static fn($a)=>WPGPTVibe_SEO_Manager::update($a['id'],$a['seo']));
        $add('list_theme_files','Read the theme file list. Visual page content should use blocks instead.','theme:read','read_theme_files',['theme'=>self::string(),'path'=>self::string()],[],false,static fn($a)=>WPGPTVibe_Theme_Manager::list_files($a['theme']??null,$a['path']??null),'edit_theme_options');
        $add('read_theme_file','Read a theme file when a layout issue requires theme inspection.','theme:read','read_theme_files',['path'=>self::string(),'theme'=>self::string()],['path'],false,static fn($a)=>WPGPTVibe_Theme_Manager::read_file($a['path'],$a['theme']??null),'edit_theme_options');
        $add('create_draft_theme','Clone the active theme into a separate draft; preserve an existing draft.','theme:write','create_draft_themes',[],[],true,static fn()=>WPGPTVibe_Theme_Manager::create_draft(false),'edit_themes');
        $add('get_theme_preview','Get the current draft theme preview URL.','theme:read','read_theme_files',[],[],false,static fn()=>WPGPTVibe_Theme_Manager::preview_url(),'edit_theme_options');
        $add('write_draft_file','Write a file only inside the draft theme. Use visual page tools for ordinary content.','theme:write','edit_theme_files',['path'=>self::string(),'content'=>self::string()],['path','content'],true,static fn($a)=>WPGPTVibe_Theme_Manager::write_file($a['path'],$a['content']),'edit_themes');
        $add('publish_draft_theme','Activate the reviewed draft theme only after explicit user publication instruction.','theme:publish','publish_themes',['confirm'=>self::string('', ['const'=>'PUBLISH'])],['confirm'],true,static fn()=>WPGPTVibe_Theme_Manager::publish_draft(),'switch_themes');
        $add('list_theme_releases','Read available theme release and rollback records.','theme:read','read_theme_files',[],[],false,static fn()=>WPGPTVibe_Theme_Manager::releases(),'edit_theme_options');
        $add('rollback_theme','Restore a selected previous theme release after explicit instruction.','theme:publish','rollback_themes',['release_id'=>self::string(),'confirm'=>self::string('', ['const'=>'ROLLBACK'])],['release_id','confirm'],true,static fn($a)=>WPGPTVibe_Theme_Manager::rollback($a['release_id']),'switch_themes');
        return $out;
    }
    public static function catalog(): array {return array_values(array_map(static function($d){unset($d['scope'],$d['capability'],$d['handler'],$d['wp_cap'],$d['write']);return $d;},self::definitions()));}
    public static function execute(string $name,array $arguments,array $identity): array {
        $definitions=self::definitions();if(!isset($definitions[$name])){throw new RuntimeException('Unknown WordPress tool.');}$d=$definitions[$name];
        if(!in_array($d['scope'],$identity['scopes'],true)){throw new WPGPTVibe_Scope_Exception($d['scope']);}
        wp_set_current_user($identity['user_id']);
        if(!current_user_can($d['wp_cap'])||!in_array($d['capability'],WPGPTVibe_Auth::enabled_capabilities(),true)){throw new RuntimeException('Enable the required WordPress permission in Tools → WPGPTVibe.');}
        self::validate($arguments,$d['inputSchema']);
        if(isset($arguments['id'])&&!current_user_can('edit_post',$arguments['id'])){throw new RuntimeException('No permission for this content.');}
        $run=static fn()=>($d['handler'])($arguments);
        return $d['write']?WPGPTVibe_Operation_Store::run($arguments['operation_id'],$identity,['name'=>$name,'arguments'=>$arguments],$run):$run();
    }
    private static function validate(array $a,array $schema): void {
        $properties=(array)$schema['properties'];if(array_diff(array_keys($a),array_keys($properties))){throw new RuntimeException('Unexpected tool argument.');}
        foreach($schema['required'] as $key){if(!array_key_exists($key,$a)){throw new RuntimeException('Missing argument: '.$key);}}
        foreach($a as $key=>$v){$s=$properties[$key];$type=$s['type'];$valid=match($type){'string'=>is_string($v),'integer'=>is_int($v),'boolean'=>is_bool($v),'array'=>is_array($v)&&array_is_list($v),'object'=>is_array($v)&&(!$v||!array_is_list($v)),default=>false};
            if(!$valid){throw new RuntimeException('Invalid argument type: '.$key);}
            if(isset($s['const'])&&$v!==$s['const']){throw new RuntimeException('Explicit confirmation required: '.$s['const']);}
            if(isset($s['enum'])&&!in_array($v,$s['enum'],true)){throw new RuntimeException('Invalid option: '.$key);}
            if(is_int($v)&&isset($s['minimum'])&&$v<$s['minimum']){throw new RuntimeException('Invalid positive ID.');}
            if(is_string($v)&&(strlen($v)>($s['maxLength']??1048576)||strlen($v)<($s['minLength']??0))){throw new RuntimeException('Argument length is invalid: '.$key);}
            if(isset($s['pattern'])&&!preg_match('/'.$s['pattern'].'/',$v)){throw new RuntimeException('Invalid content hash.');}
            if(is_array($v)&&$type==='array'&&(count($v)>($s['maxItems']??100)||count($v)<($s['minItems']??0))){throw new RuntimeException('Too many or too few items.');}
        }
    }
}

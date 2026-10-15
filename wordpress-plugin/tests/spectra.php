<?php
require __DIR__.'/shared.php';
// The real WordPress parser/serializer is used; registry/media are boundary fixtures.
class WP_Block_Type_Registry {
    public static function get_instance(){return new self();}
    public function is_registered($name){return in_array($name,$GLOBALS['registered_spectra']??[],true);}
}
define('SPECTRA_BLOCKS_VER',getenv('SPECTRA_TEST_VERSION')?:'1.0.11');
$GLOBALS['registered_spectra']=['spectra/container','spectra/content','spectra/buttons','spectra/button'];
if(file_exists(dirname(__DIR__).'/includes/class-spectra-adapter.php'))require_once dirname(__DIR__).'/includes/class-spectra-adapter.php';
$descriptor=['name'=>'spectra/container','attributes'=>['htmlTag'=>'section','layout'=>['type'=>'grid','minimumColumnWidth'=>'240px'],'style'=>['spacing'=>['padding'=>['top'=>'32px','bottom'=>'32px']]]],'children'=>[['name'=>'spectra/content','attributes'=>['tagName'=>'h2','text'=>'Native visual headline']]]];
if(SPECTRA_BLOCKS_VER!=='1.0.11'){
 rejects(fn()=>WPGPTVibe_Block_Manager::new_block($descriptor),'tested Spectra Blocks');exit;
}
try{$block=WPGPTVibe_Block_Manager::new_block($descriptor);}catch(Throwable $e){throw new RuntimeException('FAIL: native Spectra page creation is unavailable: '.$e->getMessage());}
check($block['blockName']==='spectra/container'&&$block['innerBlocks'][0]['blockName']==='spectra/content','native Spectra container and content creation');
$roundtrip=parse_blocks(serialize_block($block))[0];
check($roundtrip['innerBlocks'][0]['attrs']['text']==='Native visual headline','native dynamic Spectra serialization preserves editable text');
$GLOBALS['posts'][500]=new WP_Post(500,serialize_block($block));
$initial=WPGPTVibe_Block_Manager::get(500);
$updated=WPGPTVibe_Block_Manager::change(500,$initial['hash'],[['type'=>'update','path'=>[0,0],'attributes'=>['text'=>'Edited through MCP']]],false);
check($updated['blocks'][0]['innerBlocks'][0]['attrs']['text']==='Edited through MCP','Spectra content update uses guarded page changes');
rejects(fn()=>WPGPTVibe_Block_Manager::change(500,$initial['hash'],[['type'=>'update','path'=>[0,0],'attributes'=>['text'=>'Stale']]],false),'newer edits');
rejects(fn()=>WPGPTVibe_Block_Manager::new_block(['name'=>'spectra/content','attributes'=>['text'=>'No children'],'children'=>[['name'=>'core/paragraph','attributes'=>['content'=>'hidden']]]]),'child blocks');
rejects(fn()=>WPGPTVibe_Block_Manager::new_block(['name'=>'spectra/button','attributes'=>['text'=>'Orphan']]),'spectra/buttons');
rejects(fn()=>WPGPTVibe_Block_Manager::new_block(['name'=>'spectra/container','attributes'=>['htmlAttributes'=>['onclick'=>'alert(1)']]]),'Unsupported Spectra attribute');
rejects(fn()=>WPGPTVibe_Block_Manager::new_block(['name'=>'spectra/content','attributes'=>['style'=>['color'=>['text'=>'url(evil)']]]]),'colour');
rejects(fn()=>WPGPTVibe_Block_Manager::new_block(['name'=>'spectra/container','attributes'=>['style'=>['spacing'=>['padding'=>['top'=>'-99999px']]]]]),'dimension');
rejects(fn()=>WPGPTVibe_Block_Manager::new_block(['name'=>'spectra/buttons','children'=>[['name'=>'spectra/button','attributes'=>['linkURL'=>'javascript:alert(1)']]]]),'URL');
$empty=WPGPTVibe_Block_Manager::new_block(['name'=>'spectra/container']);
$GLOBALS['posts'][501]=new WP_Post(501,serialize_block($empty));$p=WPGPTVibe_Block_Manager::get(501);
$insert=WPGPTVibe_Block_Manager::preview(501,$p['hash'],[['type'=>'insert','path'=>[0,0],'block'=>['name'=>'spectra/content','attributes'=>['text'=>'Previously empty']]]]);
check($insert['blocks'][0]['innerBlocks'][0]['attrs']['text']==='Previously empty','insert into an empty dynamic Spectra container');
$existing=$block;$existing['attrs']['futureSetting']=['preserve'=>true];$existing['attrs']['style']['border']=['radius'=>'12px'];$existing['attrs']['spectraId']='spectra-original';
$GLOBALS['posts'][502]=new WP_Post(502,serialize_block($existing));$p=WPGPTVibe_Block_Manager::get(502);
$r=WPGPTVibe_Block_Manager::preview(502,$p['hash'],[['type'=>'update','path'=>[0],'attributes'=>['style'=>['color'=>['background'=>'#ffffff']]]],['type'=>'duplicate','path'=>[0]]]);
check($r['blocks'][0]['attrs']['futureSetting']===['preserve'=>true]&&$r['blocks'][0]['attrs']['style']['border']['radius']==='12px','unrelated existing Spectra attributes and nested styles survive updates');
check($r['blocks'][1]['attrs']['spectraId']!==$r['blocks'][0]['attrs']['spectraId'],'duplicating Spectra sections regenerates CSS identifiers');
rejects(fn()=>WPGPTVibe_Block_Manager::preview(500,$updated['hash'],[['type'=>'insert','path'=>[0,0,0],'block'=>['name'=>'core/paragraph','attributes'=>['content'=>'No leaf insertion']]]]),'contain');
$GLOBALS['meta'][500]['_wpgptvibe_content_only']=true;
rejects(fn()=>WPGPTVibe_Block_Manager::preview(500,$updated['hash'],[['type'=>'update','path'=>[0,0],'attributes'=>['tagName'=>'h1']]]),'Content-only');
$GLOBALS['meta'][500]=[];
$GLOBALS['registered_spectra']=[];
rejects(fn()=>WPGPTVibe_Block_Manager::new_block($descriptor),'tested Spectra Blocks');
check(WPGPTVibe_Spectra_Adapter::capabilities()['writable']===false,'missing Spectra registration never claims writable support');
$GLOBALS['registered_spectra']=['spectra/container','spectra/content','spectra/buttons','spectra/button'];
function wp_attachment_is_image($id){return $id===77||$id===78;}
function wp_get_attachment_image_src($id,$size){return ['https://example.com/image-'.$id.'.png',64,64];}
function esc_url($value){return htmlspecialchars($value,ENT_QUOTES);}
function esc_attr($value){return htmlspecialchars($value,ENT_QUOTES);}
$image=WPGPTVibe_Block_Manager::new_block(['name'=>'core/image','attributes'=>['id'=>77,'alt'=>'Partner logo']]);
check($image['blockName']==='core/image'&&str_contains($image['innerHTML'],'wp-image-77'),'native image creation uses a real media attachment');
rejects(fn()=>WPGPTVibe_Block_Manager::new_block(['name'=>'core/image','attributes'=>['id'=>79]]),'image attachment');
rejects(fn()=>WPGPTVibe_Block_Manager::new_block(['name'=>'core/image','attributes'=>['id'=>77,'url'=>'https://evil.example/a.svg']]),'image attributes');

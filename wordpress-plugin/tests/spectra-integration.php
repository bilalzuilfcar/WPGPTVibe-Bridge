<?php
if(!getenv('WPGPTVIBE_TEST_SITE')){throw new RuntimeException('Disposable test site required.');}
require getenv('WPGPTVIBE_WP_CORE').'/wp-load.php';
wp_set_current_user(1);
function spectra_verify($value,$message){if(!$value){throw new RuntimeException('FAIL: '.$message);}echo 'PASS: '.$message."\n";}
$identity=['user_id'=>1,'scopes'=>WPGPTVibe_OAuth::SCOPES,'client_id'=>'spectra-integration'];
spectra_verify(WPGPTVibe_Spectra_Adapter::capabilities()['writable'],'actual registered Spectra Blocks version is supported');
$created=WPGPTVibe_MCP_Tools::execute('create_spectra_page',['title'=>'Partnerships and Certifications (test draft)','pattern'=>'partnerships','operation_id'=>'spectra-create-trial'],$identity);
spectra_verify($created['status']==='draft'&&$created['blocks'][0]['blockName']==='spectra/container','MCP creates a draft with genuine Spectra blocks');
$replay=WPGPTVibe_MCP_Tools::execute('create_spectra_page',['title'=>'Partnerships and Certifications (test draft)','pattern'=>'partnerships','operation_id'=>'spectra-create-trial'],$identity);
spectra_verify($replay['id']===$created['id'],'Spectra creation retry returns the same draft');
$html=do_blocks(get_post($created['id'])->post_content);
spectra_verify(str_contains($html,'Partnerships and Certifications')&&str_contains($html,'spectra-container')&&str_contains($html,'href="/contact/"'),'Spectra PHP renders native heading, containers and CTA');
$before=get_post($created['id'])->post_content;
$preview=WPGPTVibe_MCP_Tools::execute('preview_page_changes',['id'=>$created['id'],'expected_hash'=>$created['hash'],'operations'=>[['type'=>'update','path'=>[0,0],'attributes'=>['text'=>'MCP native Spectra headline']]]],$identity);
spectra_verify(get_post($created['id'])->post_content===$before,'Spectra change preview does not save the page');
$updated=WPGPTVibe_MCP_Tools::execute('apply_page_changes',['id'=>$created['id'],'expected_hash'=>$created['hash'],'operations'=>[['type'=>'update','path'=>[0,0],'attributes'=>['text'=>'MCP native Spectra headline']],['type'=>'update','path'=>[0],'attributes'=>['backgroundColor'=>'#123456','style'=>['color'=>['text'=>'#ffffff']]]]],'operation_id'=>'spectra-update-trial'],$identity);
spectra_verify(str_contains(do_blocks(get_post($created['id'])->post_content),'MCP native Spectra headline'),'MCP edits render through Spectra itself');
spectra_verify(str_contains(do_blocks(get_post($created['id'])->post_content),'#123456'),'native container background setting renders in Spectra');
try{WPGPTVibe_MCP_Tools::execute('apply_page_changes',['id'=>$created['id'],'expected_hash'=>$created['hash'],'operations'=>[['type'=>'duplicate','path'=>[0]]],'operation_id'=>'spectra-stale-write'],$identity);throw new RuntimeException('Stale write was accepted');}catch(RuntimeException $e){spectra_verify(str_contains($e->getMessage(),'newer edits'),'Spectra rejects stale changes');}
// Use real WordPress media. Files are generated locally, not fetched externally.
$uploads=wp_upload_dir();$images=[];
foreach(['first','second'] as $label){
 $path=$uploads['path'].'/spectra-'.$label.'.png';file_put_contents($path,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
 $id=wp_insert_attachment(['post_title'=>'Spectra '.$label.' image','post_mime_type'=>'image/png','post_status'=>'inherit'],$path);update_post_meta($id,'_wp_attachment_metadata',['width'=>1,'height'=>1,'file'=>_wp_relative_upload_path($path),'sizes'=>[]]);$images[]=$id;
}
$withImage=WPGPTVibe_MCP_Tools::execute('apply_page_changes',['id'=>$created['id'],'expected_hash'=>$updated['hash'],'operations'=>[['type'=>'insert','path'=>[0,1],'block'=>['name'=>'core/image','attributes'=>['id'=>$images[0],'alt'=>'Demonstration partner image']]]],'operation_id'=>'spectra-image-insert'],$identity);
spectra_verify(str_contains(do_blocks(get_post($created['id'])->post_content),'wp-image-'.$images[0]),'native media image renders in Spectra section');
// A pre-existing builder page is not converted, and unrelated content survives.
$preexisting=wp_insert_post(['post_type'=>'page','post_status'=>'draft','post_title'=>'Existing Spectra page','post_content'=>'<!-- wp:spectra/container {"anchor":"existing-section"} --><!-- wp:spectra/content {"tagName":"h2","text":"Existing heading"} /--><!-- wp:spectra/content {"text":"Keep this paragraph"} /--><!-- /wp:spectra/container -->']);
$old=WPGPTVibe_Block_Manager::get($preexisting);
$edited=WPGPTVibe_Block_Manager::change($preexisting,$old['hash'],[['type'=>'update','path'=>[0,0],'attributes'=>['text'=>'Improved existing heading']]],false);
spectra_verify($edited['blocks'][0]['attrs']['anchor']==='existing-section'&&$edited['blocks'][0]['innerBlocks'][1]['attrs']['text']==='Keep this paragraph','existing Spectra page update preserves unrelated content');
// Publishing/recovery is exercised on a separate disposable copy, not the browser fixture.
$publication=WPGPTVibe_MCP_Tools::execute('create_spectra_page',['title'=>'Disposable publication test','blocks'=>[['name'=>'spectra/content','attributes'=>['text'=>'Before revision','tagName'=>'h1']]],'operation_id'=>'spectra-publication-create'],$identity);
$changed=WPGPTVibe_Block_Manager::change($publication['id'],$publication['hash'],[['type'=>'update','path'=>[0],'attributes'=>['text'=>'After revision']]],false);
$revision=null;foreach(wp_get_post_revisions($publication['id']) as $r){if(str_contains($r->post_content,'Before revision')){$revision=$r;break;}}
spectra_verify((bool)$revision,'native Spectra revisions preserve previous editable content');
$restored=WPGPTVibe_Block_Manager::restore($publication['id'],$revision->ID,$changed['hash'],false);
spectra_verify(str_contains(do_blocks(get_post($publication['id'])->post_content),'Before revision'),'Spectra revision restore renders original content');
try{WPGPTVibe_MCP_Tools::execute('publish_page',['id'=>$publication['id'],'expected_hash'=>$restored['hash'],'confirm'=>'PUBLISH','operation_id'=>'spectra-publish-test'],$identity);throw new RuntimeException('API publication was allowed');}catch(RuntimeException $e){spectra_verify(str_contains($e->getMessage(),'Manual review'),'Spectra publication requires the WordPress editor');}
file_put_contents('/tmp/wpgptvibe-spectra-test.json',wp_json_encode(['id'=>$created['id'],'image_ids'=>$images,'image_urls'=>array_map('wp_get_attachment_url',$images),'theme'=>wp_get_theme()->get('Version'),'spectra'=>SPECTRA_BLOCKS_VER]));

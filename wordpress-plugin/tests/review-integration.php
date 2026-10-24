<?php
if(!getenv('WPGPTVIBE_TEST_SITE')){throw new RuntimeException('Disposable test site required.');}
require getenv('WPGPTVIBE_WP_CORE').'/wp-load.php';wp_set_current_user(1);
function review_verify($v,$message){if(!$v){throw new RuntimeException('FAIL: '.$message);}echo 'PASS: '.$message."\n";}
function review_reject(callable $fn,string $message){try{$fn();}catch(Throwable $e){review_verify(str_contains($e->getMessage(),$message),$message);return;}throw new RuntimeException('FAIL: expected '.$message);}
$identity=['user_id'=>1,'client_id'=>'product-integration','scopes'=>WPGPTVibe_OAuth::SCOPES];
$source=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'Original public page','post_content'=>'<!-- wp:heading --><h2 class="wp-block-heading">Original public headline</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Original protected content</p><!-- /wp:paragraph -->']);
update_post_meta($source,'_wp_page_template','default');update_post_meta($source,'_private_plugin_setting','never-copy');
$before=WPGPTVibe_Block_Manager::get($source);
review_reject(fn()=>WPGPTVibe_Block_Manager::change($source,$before['hash'],[['type'=>'update','path'=>[0],'attributes'=>['content'=>'Bypass']]],true),'Manual review');
review_reject(fn()=>WPGPTVibe_SEO_Manager::update($source,['title'=>'Bypass']),'Manual review');
review_reject(fn()=>WPGPTVibe_Meta_Manager::update($source,['public_meta'=>'Bypass']),'Manual review');
review_reject(fn()=>WPGPTVibe_Content_Manager::create('page',['title'=>'Unreviewed publication','status'=>'publish']),'Manual review');
review_reject(fn()=>WPGPTVibe_Content_Manager::delete($source,false),'Manual review');
$copy_args=['id'=>$source,'expected_hash'=>$before['hash'],'title'=>'Redesign for human review','operation_id'=>'product-copy-page'];
$copy=WPGPTVibe_MCP_Tools::execute('copy_page_for_review',$copy_args,$identity);
$replay=WPGPTVibe_MCP_Tools::execute('copy_page_for_review',$copy_args,$identity);
review_verify($copy['id']===$replay['id']&&$copy['status']==='draft','copy retries return one draft');
review_verify(get_post_meta($copy['id'],'_private_plugin_setting',true)==='','review copies do not copy arbitrary metadata');
$changed=WPGPTVibe_MCP_Tools::execute('apply_page_changes',['id'=>$copy['id'],'expected_hash'=>$copy['hash'],'operations'=>[['type'=>'update','path'=>[0],'attributes'=>['content'=>'Redesigned draft headline']]],'operation_id'=>'product-redesign-edit'],$identity);
review_verify(WPGPTVibe_Block_Manager::get($source)['hash']===$before['hash']&&get_post_status($source)==='publish','redesign editing preserves original live page and URL');
$ready=WPGPTVibe_MCP_Tools::execute('request_page_review',['id'=>$copy['id'],'expected_hash'=>$changed['hash'],'operation_id'=>'product-review-submit'],$identity);
review_verify($ready['status']==='pending','review handoff keeps the page unpublished');
$listed=WPGPTVibe_MCP_Tools::execute('list_review_drafts',[],$identity);review_verify(in_array($copy['id'],array_column($listed,'id'),true),'submitted draft appears in review queue');
review_reject(fn()=>WPGPTVibe_MCP_Tools::execute('publish_page',['id'=>$copy['id'],'expected_hash'=>$ready['hash'],'confirm'=>'PUBLISH','operation_id'=>'product-review-publish-denied'],$identity),'Manual review');
$shared=WPGPTVibe_Shared_Patterns::create('Protected shared section',[['name'=>'core/paragraph','attributes'=>['content'=>'Shared public copy']]]);
wp_update_post(['ID'=>$source,'post_content'=>get_post($source)->post_content.'<!-- wp:block {"ref":'.$shared['id'].'} /-->']);
$shared=WPGPTVibe_Block_Manager::get($shared['id']);
review_reject(fn()=>WPGPTVibe_Block_Manager::change($shared['id'],$shared['hash'],[['type'=>'update','path'=>[0],'attributes'=>['content'=>'Shared bypass']]],true,$shared['shared_usage']['hash']),'Manual review');
// Exercise the exact immutable theme release through the same manager as both transports.
$original_theme=get_stylesheet();WPGPTVibe_Theme_Manager::create_draft(false);
review_reject(fn()=>WPGPTVibe_Theme_Manager::publish_draft(),'administrator');
WPGPTVibe_Review_Gate::approve_theme();$draft=WPGPTVibe_Theme_Manager::draft_status();$draft_root=wp_get_theme($draft['stylesheet'])->get_stylesheet_directory();
file_put_contents($draft_root.'/review-proof.txt','Changed after approval');
review_reject(fn()=>WPGPTVibe_Theme_Manager::publish_draft(),'administrator');
WPGPTVibe_Review_Gate::approve_theme();$published=WPGPTVibe_Theme_Manager::publish_draft();
review_verify(get_stylesheet()===$published['release_stylesheet'],'exact human-approved theme publishes through the guarded manager');
review_reject(fn()=>WPGPTVibe_Theme_Manager::publish_draft(),'administrator');switch_theme($original_theme);
file_put_contents('/tmp/wpgptvibe-review-test.json',wp_json_encode(['source_id'=>$source,'draft_id'=>$copy['id'],'source_hash'=>WPGPTVibe_Block_Manager::get($source)['hash']]));

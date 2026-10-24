<?php
require __DIR__.'/review.php';
if(file_exists(dirname(__DIR__).'/includes/class-draft-manager.php'))require dirname(__DIR__).'/includes/class-draft-manager.php';
check(class_exists('WPGPTVibe_Draft_Manager'),'reviewable redesign draft manager exists');
$GLOBALS['posts'][900]=new WP_Post(900,'<!-- wp:paragraph --><p>Original public page</p><!-- /wp:paragraph -->');$GLOBALS['posts'][900]->post_status='publish';$GLOBALS['posts'][900]->post_title='Original';
$GLOBALS['meta'][900]=['_wp_page_template'=>'full-width.php','_secret_plugin_key'=>'never-copy'];
$source=WPGPTVibe_Block_Manager::get(900);
$copy=WPGPTVibe_Draft_Manager::copy(900,$source['hash'],'Redesign draft');
check($copy['status']==='draft'&&$copy['id']!==900,'redesign creates a separate unpublished page');
check(get_post(900)->post_status==='publish'&&get_post(900)->post_content===get_post($copy['id'])->post_content,'copy leaves original published content unchanged');
check(get_post_meta($copy['id'],'_wp_page_template',true)==='full-width.php','copy preserves the native page template');
check(get_post_meta($copy['id'],'_secret_plugin_key',true)==='','copy does not copy arbitrary plugin metadata');
rejects(fn()=>WPGPTVibe_Draft_Manager::copy(900,str_repeat('a',64),'Stale'),'newer edits');
$submitted=WPGPTVibe_Draft_Manager::submit($copy['id'],$copy['hash']);
check($submitted['status']==='pending'&&str_contains($submitted['editor_url'],'post.php'),'review submission leaves publication to the WordPress editor');
rejects(fn()=>WPGPTVibe_Draft_Manager::submit(900,$source['hash']),'Manual review');
$GLOBALS['deny']=true;rejects(fn()=>WPGPTVibe_Draft_Manager::copy(900,$source['hash'],'Denied'),'permission');$GLOBALS['deny']=false;

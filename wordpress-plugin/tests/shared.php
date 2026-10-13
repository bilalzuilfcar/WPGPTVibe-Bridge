<?php
require __DIR__.'/recovery.php';
check(file_exists(dirname(__DIR__).'/includes/class-shared-patterns.php'),'shared pattern tools exist');
require dirname(__DIR__).'/includes/class-shared-patterns.php';
check(WPGPTVibe_Shared_Patterns::references('<!-- wp:block {"ref":42} /-->',42),'shared pattern reference detected');
check(!WPGPTVibe_Shared_Patterns::references('<!-- wp:block {"ref":142} /-->',42),'reference scan matches exact pattern ID');
$typography=WPGPTVibe_Patterns::attributes('wpgptvibe/text',['fontSize'=>20,'headingSize'=>36]);
check($typography['fontSize']===20&&$typography['headingSize']===36,'visual typography controls accept safe sizes');
rejects(fn()=>WPGPTVibe_Patterns::attributes('wpgptvibe/text',['fontSize'=>-1]),'out of range');
class WP_Query { public array $posts; public function __construct($args){$this->posts=$GLOBALS['query_posts']??[];} }
function get_post_types($args=[],$output='names'){return ['page','post'];}
$GLOBALS['posts'][42]=new WP_Post(42,'<!-- wp:wpgptvibe/text {"heading":"Reusable"} /-->');$GLOBALS['posts'][42]->post_type='wp_block';
$GLOBALS['posts'][43]=new WP_Post(43,'<!-- wp:block {"ref":42} /-->');$GLOBALS['posts'][43]->post_type='wp_block';
$pattern=WPGPTVibe_Block_Manager::get(42);
rejects(fn()=>WPGPTVibe_Block_Manager::preview(42,$pattern['hash'],[['type'=>'insert','path'=>[1],'block'=>['name'=>'core/block','attributes'=>['ref'=>42]]]]),'recursive');
rejects(fn()=>WPGPTVibe_Block_Manager::preview(42,$pattern['hash'],[['type'=>'insert','path'=>[1],'block'=>['name'=>'core/block','attributes'=>['ref'=>43]]]]),'recursive');
$GLOBALS['query_posts']=array_fill(0,501,new WP_Post(100,'<!-- wp:paragraph --><p>Page</p><!-- /wp:paragraph -->'));
$readLarge=WPGPTVibe_Block_Manager::get(42);
check($readLarge['shared_usage']['complete']===false,'large-site shared section read reports unavailable impact without failing');
$createdLarge=WPGPTVibe_Shared_Patterns::create('Large site shared section',[['name'=>'wpgptvibe/text','attributes'=>['heading'=>'Still created']]]);
check($createdLarge['id']>0&&$createdLarge['shared_usage']['complete']===false,'large-site shared creation reports success with unavailable impact');

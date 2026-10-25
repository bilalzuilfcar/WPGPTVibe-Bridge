<?php
require __DIR__.'/spectra.php';
if(file_exists(dirname(__DIR__).'/includes/class-review-gate.php'))require_once dirname(__DIR__).'/includes/class-review-gate.php';
$GLOBALS['posts'][800]=new WP_Post(800,'<!-- wp:paragraph --><p>Live original</p><!-- /wp:paragraph -->');$GLOBALS['posts'][800]->post_status='publish';
$p=WPGPTVibe_Block_Manager::get(800);
rejects(fn()=>WPGPTVibe_Block_Manager::change(800,$p['hash'],[['type'=>'update','path'=>[0],'attributes'=>['content'=>'Unauthorized live edit']]],true),'Manual review');
check(str_contains(get_post(800)->post_content,'Live original'),'live page stays unchanged even when confirm_live is supplied');
check(class_exists('WPGPTVibe_Review_Gate'),'manual review policy is loaded');
foreach(['publish','private','future','trash'] as $status){rejects(fn()=>WPGPTVibe_Review_Gate::assert_draft_status(['status'=>$status]),'Manual review');}
WPGPTVibe_Review_Gate::assert_draft_status(['status'=>'draft']);check(true,'draft content remains editable');
$GLOBALS['posts'][801]=new WP_Post(801,'<!-- wp:paragraph --><p>Draft awaiting human publication</p><!-- /wp:paragraph -->');
$GLOBALS['options']['wpgptvibe_capabilities']['publish_content']=true;$identity=['user_id'=>1,'client_id'=>'review-tests','scopes'=>['pages:publish','pages:read','pages:write','site:read']];
$p=WPGPTVibe_Block_Manager::get(801);
rejects(fn()=>WPGPTVibe_MCP_Tools::execute('publish_page',['id'=>801,'expected_hash'=>$p['hash'],'confirm'=>'PUBLISH','operation_id'=>'review-publish-1'],$identity),'Manual review');
check(get_post(801)->post_status==='draft','MCP cannot publish its own drafts');
$GLOBALS['query_posts']=[get_post(800)];$GLOBALS['posts'][802]=new WP_Post(802,'<!-- wp:paragraph --><p>Live shared section</p><!-- /wp:paragraph -->');$GLOBALS['posts'][802]->post_type='wp_block';$GLOBALS['posts'][802]->post_status='publish';
$GLOBALS['posts'][800]->post_content='<!-- wp:block {"ref":802} /-->';$shared=WPGPTVibe_Block_Manager::get(802);
rejects(fn()=>WPGPTVibe_Block_Manager::change(802,$shared['hash'],[['type'=>'update','path'=>[0],'attributes'=>['content'=>'Live shared bypass']]],true,$shared['shared_usage']['hash']),'Manual review');
$GLOBALS['query_posts']=[];
require dirname(__DIR__).'/includes/class-meta-manager.php';
require dirname(__DIR__).'/includes/class-seo-manager.php';
rejects(fn()=>WPGPTVibe_Meta_Manager::update(800,[]),'Manual review');
rejects(fn()=>WPGPTVibe_SEO_Manager::update(800,[]),'Manual review');
rejects(fn()=>WPGPTVibe_Review_Gate::assert_editable(802),'Manual review');
function get_file_data($file,$headers,$context='') { $data=file_get_contents($file);$out=[];foreach($headers as $key=>$name){preg_match('/^'.preg_quote($name,'/').':\s*(.*)$/m',$data,$m);$out[$key]=trim($m[1]??'');}return $out; }
// Real on-disk theme fixtures; fake WP theme lookup only at the platform boundary.
class WPGPTVibe_Theme_Manager {public static function draft_status(){return ['exists'=>true,'stylesheet'=>'review-draft','source_stylesheet'=>'review-source'];}}
function wp_get_theme($slug=''){return new class($slug){public function __construct(private $slug){}public function exists(){return true;}public function get_stylesheet_directory(){return $GLOBALS['theme_roots'][$this->slug];}};}
$base=dirname(__DIR__,2).'/.test-runtime/review-themes';@mkdir($base.'/draft',0777,true);@mkdir($base.'/release',0777,true);
@mkdir($base.'/source',0777,true);file_put_contents($base.'/source/style.css','Theme Name: Source');
$GLOBALS['theme_roots']=['review-draft'=>$base.'/draft','review-source'=>$base.'/source'];file_put_contents($base.'/draft/style.css','Theme Name: Review');file_put_contents($base.'/release/style.css','Theme Name: Unapproved');
WPGPTVibe_Review_Gate::approve_theme();
rejects(fn()=>WPGPTVibe_Review_Gate::consume_theme_approval($base.'/release'),'administrator');
WPGPTVibe_Review_Gate::approve_theme();file_put_contents($base.'/draft/style.css','Theme Name: Changed');
check(WPGPTVibe_Review_Gate::theme_approval_status()['approved']===false,'changed draft files invalidate the displayed approval status');
WPGPTVibe_Review_Gate::approve_theme();$GLOBALS['deny']=true;
rejects(fn()=>WPGPTVibe_Review_Gate::consume_theme_approval(),'administrator');$GLOBALS['deny']=false;
WPGPTVibe_Review_Gate::approve_theme();WPGPTVibe_Review_Gate::consume_theme_approval();
rejects(fn()=>WPGPTVibe_Review_Gate::consume_theme_approval(),'administrator');

foreach (['functions.php','client.js','templates/index.html'] as $file) { rejects(fn()=>WPGPTVibe_Review_Gate::assert_css_write($file),'CSS'); }
rejects(fn()=>WPGPTVibe_Review_Gate::assert_css_write('style.css','</style><script>alert(1)</script>'),'executable');
file_put_contents($base.'/draft/functions.php','<?php echo "unapproved";');
rejects(fn()=>WPGPTVibe_Review_Gate::assert_safe_theme_preview($base.'/draft',$base.'/source'),'staging');
rejects(fn()=>WPGPTVibe_Review_Gate::approve_theme(),'staging');
unlink($base.'/draft/functions.php');
file_put_contents($base.'/draft/style.css',"Theme Name: Changed\nTemplate: evil-parent");
rejects(fn()=>WPGPTVibe_Review_Gate::assert_safe_theme_preview($base.'/draft',$base.'/source'),'staging');
file_put_contents($base.'/draft/style.css','Theme Name: Review');

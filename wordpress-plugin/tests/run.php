<?php
// Small WordPress adapter harness; real block parser is loaded from the supplied core checkout.
define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS',60);
define('WPGPTVIBE_VERSION', '0.4.0');
$GLOBALS['transients'] = [];
function get_transient($k){return $GLOBALS['transients'][$k]??false;}
function set_transient($k,$v,$ttl){$GLOBALS['transients'][$k]=$v;return true;}
function delete_transient($k){unset($GLOBALS['transients'][$k]);}
$GLOBALS['options'] = []; $GLOBALS['posts'] = []; $GLOBALS['meta'] = []; $GLOBALS['current_user'] = 1;
class WP_Post { public $ID; public $post_content; public $post_status='draft'; public $post_type='page'; public $post_title='Test'; public $post_modified_gmt='2026-10-13 00:00:00'; public function __construct($id,$content){$this->ID=$id;$this->post_content=$content;} }
class WP_Error { public function __construct(public $code,public $message,public $data=[]){} public function get_error_message(){return $this->message;} }
function is_wp_error($x){return $x instanceof WP_Error;}
function get_option($key,$default=false){return $GLOBALS['options'][$key]??$default;}
function update_option($key,$value,$autoload=false){$GLOBALS['options'][$key]=$value;return true;}
function add_option($key,$value,$deprecated='',$autoload=false){if(isset($GLOBALS['options'][$key]))return false;$GLOBALS['options'][$key]=$value;return true;}
function delete_option($key){unset($GLOBALS['options'][$key]);return true;}
function get_post($id){return $GLOBALS['posts'][$id]??null;}
function clean_post_cache($id){}
function get_post_meta($id,$key,$single=true){return $GLOBALS['meta'][$id][$key]??'';}
function update_post_meta($id,$key,$value){$GLOBALS['meta'][$id][$key]=$value;}
function wp_is_post_revision($id){return false;}
function current_user_can($cap,...$args){return !($GLOBALS['deny']??false);}
function get_current_user_id(){return $GLOBALS['current_user'];}
function wp_set_current_user($id){$GLOBALS['current_user']=$id;}
function get_userdata($id){return $id===1?(object)['ID'=>1,'display_name'=>'Tester']:false;}
function user_can($user,$cap,...$args){return $user && !($GLOBALS['deny']??false);}
function wp_json_encode($x,$flags=0){return json_encode($x,$flags);}
function wp_unslash($x){return $x;}
function wp_slash($x){return $x;}
function wp_kses($x,$allowed){return strip_tags($x,'<a><strong><em><b><i><br><code><s><sub><sup>');}
function wp_kses_post($x){return preg_replace('#<script.*?</script>#si','',$x);}
function sanitize_key($x){return preg_replace('/[^a-z0-9_\-]/','',strtolower($x));}
function sanitize_text_field($x){return strip_tags($x);}
function wp_generate_uuid4(){return '12345678-1234-4234-8234-123456789abc';}
function get_current_blog_id(){return 1;}
function home_url($path=''){return 'https://example.com'.$path;}
function rest_url($path=''){return 'https://example.com/wp-json/'.$path;}
function admin_url($path=''){return 'https://example.com/wp-admin/'.$path;}
function get_edit_post_link($id,$context=''){return admin_url('post.php?post='.$id.'&action=edit');}
function get_preview_post_link($id){return home_url('/?preview=true&p='.$id);}
function get_permalink($id){return home_url('/?p='.(is_object($id)?$id->ID:$id));}
function post_type_supports($type,$feature){return !($GLOBALS['no_revisions']??false);}
function wp_revisions_enabled($post){return !($GLOBALS['no_revisions']??false);}
function wp_save_post_revision($id){return 99;}
function wp_update_post($data,$error=false){$p=$GLOBALS['posts'][$data['ID']];foreach($data as $k=>$v){if($k!=='ID')$p->$k=$v;}return $p->ID;}
function wp_insert_post($data,$error=false){$id=count($GLOBALS['posts'])+1;$p=new WP_Post($id,$data['post_content']??'');foreach($data as $k=>$v)$p->$k=$v;$GLOBALS['posts'][$id]=$p;return $id;}
function get_post_type_object($type){return (object)['public'=>true,'show_ui'=>true,'cap'=>(object)['create_posts'=>'edit_pages','publish_posts'=>'publish_pages']];}
function apply_filters($name,$value,...$args){return $value;}
function is_ssl(){return true;}
function wp_parse_url($url,$component=-1){return parse_url($url,$component);}
function esc_url_raw($x){return $x;}
function get_bloginfo($x){return 'Example';}
class TestDB {public $posts="wp_posts";function query($sql){return 1;} function prepare($sql,...$args){if(str_contains($sql,'GET_LOCK')&&strlen($args[0])>64)throw new RuntimeException('MySQL lock name exceeds 64 characters');return $sql;} function get_var($sql){return str_contains($sql,'GET_LOCK')||str_contains($sql,'RELEASE_LOCK')?1:null;}}
$GLOBALS['wpdb']=new TestDB();
$core=getenv('WPGPTVIBE_WP_CORE');
if(!$core)throw new RuntimeException('Set WPGPTVIBE_WP_CORE to a WordPress core checkout.');
require $core.'/wp-includes/class-wp-block-parser-block.php';
require $core.'/wp-includes/class-wp-block-parser-frame.php';
require $core.'/wp-includes/class-wp-block-parser.php';
// Use the actual WordPress block serialization functions rather than a mock parser.
require $core.'/wp-includes/blocks.php';
function check($condition,$message){if(!$condition)throw new RuntimeException('FAIL: '.$message);echo 'PASS: '.$message."\n";}
function rejects(callable $fn,$part){try{$fn();}catch(Throwable $e){check(str_contains($e->getMessage(),$part),$part);return;}throw new RuntimeException('FAIL: expected '.$part);}
$manager=dirname(__DIR__).'/includes/class-block-manager.php';
check(file_exists($manager),'editable block manager exists');
require $manager;
require dirname(__DIR__).'/includes/class-audit-log.php';
$content='<!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph --><p>First</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Second</p><!-- /wp:paragraph --></div><!-- /wp:group -->';
$GLOBALS['posts'][1]=new WP_Post(1,$content);
$initial=WPGPTVibe_Block_Manager::get(1);
check($initial['hash']===hash('sha256',$content),'content hash represents saved page');
rejects(fn()=>WPGPTVibe_Block_Manager::change(1,str_repeat('a',64),[['type'=>'duplicate','path'=>[0,0]]],false),'newer edits');
$preview=WPGPTVibe_Block_Manager::preview(1,$initial['hash'],[['type'=>'duplicate','path'=>[0,0]]]);
check($GLOBALS['posts'][1]->post_content===$content,'preview does not write content');
check(count($preview['blocks'][0]['innerBlocks'])===3,'nested section duplication');
$result=WPGPTVibe_Block_Manager::change(1,$initial['hash'],[['type'=>'move','path'=>[0,0],'to'=>1]],false);
check(strpos($GLOBALS['posts'][1]->post_content,'Second')<strpos($GLOBALS['posts'][1]->post_content,'First'),'move changes block order');
$parsed=parse_blocks($GLOBALS['posts'][1]->post_content);
check(count($parsed[0]['innerBlocks'])===2,'nested serialization preserves child markers');
rejects(fn()=>WPGPTVibe_Block_Manager::change(1,$result['hash'],[['type'=>'remove','path'=>[0,0]]],false),'confirm removal');
$GLOBALS['posts'][1]->post_status='publish';
rejects(fn()=>WPGPTVibe_Block_Manager::change(1,$result['hash'],[['type'=>'duplicate','path'=>[0,0]]],false),'published');
$GLOBALS['posts'][1]->post_status='draft';$GLOBALS['no_revisions']=true;
rejects(fn()=>WPGPTVibe_Block_Manager::change(1,$result['hash'],[['type'=>'duplicate','path'=>[0,0]]],false),'revisions');
$GLOBALS['no_revisions']=false;$GLOBALS['meta'][1]['_elementor_edit_mode']='builder';
rejects(fn()=>WPGPTVibe_Block_Manager::get(1),'unsupported builder');
$GLOBALS['meta'][1]=[];$GLOBALS['deny']=true;
rejects(fn()=>WPGPTVibe_Block_Manager::get(1),'permission');
$GLOBALS['deny']=false;

$patterns=dirname(__DIR__).'/includes/class-patterns.php';
check(file_exists($patterns),'editable patterns exist');
require $patterns;
check(count(WPGPTVibe_Patterns::catalog())===5,'five editable starter patterns');
rejects(fn()=>WPGPTVibe_Patterns::attributes('wpgptvibe/text',['unknown'=>1]),'Unsupported attribute');

<?php
// Run against an installed disposable WordPress with MySQL/InnoDB. Never run on a live site.
if(!getenv('WPGPTVIBE_TEST_SITE')){throw new RuntimeException('Set WPGPTVIBE_TEST_SITE only for a disposable test site.');}
require getenv('WPGPTVIBE_WP_CORE').'/wp-load.php';
require_once ABSPATH.'wp-admin/includes/plugin.php';
activate_plugin('wpgptvibe-bridge/wpgptvibe-bridge.php');
wp_set_current_user(1);
function verify($value,$message){if(!$value)throw new RuntimeException('FAIL: '.$message);echo 'PASS: '.$message."\n";}
WPGPTVibe_Auth::activate();$capabilities=WPGPTVibe_Auth::capabilities();foreach(['read_content','manage_content','edit_layout','restore_content','publish_content'] as $cap){$capabilities[$cap]=true;}WPGPTVibe_Auth::update_capabilities($capabilities);
$identity=['user_id'=>1,'scopes'=>WPGPTVibe_OAuth::SCOPES,'client_id'=>'integration'];
$created=WPGPTVibe_MCP_Tools::execute('create_page',['title'=>'Editable integration page','pattern'=>'hero','operation_id'=>'integration-create'],$identity);
verify(get_post($created['id'])->post_status==='draft','real WordPress draft created');
verify(parse_blocks(get_post($created['id'])->post_content)[0]['blockName']==='wpgptvibe/section','saved page uses native block serialization');
$replayed=WPGPTVibe_MCP_Tools::execute('create_page',['title'=>'Editable integration page','pattern'=>'hero','operation_id'=>'integration-create'],$identity);verify($replayed['id']===$created['id'],'real storage returns original create result');
$changed=WPGPTVibe_MCP_Tools::execute('apply_page_changes',['id'=>$created['id'],'expected_hash'=>$created['hash'],'operations'=>[['type'=>'update','path'=>[0,0],'attributes'=>['heading'=>'Human editable headline']]],'operation_id'=>'integration-edit'],$identity);
verify($changed['blocks'][0]['innerBlocks'][0]['attrs']['heading']==='Human editable headline','real nested page edit saves');
$html=do_blocks(get_post($created['id'])->post_content);verify(str_contains($html,'Human editable headline'),'frontend render reflects visual text edit');
verify(count(wp_get_post_revisions($created['id']))>0,'real WordPress revision saved');
$client=WPGPTVibe_OAuth::register_client(['redirect_uris'=>['https://chatgpt.com/connector_platform_oauth_redirect']]);$verifier=str_repeat('x',64);
$p=['client_id'=>$client['client_id'],'redirect_uri'=>$client['redirect_uris'][0],'response_type'=>'code','state'=>'integration-state','scope'=>'site:read pages:read pages:write layout:write','resource'=>WPGPTVibe_OAuth::resource(),'code_challenge'=>rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'='),'code_challenge_method'=>'S256'];
$code=WPGPTVibe_OAuth::issue_code($p,1);$token=WPGPTVibe_OAuth::exchange(['grant_type'=>'authorization_code','client_id'=>$p['client_id'],'redirect_uri'=>$p['redirect_uri'],'code'=>$code,'code_verifier'=>$verifier,'resource'=>$p['resource']]);
verify($token['status']===200,'real OAuth storage exchanges PKCE code');
verify(WPGPTVibe_OAuth::identity($token['body']['access_token'])['user_id']===1,'real token authenticates WordPress administrator');
$rpc=WPGPTVibe_MCP::dispatch(['jsonrpc'=>'2.0','id'=>1,'method'=>'tools/call','params'=>['name'=>'get_page_blocks','arguments'=>['id'=>$created['id']]]],WPGPTVibe_OAuth::identity($token['body']['access_token']));verify(empty($rpc['result']['isError']),'authenticated MCP tool reads real page');
$rest=rest_do_request(new WP_REST_Request('GET','/wpgptvibe/v1/oauth/resource'));verify($rest->get_status()===200&&$rest->get_data()['resource']===WPGPTVibe_OAuth::resource(),'WordPress REST discovery route works');

file_put_contents('/tmp/wpgptvibe-test-page',(string)$created['id']);
$shared=WPGPTVibe_MCP_Tools::execute('create_shared_pattern',['title'=>'Shared CTA','blocks'=>[['name'=>'wpgptvibe/text','attributes'=>['heading'=>'Shared original','body'=>'Shared content']]],'operation_id'=>'integration-shared-create'],$identity);
$linked=WPGPTVibe_MCP_Tools::execute('apply_page_changes',['id'=>$created['id'],'expected_hash'=>$changed['hash'],'operations'=>[['type'=>'insert','path'=>[1],'block'=>['name'=>'core/block','attributes'=>['ref'=>$shared['id']]]]],'operation_id'=>'integration-shared-insert'],$identity);
$impact=WPGPTVibe_Shared_Patterns::impact($shared['id']);verify(in_array($created['id'],array_column($impact['items'],'id'),true),'shared section reports affected page');
try { WPGPTVibe_Block_Manager::change($shared['id'],$shared['hash'],[['type'=>'update','path'=>[0],'attributes'=>['heading'=>'Unsafe shared change']]],true,$impact['hash']); throw new RuntimeException('Expected denial'); } catch(RuntimeException $e) { verify(str_contains($e->getMessage(),'Manual review'),'existing shared sections require native WordPress editing'); }
wp_update_post(['ID'=>$shared['id'],'post_content'=>'<!-- wp:wpgptvibe/text {"heading":"Human reviewed change","body":"Shared content"} /-->']);
verify(str_contains(do_blocks(get_post($created['id'])->post_content),'Human reviewed change'),'native WordPress shared edits still render');
$a=WPGPTVibe_Shared_Patterns::create('Cycle A',[['name'=>'wpgptvibe/text','attributes'=>['heading'=>'A']]]);
$b=WPGPTVibe_Shared_Patterns::create('Cycle B',[['name'=>'wpgptvibe/text','attributes'=>['heading'=>'B']]]);
wp_update_post(['ID'=>$a['id'],'post_content'=>'<!-- wp:block {"ref":'.$b['id'].'} /-->']);
wp_update_post(['ID'=>$b['id'],'post_content'=>'<!-- wp:block {"ref":'.$a['id'].'} /-->']);
try { WPGPTVibe_Block_Manager::preview($created['id'],WPGPTVibe_Block_Manager::get($created['id'])['hash'],[['type'=>'insert','path'=>[0],'block'=>['name'=>'core/block','attributes'=>['ref'=>$a['id']]]]]); throw new RuntimeException('Expected recursive rejection'); } catch(RuntimeException $e) { verify(str_contains($e->getMessage(),'recursive'),'draft preview rejects transitive reference cycles'); }
file_put_contents('/tmp/wpgptvibe-test-oauth.json',wp_json_encode(['parameters'=>$p,'verifier'=>$verifier]));

file_put_contents('/tmp/wpgptvibe-test-identity.json',wp_json_encode(['access_token'=>$token['body']['access_token']]));

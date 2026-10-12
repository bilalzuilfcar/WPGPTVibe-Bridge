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

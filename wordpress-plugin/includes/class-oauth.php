<?php
if (!defined('ABSPATH')) { exit; }

final class WPGPTVibe_OAuth {
    public const SCOPES=['site:read','pages:read','pages:write','layout:write','pages:publish','media:write','seo:write','theme:read','theme:write','theme:publish','maintenance:write'];
    public static function resource(): string {return rest_url('wpgptvibe/v1/mcp');}
    public static function issuer(): string {return rtrim(home_url('/'),'/');}
    public static function init(): void {
        add_action('rest_api_init',[self::class,'routes']);
        add_action('parse_request',[self::class,'well_known'],0);
        add_action('admin_post_wpgptvibe_authorize',[self::class,'authorize']);
        add_action('admin_post_nopriv_wpgptvibe_authorize',[self::class,'authorize']);
        add_action('admin_post_wpgptvibe_disconnect',[self::class,'disconnect_action']);
    }
    public static function routes(): void {
        foreach(['metadata'=>'metadata','resource'=>'resource_metadata'] as $path=>$method){register_rest_route('wpgptvibe/v1','/oauth/'.$path,['methods'=>'GET','callback'=>[self::class,$method],'permission_callback'=>'__return_true']);}
        foreach(['register'=>'registration_route','token'=>'token_route','revoke'=>'revoke_route'] as $path=>$method){register_rest_route('wpgptvibe/v1','/oauth/'.$path,['methods'=>'POST','callback'=>[self::class,$method],'permission_callback'=>'__return_true']);}
    }
    public static function metadata(): array {return [
        'issuer'=>self::issuer(),'authorization_endpoint'=>admin_url('admin-post.php?action=wpgptvibe_authorize'),
        'token_endpoint'=>rest_url('wpgptvibe/v1/oauth/token'),'registration_endpoint'=>rest_url('wpgptvibe/v1/oauth/register'),
        'revocation_endpoint'=>rest_url('wpgptvibe/v1/oauth/revoke'),'response_types_supported'=>['code'],
        'grant_types_supported'=>['authorization_code','refresh_token'],'token_endpoint_auth_methods_supported'=>['none'],
        'code_challenge_methods_supported'=>['S256'],'scopes_supported'=>self::SCOPES,
        'authorization_response_iss_parameter_supported'=>true
    ];}
    public static function resource_metadata(): array {return ['resource'=>self::resource(),'authorization_servers'=>[self::issuer()],'scopes_supported'=>self::SCOPES,'bearer_methods_supported'=>['header']];}
    public static function well_known($wp): void {
        $path=wp_parse_url((string)($_SERVER['REQUEST_URI']??''),PHP_URL_PATH);
        $base=rtrim((string)wp_parse_url(self::issuer(),PHP_URL_PATH),'/');
        if($path===$base.'/.well-known/oauth-authorization-server'){$data=self::metadata();}
        elseif($path===$base.'/.well-known/oauth-protected-resource'||$path==='/.well-known/oauth-protected-resource'.wp_parse_url(self::resource(),PHP_URL_PATH)){$data=self::resource_metadata();}
        elseif($base!==''&&$path==='/.well-known/oauth-authorization-server'.$base){$data=self::metadata();}
        else{return;}
        if(!is_ssl()){status_header(403);exit;}
        nocache_headers();header('Content-Type: application/json; charset=utf-8');echo wp_json_encode($data);exit;
    }
    public static function rate(string $bucket,int $limit=30): void {
        $key='wpgv_limit_'.hash('sha256',$bucket.'|'.($_SERVER['REMOTE_ADDR']??'unknown'));
        $n=(int)get_transient($key);if($n>=$limit){throw new RuntimeException('Too many connection requests. Retry in a minute.');}set_transient($key,$n+1,60);
    }
    public static function register_client(array $input): array {
        $uris=$input['redirect_uris']??[];
        if(!is_array($uris)||count($uris)!==1||!is_string($uris[0])){throw new RuntimeException('Provide one exact callback URL.');}
        $allowed=get_option('wpgptvibe_oauth_redirects',['https://chatgpt.com/connector_platform_oauth_redirect']);
        if(!in_array($uris[0],$allowed,true)||!str_starts_with($uris[0],'https://')){throw new RuntimeException('The callback URL is not approved in WordPress connection settings.');}
        if(($input['token_endpoint_auth_method']??'none')!=='none'){throw new RuntimeException('This connection supports public clients with PKCE.');}
        if(isset($input['grant_types'])&&(!is_array($input['grant_types'])||array_diff($input['grant_types'],['authorization_code','refresh_token']))){throw new RuntimeException('Unsupported grant type.');}
        $clients=get_option('wpgptvibe_oauth_clients',[]);
        if(count($clients)>=100){throw new RuntimeException('Client limit reached. Remove unused connections first.');}
        $id='wpgv_'.bin2hex(random_bytes(24));
        $clients[$id]=['client_id'=>$id,'redirect_uri'=>$uris[0],'scope'=>implode(' ',self::SCOPES),'client_secret'=>null,'created'=>time()];
        update_option('wpgptvibe_oauth_clients',$clients,false);
        return ['client_id'=>$id,'client_id_issued_at'=>time(),'redirect_uris'=>$uris,'token_endpoint_auth_method'=>'none','grant_types'=>['authorization_code','refresh_token'],'response_types'=>['code']];
    }
    public static function validate_authorization(array $p): void {
        $s=new WPGPTVibe_OAuth_Storage();$client=$s->getClientDetails($p['client_id']??'');
        if(!$client){throw new RuntimeException('Unknown OAuth client. Reconnect ChatGPT.');}
        if(($p['redirect_uri']??'')!==$client['redirect_uri']){throw new RuntimeException('The callback URL does not match the registered client.');}
        if(($p['resource']??'')!==self::resource()){throw new RuntimeException('OAuth resource does not match this website.');}
        if(($p['response_type']??'')!=='code'||empty($p['state'])||strlen((string)$p['state'])>2048){throw new RuntimeException('Authorization code flow and state are required.');}
        if(($p['code_challenge_method']??'')!=='S256'||!preg_match('/^[A-Za-z0-9_-]{43}$/',(string)($p['code_challenge']??''))){throw new RuntimeException('PKCE S256 is required.');}
        if(!$s->scopeExists($p['scope']??'')){throw new RuntimeException('Unsupported OAuth scope.');}
    }
    public static function issue_code(array $p,int $user_id): string {
        self::validate_authorization($p);
        if(!user_can(get_userdata($user_id),'manage_options')){throw new RuntimeException('Only a WordPress administrator can authorize this connection.');}
        $s=new WPGPTVibe_OAuth_Storage();$s->context=['resource'=>self::resource(),'issuer'=>self::issuer(),'epoch'=>(string)get_option('wpgptvibe_oauth_epoch','0'),'family'=>bin2hex(random_bytes(16))];
        $type=new WPGPTVibeVendor\OAuth2\ResponseType\AuthorizationCode($s,['auth_code_lifetime'=>120]);
        return $type->createAuthorizationCode($p['client_id'],$user_id,$p['redirect_uri'],$p['scope'],$p['code_challenge'],'S256');
    }
    public static function exchange(array $p): array {
        $s=new WPGPTVibe_OAuth_Storage();$grant=$p['grant_type']??'';
        if(($p['resource']??'')!==self::resource()){return ['status'=>400,'body'=>['error'=>'invalid_target','error_description'=>'Incorrect MCP resource.']];}
        if(!in_array($grant,['authorization_code','refresh_token'],true)){return ['status'=>400,'body'=>['error'=>'unsupported_grant_type']];}
        $secret=$p[$grant==='authorization_code'?'code':'refresh_token']??'';
        if(!is_string($secret)||strlen($secret)>256){return ['status'=>400,'body'=>['error'=>'invalid_grant']];}
        global $wpdb;$lock='wpgv_exchange_'.substr(hash('sha256',$secret),0,50);
        if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 3)',$lock))!==1){return ['status'=>409,'body'=>['error'=>'temporarily_unavailable']];}
        try{
            $record=$grant==='authorization_code'?$s->getAuthorizationCode($secret):$s->getRefreshToken($secret);
            if(!$record||($record['resource']??'')!==self::resource()||($record['issuer']??'')!==self::issuer()||($record['epoch']??'')!==(string)get_option('wpgptvibe_oauth_epoch','0')||get_transient('wpgv_revoked_'.($record['family']??''))||!user_can(get_userdata((int)$record['user_id']),'manage_options')){
                return ['status'=>400,'body'=>['error'=>'invalid_grant','error_description'=>'Authorization is invalid or revoked.']];
            }
            $s->context=array_intersect_key($record,array_flip(['resource','issuer','epoch','family']));
            $server=new WPGPTVibeVendor\OAuth2\Server($s,['access_lifetime'=>3600,'refresh_token_lifetime'=>604800,'allow_implicit'=>false,'enforce_state'=>true]);
            $server->addGrantType(new WPGPTVibeVendor\OAuth2\GrantType\AuthorizationCode($s));
            $server->addGrantType(new WPGPTVibeVendor\OAuth2\GrantType\RefreshToken($s,['always_issue_new_refresh_token'=>true,'unset_refresh_token_after_use'=>true]));
            $request=new WPGPTVibeVendor\OAuth2\Request([],$p,[],[],[],['REQUEST_METHOD'=>'POST']);
            $response=new WPGPTVibeVendor\OAuth2\Response();$server->handleTokenRequest($request,$response);
            return ['status'=>$response->getStatusCode(),'body'=>$response->getParameters()];
        }finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
    }
    public static function identity(string $token): array {
        if($token===''||strlen($token)>256){throw new RuntimeException('Missing access token.');}
        $s=new WPGPTVibe_OAuth_Storage();$r=$s->getAccessToken($token);
        if(!$r){throw new RuntimeException('Access token is expired or invalid. Reconnect ChatGPT.');}
        if(($r['epoch']??'')!==(string)get_option('wpgptvibe_oauth_epoch','0')||get_transient('wpgv_revoked_'.($r['family']??''))){throw new RuntimeException('Connection has been revoked.');}
        if((int)$r['expires']<=time()){throw new RuntimeException('Access token has expired.');}
        if(($r['resource']??'')!==self::resource()||($r['issuer']??'')!==self::issuer()){throw new RuntimeException('Access token has the wrong resource.');}
        if(!user_can(get_userdata((int)$r['user_id']),'manage_options')){throw new RuntimeException('The authorized administrator no longer has access.');}
        return ['user_id'=>(int)$r['user_id'],'scopes'=>explode(' ',(string)$r['scope']),'client_id'=>$r['client_id']];
    }
    private static function response(array $result): WP_REST_Response {
        $r=new WP_REST_Response($result['body'],$result['status']);$r->header('Cache-Control','no-store');$r->header('Pragma','no-cache');return $r;
    }
    public static function registration_route(WP_REST_Request $r): WP_REST_Response {
        try{if(!is_ssl()){throw new RuntimeException('HTTPS is required.');}self::rate('register',10);return self::response(['status'=>201,'body'=>self::register_client($r->get_json_params()?:[])]);}
        catch(Throwable $e){return self::response(['status'=>400,'body'=>['error'=>'invalid_client_metadata','error_description'=>$e->getMessage()]]);}
    }
    public static function token_route(WP_REST_Request $r): WP_REST_Response {
        try{if(!is_ssl()){throw new RuntimeException('HTTPS is required.');}self::rate('token',60);if(strlen($r->get_body())>16384){throw new RuntimeException('Token request is too large.');}return self::response(self::exchange($r->get_body_params()));}
        catch(Throwable $e){return self::response(['status'=>400,'body'=>['error'=>'invalid_request','error_description'=>'Unable to exchange authorization. Check connection settings.']]);}
    }
    public static function revoke_route(WP_REST_Request $r): WP_REST_Response {
        if(!is_ssl()){return self::response(['status'=>403,'body'=>['error'=>'HTTPS required']]);}
        try{self::rate('revoke',30);$s=new WPGPTVibe_OAuth_Storage();$token=(string)$r->get_param('token');if(strlen($token)>256){throw new RuntimeException('Invalid token.');}$record=$s->getAccessToken($token)?:$s->getRefreshToken($token);
            if($record&&($record['client_id']??'')===$r->get_param('client_id')){set_transient('wpgv_revoked_'.$record['family'],true,604800);$s->unsetAccessToken($token);$s->unsetRefreshToken($token);}
            return self::response(['status'=>200,'body'=>new stdClass()]);
        }catch(Throwable $e){return self::response(['status'=>400,'body'=>['error'=>'invalid_request']]);}
    }
    public static function authorize(): void {
        if(!is_ssl()){wp_die('HTTPS is required.', '', ['response'=>403]);}
        $p=[];foreach(['client_id','redirect_uri','response_type','state','scope','resource','code_challenge','code_challenge_method'] as $key){$input=$_SERVER['REQUEST_METHOD']==='POST'?$_POST:$_GET;$value=$input[$key]??'';if(!is_string($value)){wp_die('Invalid authorization parameter.');}$p[$key]=wp_unslash($value);}
        if($p['scope']===''){$p['scope']='site:read pages:read';}
        try{self::validate_authorization($p);}catch(Throwable $e){wp_die(esc_html($e->getMessage()),'Connection error',['response'=>400]);}
        if(!is_user_logged_in()){auth_redirect();exit;}
        if(!current_user_can('manage_options')){wp_die('Only an administrator can connect this website.','',['response'=>403]);}
        $nonce_action='wpgptvibe_consent_'.hash('sha256',wp_json_encode($p));
        if($_SERVER['REQUEST_METHOD']==='POST'){
            check_admin_referer($nonce_action);
            $query=['state'=>$p['state'],'iss'=>self::issuer()];
            if(($_POST['decision']??'')==='allow'){$query['code']=self::issue_code($p,get_current_user_id());WPGPTVibe_Audit_Log::record('oauth_connect','user:'.get_current_user_id(),'success');}
            else{$query['error']='access_denied';}
            // Exact registered URL already checked. Do not use a generic open redirect.
            wp_redirect(add_query_arg($query,$p['redirect_uri']));exit;
        }
        nocache_headers();header('X-Frame-Options: DENY');header("Content-Security-Policy: frame-ancestors 'none'");
        $html='<h1>Connect this website to ChatGPT</h1><p>Website: <strong>'.esc_html(get_bloginfo('name')).'</strong></p><p>Requested permissions: '.esc_html(str_replace(' ', ', ', $p['scope'])).'</p><p>Callback: <code>'.esc_html($p['redirect_uri']).'</code></p><p>WordPress permissions remain enforced. You can disconnect in Tools → WPGPTVibe.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="wpgptvibe_authorize">';
        foreach($p as $key=>$value){$html.='<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr($value).'">';}
        $html.=wp_nonce_field($nonce_action,'_wpnonce',true,false).'<button name="decision" value="allow">Allow connection</button> <button name="decision" value="deny">Cancel</button></form>';
        wp_die($html,'Connect WPGPTVibe',['response'=>200]);
    }
    public static function disconnect(): void {update_option('wpgptvibe_oauth_epoch',bin2hex(random_bytes(16)),false);WPGPTVibe_Audit_Log::record('oauth_disconnect','all_connections','success');}
    public static function disconnect_action(): void {if(!current_user_can('manage_options')){wp_die('Insufficient permissions.');}check_admin_referer('wpgptvibe_disconnect');self::disconnect();wp_safe_redirect(admin_url('tools.php?page=wpgptvibe'));exit;}
}

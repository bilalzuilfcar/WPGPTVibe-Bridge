<?php
if (!defined('ABSPATH')) { exit; }

/** Stateless Streamable HTTP MCP, with JSON responses and OAuth-only tool execution. */
final class WPGPTVibe_MCP {
    public static function init(): void {add_action('rest_api_init',[self::class,'routes']);}
    public static function routes(): void {
        register_rest_route('wpgptvibe/v1','/mcp',['methods'=>'GET,POST,DELETE','callback'=>[self::class,'handle'],'permission_callback'=>'__return_true']);
    }
    public static function dispatch(array $message,array $identity): ?array {
        $id=$message['id']??null;
        if(($message['jsonrpc']??'')!=='2.0'||!isset($message['method'])||!is_string($message['method'])||isset($message[0])||($id!==null&&!is_string($id)&&!is_int($id))){return ['jsonrpc'=>'2.0','id'=>null,'error'=>['code'=>-32600,'message'=>'Invalid Request']];}
        $method=$message['method'];$params=$message['params']??[];
        if(!is_array($params)){return ['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>-32602,'message'=>'Invalid params']];}
        if(!array_key_exists('id',$message)){return null;}
        $result=null;
        if($method==='initialize'){
            $versions=['2025-11-25','2025-06-18','2025-03-26'];$requested=$params['protocolVersion']??'';
            $result=['protocolVersion'=>in_array($requested,$versions,true)?$requested:$versions[0],'capabilities'=>['tools'=>['listChanged'=>false]],'serverInfo'=>['name'=>'wpgptvibe-wordpress','version'=>WPGPTVIBE_VERSION],
                'instructions'=>'Manage only this connected WordPress website. Read the page and hash before editing. Use editable blocks and patterns for ordinary page content. Preview changes before applying them. Save drafts by default. Ask the user before publishing or changing live content. Reuse operation_id only with identical arguments after a retry. Unsupported builders must not be converted. A theme preview is separate from a page preview.'];
        }elseif($method==='ping'){$result=new stdClass();}
        elseif($method==='tools/list'){$result=['tools'=>WPGPTVibe_MCP_Tools::catalog()];}
        elseif($method==='tools/call'){
            if(!is_string($params['name']??null)||!is_array($params['arguments']??[])){return ['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>-32602,'message'=>'Invalid tool call']];}
            try{$data=WPGPTVibe_MCP_Tools::execute($params['name'],$params['arguments']??[],$identity);$result=['content'=>[['type'=>'text','text'=>wp_json_encode($data)]],'structuredContent'=>['result'=>$data]];}
            catch(Throwable $e){$result=['isError'=>true,'content'=>[['type'=>'text','text'=>$e instanceof RuntimeException?$e->getMessage():'Unable to complete this WordPress operation. Check the connection and permissions.']]];}
        }else{return ['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>-32601,'message'=>'Method not found']];}
        return ['jsonrpc'=>'2.0','id'=>$id,'result'=>$result];
    }
    public static function handle(WP_REST_Request $request): WP_REST_Response {
        $headers=['Cache-Control'=>'no-store','X-Content-Type-Options'=>'nosniff'];
        if(!is_ssl()){return new WP_REST_Response(['error'=>'HTTPS required'],403,$headers);}
        $origin=$request->get_header('origin');
        if($origin&&rtrim($origin,'/')!==rtrim(home_url('/'),'/')&&$origin!=='https://chatgpt.com'){return new WP_REST_Response(['error'=>'Origin not permitted'],403,$headers);}
        try{WPGPTVibe_OAuth::rate('mcp',120);$authorization=$request->get_header('authorization');if(!preg_match('/^Bearer ([A-Za-z0-9_-]+)$/',$authorization,$matches)){throw new RuntimeException('Authorization required.');}$identity=WPGPTVibe_OAuth::identity($matches[1]);}
        catch(Throwable $e){$headers['WWW-Authenticate']='Bearer resource_metadata="'.rest_url('wpgptvibe/v1/oauth/resource').'", error="invalid_token"';return new WP_REST_Response(['error'=>'Authentication required. Reconnect this website.'],401,$headers);}
        if($request->get_method()!=='POST'){return new WP_REST_Response(['error'=>'Use POST. This stateless MCP endpoint has no SSE session.'],405,array_merge($headers,['Allow'=>'POST']));}
        $version=$request->get_header('mcp-protocol-version');if($version&&!in_array($version,['2025-11-25','2025-06-18','2025-03-26'],true)){return new WP_REST_Response(['error'=>'Unsupported MCP protocol version'],400,$headers);}
        if(strlen($request->get_body())>2097152){return new WP_REST_Response(['error'=>'Request exceeds 2 MB'],413,$headers);}
        if(!str_contains(strtolower($request->get_header('content-type')),'application/json')){return new WP_REST_Response(['error'=>'Content-Type must be application/json'],415,$headers);}
        $message=json_decode($request->get_body(),true,64);
        if(!is_array($message)||json_last_error()!==JSON_ERROR_NONE){return new WP_REST_Response(['jsonrpc'=>'2.0','id'=>null,'error'=>['code'=>-32700,'message'=>'Parse error']],400,$headers);}
        $original=get_current_user_id();wp_set_current_user($identity['user_id']);
        try{$response=self::dispatch($message,$identity);return new WP_REST_Response($response,$response===null?202:200,$headers);}
        finally{wp_set_current_user($original);}
    }
}

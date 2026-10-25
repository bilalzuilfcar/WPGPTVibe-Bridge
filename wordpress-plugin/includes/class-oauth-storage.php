<?php
if (!defined('ABSPATH')) { exit; }

/** Hash-only keys, bounded expiring credentials, and connection revocation. */
final class WPGPTVibe_OAuth_Storage implements
    WPGPTVibeVendor\OAuth2\Storage\AuthorizationCodeInterface,
    WPGPTVibeVendor\OAuth2\Storage\AccessTokenInterface,
    WPGPTVibeVendor\OAuth2\Storage\RefreshTokenInterface,
    WPGPTVibeVendor\OAuth2\Storage\ClientCredentialsInterface,
    WPGPTVibeVendor\OAuth2\Storage\ScopeInterface {
    public array $context=[];
    private function key($kind,$token): string {return 'wpgv_oauth_'.$kind.'_'.hash('sha256',(string)$token);}
    private function get($kind,$token){$data=get_transient($this->key($kind,$token));return is_array($data)?$data:null;}
    private function put($kind,$token,array $data): void {set_transient($this->key($kind,$token),$data,max(1,(int)$data['expires']-time()));}
    public function getAuthorizationCode($code){return $this->get('code',$code);}
    public function setAuthorizationCode($code,$client_id,$user_id,$redirect_uri,$expires,$scope=null,$id_token=null,$code_challenge=null,$code_challenge_method=null){
        $this->put('code',$code,array_merge($this->context,compact('client_id','user_id','redirect_uri','expires','scope','code_challenge','code_challenge_method')));
    }
    public function expireAuthorizationCode($code){delete_transient($this->key('code',$code));}
    public function getAccessToken($token){return $this->get('access',$token);}
    public function setAccessToken($token,$client_id,$user_id,$expires,$scope=null){$this->put('access',$token,array_merge($this->context,compact('client_id','user_id','expires','scope')));}
    public function unsetAccessToken($token){delete_transient($this->key('access',$token));return true;}
    public function getRefreshToken($token){$data=$this->get('refresh',$token);return $data?array_merge($data,['refresh_token'=>$token]):null;}
    public function setRefreshToken($token,$client_id,$user_id,$expires,$scope=null){$this->put('refresh',$token,array_merge($this->context,compact('client_id','user_id','expires','scope')));}
    public function unsetRefreshToken($token){delete_transient($this->key('refresh',$token));}
    public function getClientDetails($client_id){$clients=get_option('wpgptvibe_oauth_clients',[]);return $clients[$client_id]??false;}
    public function getClientScope($client_id){$c=$this->getClientDetails($client_id);return $c['scope']??false;}
    public function checkRestrictedGrantType($client_id,$grant_type){return $this->getClientDetails($client_id)&&in_array($grant_type,['authorization_code','refresh_token'],true);}
    public function checkClientCredentials($client_id,$client_secret=null){return $this->isPublicClient($client_id)&&($client_secret===null||$client_secret==='');}
    public function isPublicClient($client_id){return (bool)$this->getClientDetails($client_id);}
    public function scopeExists($scope){return $scope!==''&&!array_diff(explode(' ',$scope),WPGPTVibe_OAuth::SCOPES);}
    public function getDefaultScope($client_id=null){return 'site:read pages:read';}
}

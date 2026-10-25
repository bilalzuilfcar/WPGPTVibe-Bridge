<?php
if (!defined('ABSPATH')) { exit; }
final class WPGPTVibe_Operation_Store {
    public static function init(): void {add_action('wpgptvibe_cleanup_operations',[self::class,'cleanup']);if(!wp_next_scheduled('wpgptvibe_cleanup_operations')){wp_schedule_event(time()+3600,'daily','wpgptvibe_cleanup_operations');}}
    public static function cleanup(): void {global $wpdb;$keys=$wpdb->get_col($wpdb->prepare('SELECT option_name FROM '.$wpdb->options.' WHERE option_name LIKE %s ORDER BY option_id ASC LIMIT 1000',$wpdb->esc_like('wpgv_operation_').'%'));foreach($keys as $key){$record=get_option($key);if(is_array($record)&&($record['expires']??0)<time()){delete_option($key);}}}
    public static function run(string $id,array $identity,array $request,callable $operation) {
        if(!preg_match('/^[A-Za-z0-9_-]{8,100}$/',$id)){throw new RuntimeException('Provide an operation_id with 8–100 letters, numbers, underscores or hyphens.');}
        $key='wpgv_operation_'.hash('sha256',$identity['user_id'].'|'.$identity['client_id'].'|'.$id);
        $fingerprint=hash('sha256',wp_json_encode($request));global $wpdb;$lock=substr($key,0,64);
        if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 3)',$lock))!==1){throw new RuntimeException('Operation is still running. Retry the same operation_id.');}
        try{
            $saved=get_option($key);
            if(is_array($saved)){
                if(($saved['expires']??0)<time()){throw new RuntimeException('Operation history has expired. Inspect the current result and use a new operation_id.');}
                if(!hash_equals($saved['fingerprint'],$fingerprint)){throw new RuntimeException('This operation_id belongs to a different request.');}
                if(($saved['state']??'')!=='complete'){throw new RuntimeException('The previous operation has an uncertain result. Inspect the page before retrying with a new operation_id.');}
                return $saved['result'];
            }
            update_option($key,['fingerprint'=>$fingerprint,'state'=>'started','expires'=>time()+604800],false);
            try{$result=$operation();}catch(Throwable $e){
                // Preserve the claim: a manager may fail after committing part of its work.
                throw $e;
            }
            update_option($key,['fingerprint'=>$fingerprint,'state'=>'complete','result'=>$result,'expires'=>time()+604800],false);
            return $result;
        }finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
    }
}

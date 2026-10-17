<?php
if (!defined('ABSPATH')) { exit; }
spl_autoload_register(static function(string $class): void {
    $prefix='WPGPTVibeVendor\\OAuth2\\';
    if(str_starts_with($class,$prefix)){
        $path=__DIR__.'/../vendor/oauth2-server-php/src/OAuth2/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
        if(is_file($path)){require_once $path;}
    }
});

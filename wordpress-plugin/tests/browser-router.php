<?php
$core=getenv('WPGPTVIBE_WP_CORE');
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path&&is_file($core.$path)){return false;}
require $core.'/index.php';

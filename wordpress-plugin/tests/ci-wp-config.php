<?php
// Disposable CI database only; never use as a production configuration.
define('DB_NAME','wpgptvibe_test');
define('DB_USER','root');
define('DB_PASSWORD','test-only-password');
define('DB_HOST','127.0.0.1:3306');
define('DB_CHARSET','utf8mb4');
define('DB_COLLATE','');
define('WP_HOME','https://example.test');
define('WP_SITEURL','https://example.test');
define('WP_DEBUG',true);
define('WP_DEBUG_DISPLAY',true);
define('WP_DISABLE_FATAL_ERROR_HANDLER',true);
define('WP_DEBUG_LOG',true);
$_SERVER['HTTPS']='on';
$table_prefix='wp_';
if(!defined('ABSPATH'))define('ABSPATH',__DIR__.'/');
require_once ABSPATH.'wp-settings.php';

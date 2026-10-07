<?php
/**
 * Plugin Name: WPGPTVibe Bridge
 * Description: Secure WordPress REST bridge for WPGPTVibe MCP.
 * Version: 0.2.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: SPTIO Smart Solutions
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WPGPTVIBE_VERSION', '0.2.0');
define('WPGPTVIBE_FILE', __FILE__);
define('WPGPTVIBE_DIR', plugin_dir_path(__FILE__));
define('WPGPTVIBE_URL', plugin_dir_url(__FILE__));

require_once WPGPTVIBE_DIR . 'includes/class-audit-log.php';
require_once WPGPTVIBE_DIR . 'includes/class-auth.php';
require_once WPGPTVIBE_DIR . 'includes/class-request-guard.php';
require_once WPGPTVIBE_DIR . 'includes/class-theme-manager.php';
require_once WPGPTVIBE_DIR . 'includes/class-theme-operations.php';
require_once WPGPTVIBE_DIR . 'includes/class-content-manager.php';
require_once WPGPTVIBE_DIR . 'includes/class-meta-manager.php';
require_once WPGPTVIBE_DIR . 'includes/class-media-manager.php';
require_once WPGPTVIBE_DIR . 'includes/seo/interface-seo-provider.php';
require_once WPGPTVIBE_DIR . 'includes/seo/class-rank-math-provider.php';
require_once WPGPTVIBE_DIR . 'includes/seo/class-yoast-provider.php';
require_once WPGPTVIBE_DIR . 'includes/seo/class-generic-seo-provider.php';
require_once WPGPTVIBE_DIR . 'includes/class-seo-manager.php';
require_once WPGPTVIBE_DIR . 'includes/class-cache-manager.php';
require_once WPGPTVIBE_DIR . 'includes/class-wpcli-manager.php';
require_once WPGPTVIBE_DIR . 'includes/class-calculator-manager.php';
require_once WPGPTVIBE_DIR . 'includes/class-rest.php';
require_once WPGPTVIBE_DIR . 'admin/class-admin.php';

register_activation_hook(__FILE__, ['WPGPTVibe_Auth', 'activate']);

add_action('plugins_loaded', static function (): void {
    WPGPTVibe_REST::init();

    if (is_admin()) {
        WPGPTVibe_Admin::init();
    }
});

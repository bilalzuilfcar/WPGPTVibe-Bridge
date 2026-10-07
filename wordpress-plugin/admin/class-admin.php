<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WPGPTVibe_Admin {
    public static function init(): void {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_post_wpgptvibe_generate_token', [self::class, 'generate_token']);
        add_action('admin_post_wpgptvibe_revoke_token', [self::class, 'revoke_token']);
        add_action('admin_post_wpgptvibe_save_capabilities', [self::class, 'save_capabilities']);
        add_action('admin_post_wpgptvibe_save_meta_allowlist', [self::class, 'save_meta_allowlist']);
    }

    public static function menu(): void {
        add_management_page('WPGPTVibe', 'WPGPTVibe', 'manage_options', 'wpgptvibe', [self::class, 'render']);
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $token = get_transient('wpgptvibe_new_token_' . get_current_user_id());
        if ($token) {
            delete_transient('wpgptvibe_new_token_' . get_current_user_id());
        }

        $status = WPGPTVibe_Auth::token_status();
        $capabilities = WPGPTVibe_Auth::capabilities();
        $draft = WPGPTVibe_Theme_Manager::draft_status();
        $last = WPGPTVibe_Audit_Log::recent(1);
        $meta_allowlist = WPGPTVibe_Meta_Manager::allowlist();
        $wpcli = WPGPTVibe_WPCLI_Manager::status();
        $seo_provider = WPGPTVibe_SEO_Manager::provider()->name();
        $calculators = WPGPTVibe_Calculator_Manager::list();
        ?>
        <div class="wrap">
            <h1>WPGPTVibe</h1>
            <p>Secure WordPress bridge for the WPGPTVibe MCP server.</p>

            <?php if ($token): ?>
                <div class="notice notice-warning">
                    <p><strong>Copy this API token now. It will not be shown again:</strong></p>
                    <p><code style="user-select:all"><?php echo esc_html($token); ?></code></p>
                </div>
            <?php endif; ?>

            <table class="widefat striped" style="max-width:1000px;margin:20px 0">
                <tbody>
                    <tr><th>Connection Status</th><td><?php echo $status['configured'] ? 'API token configured' : 'API token not configured'; ?></td></tr>
                    <tr><th>Site ID</th><td><code><?php echo esc_html(WPGPTVibe_Auth::site_id()); ?></code></td></tr>
                    <tr><th>Site URL</th><td><?php echo esc_html(home_url('/')); ?></td></tr>
                    <tr><th>API Endpoint</th><td><code><?php echo esc_html(rest_url('wpgptvibe/v1/')); ?></code></td></tr>
                    <tr><th>API Token</th><td><?php echo $status['configured'] ? 'Configured (ends in ' . esc_html($status['hint']) . ')' : 'Not configured'; ?></td></tr>
                    <tr><th>Last Bridge Activity</th><td><?php echo !empty($last[0]['timestamp']) ? esc_html($last[0]['timestamp']) : 'None'; ?></td></tr>
                    <tr><th>Draft Theme</th><td><?php echo !empty($draft['exists']) ? esc_html($draft['stylesheet']) : 'None'; ?></td></tr>
                    <tr><th>SEO Provider</th><td><?php echo esc_html($seo_provider); ?></td></tr>
                    <tr><th>WP-CLI</th><td><?php echo !empty($wpcli['proc_open']) ? 'Wrapper available (binary: ' . esc_html($wpcli['binary']) . ')' : 'Unavailable (proc_open disabled)'; ?></td></tr>
                    <tr><th>Registered Calculators</th><td><?php echo esc_html((string) count($calculators)); ?></td></tr>
                    <tr><th>Plugin Version</th><td><?php echo esc_html(WPGPTVIBE_VERSION); ?></td></tr>
                </tbody>
            </table>

            <h2>API Token</h2>
            <p>Generate or rotate creates a new token and immediately invalidates the previous token.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:8px">
                <?php wp_nonce_field('wpgptvibe_generate_token'); ?>
                <input type="hidden" name="action" value="wpgptvibe_generate_token">
                <?php submit_button($status['configured'] ? 'Rotate API Key' : 'Generate API Key', 'primary', 'submit', false); ?>
            </form>
            <?php if ($status['configured']): ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block">
                    <?php wp_nonce_field('wpgptvibe_revoke_token'); ?>
                    <input type="hidden" name="action" value="wpgptvibe_revoke_token">
                    <?php submit_button('Revoke API Key', 'secondary', 'submit', false); ?>
                </form>
            <?php endif; ?>

            <h2>Permissions</h2>
            <p>Read and write capabilities can be enabled independently. High-risk actions also require explicit confirmation at the API layer.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('wpgptvibe_save_capabilities'); ?>
                <input type="hidden" name="action" value="wpgptvibe_save_capabilities">
                <table class="form-table">
                    <?php foreach (WPGPTVibe_Auth::CAPABILITIES as $capability): ?>
                        <tr>
                            <th><?php echo esc_html(ucwords(str_replace('_', ' ', $capability))); ?></th>
                            <td><label><input type="checkbox" name="capabilities[<?php echo esc_attr($capability); ?>]" value="1" <?php checked(!empty($capabilities[$capability])); ?>> Enabled</label></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <?php submit_button('Save Permissions'); ?>
            </form>

            <h2>Protected Meta Allowlist</h2>
            <p>Protected WordPress meta keys begin with <code>_</code>. WPGPTVibe blocks them unless explicitly listed here.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:1000px">
                <?php wp_nonce_field('wpgptvibe_save_meta_allowlist'); ?>
                <input type="hidden" name="action" value="wpgptvibe_save_meta_allowlist">
                <textarea name="meta_allowlist" rows="5" class="large-text code" placeholder="_custom_key&#10;_another_key"><?php echo esc_textarea(implode("\n", $meta_allowlist)); ?></textarea>
                <p class="description">One key per line or comma-separated. SEO plugin keys are managed by the SEO adapters and do not need to be added here.</p>
                <?php submit_button('Save Meta Allowlist', 'secondary'); ?>
            </form>

            <h2>Recent Audit Log</h2>
            <pre style="max-width:1000px;max-height:500px;overflow:auto;background:#fff;padding:16px;border:1px solid #ccd0d4"><?php echo esc_html(wp_json_encode(WPGPTVibe_Audit_Log::recent(50), JSON_PRETTY_PRINT)); ?></pre>
        </div>
        <?php
    }

    public static function generate_token(): void {
        self::guard('wpgptvibe_generate_token');
        $token = WPGPTVibe_Auth::generate_token();
        set_transient('wpgptvibe_new_token_' . get_current_user_id(), $token, 5 * MINUTE_IN_SECONDS);
        wp_safe_redirect(self::page_url());
        exit;
    }

    public static function revoke_token(): void {
        self::guard('wpgptvibe_revoke_token');
        WPGPTVibe_Auth::revoke_token();
        wp_safe_redirect(self::page_url());
        exit;
    }

    public static function save_capabilities(): void {
        self::guard('wpgptvibe_save_capabilities');
        $submitted = isset($_POST['capabilities']) && is_array($_POST['capabilities']) ? wp_unslash($_POST['capabilities']) : [];
        WPGPTVibe_Auth::update_capabilities($submitted);
        wp_safe_redirect(self::page_url());
        exit;
    }

    public static function save_meta_allowlist(): void {
        self::guard('wpgptvibe_save_meta_allowlist');
        $raw = isset($_POST['meta_allowlist']) ? sanitize_textarea_field(wp_unslash($_POST['meta_allowlist'])) : '';
        $keys = preg_split('/[\r\n,]+/', $raw) ?: [];
        WPGPTVibe_Meta_Manager::update_allowlist(array_map('trim', $keys));
        wp_safe_redirect(self::page_url());
        exit;
    }

    private static function guard(string $action): void {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions.');
        }
        check_admin_referer($action);
    }

    private static function page_url(): string {
        return admin_url('tools.php?page=wpgptvibe');
    }
}

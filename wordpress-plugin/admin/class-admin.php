<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WPGPTVibe_Admin {
    public static function init(): void {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_post_wpgptvibe_save_connection', [self::class, 'save_connection']);
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
            <p>Connect this website directly to ChatGPT. Build editable pages and manage content from one connection.</p>
            <h2>Connect ChatGPT</h2>
            <p><strong>MCP server URL</strong> <code style="user-select:all"><?php echo esc_html(WPGPTVibe_OAuth::resource()); ?></code></p>
            <ol><li>In ChatGPT, open Plugins and choose Add custom MCP server.</li><li>Paste the server URL above, select OAuth, then choose dynamic client registration (DCR).</li><li>Use scopes <code>site:read pages:read pages:write layout:write media:write seo:write</code> for page editing. Add publication or theme scopes only when needed.</li><li>Sign in to this WordPress website and review the requested permissions.</li><li>Enable the corresponding permissions below, then ask ChatGPT to inspect the site and create a draft page.</li></ol>
            <p>Pages remain editable in WordPress. Open the page editor, click text, replace images, and use List View to move or duplicate sections.</p>
            <table class="widefat striped" style="max-width:1000px"><tbody>
                <tr><th>HTTPS</th><td><?php echo is_ssl() ? 'Available' : 'Required. Open this dashboard over HTTPS.'; ?></td></tr>
                <tr><th>WordPress REST endpoint</th><td><a href="<?php echo esc_url(rest_url('wpgptvibe/v1/oauth/resource')); ?>" target="_blank" rel="noopener">Open discovery test</a> (must return JSON)</td></tr>
                <tr><th>OAuth discovery</th><td><a href="<?php echo esc_url(WPGPTVibe_OAuth::issuer().'/.well-known/oauth-authorization-server'); ?>" target="_blank" rel="noopener">Open authorization metadata</a> (must return JSON)</td></tr>
                <tr><th>Editor</th><td>WordPress Block Editor. Elementor, Divi and WPBakery pages are preserved and require separate integrations.</td></tr>
                <tr><th>Registered connections</th><td><?php echo esc_html((string)count(get_option('wpgptvibe_oauth_clients',[]))); ?></td></tr>
                <tr><th>Header and footer editing</th><td><?php echo wp_is_block_theme() ? 'Site Editor available through WordPress (MCP template tools are not included in this release).' : 'Classic theme: use the theme’s WordPress controls.'; ?></td></tr>
            </tbody></table>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('wpgptvibe_save_connection'); ?><input type="hidden" name="action" value="wpgptvibe_save_connection">
                <p><label for="wpgptvibe-redirects"><strong>Approved OAuth callback URLs</strong></label></p>
                <textarea id="wpgptvibe-redirects" name="redirects" rows="3" class="large-text code" style="max-width:1000px"><?php echo esc_textarea(implode("\n",get_option('wpgptvibe_oauth_redirects',['https://chatgpt.com/connector_platform_oauth_redirect']))); ?></textarea>
                <p class="description">Use the exact HTTPS callback shown in ChatGPT connection settings. One URL per line. Wildcards are not supported.</p>
                <?php submit_button('Save Connection Settings','secondary'); ?>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('wpgptvibe_disconnect'); ?><input type="hidden" name="action" value="wpgptvibe_disconnect">
                <?php submit_button('Disconnect All ChatGPT Sessions','secondary'); ?>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('wpgptvibe_reset_clients'); ?><input type="hidden" name="action" value="wpgptvibe_reset_clients">
                <p class="description">Reset registrations if connection setup is stuck (all clients must reconnect).</p>
                <?php submit_button('Reset OAuth Registrations','secondary'); ?>
            </form>

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

    public static function save_connection(): void {
        self::guard('wpgptvibe_save_connection');
        $raw=isset($_POST['redirects'])&&is_string($_POST['redirects'])?wp_unslash($_POST['redirects']):'';
        $urls=array_values(array_unique(array_filter(array_map('trim',explode("\n",$raw)))));
        if(!$urls||count($urls)>10){wp_die('Provide between 1 and 10 exact callback URLs.');}
        foreach($urls as $url){$parts=wp_parse_url($url);if(strlen($url)>2048||!filter_var($url,FILTER_VALIDATE_URL)||($parts['scheme']??'')!=='https'||empty($parts['host'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['fragment'])){wp_die('Use exact HTTPS callback URLs without credentials or fragments.');}}
        update_option('wpgptvibe_oauth_redirects',$urls,false);
        WPGPTVibe_OAuth::disconnect();
        // Existing clients are removed when their callback is no longer approved.
        $clients=get_option('wpgptvibe_oauth_clients',[]);foreach($clients as $id=>$client){if(!in_array($client['redirect_uri'],$urls,true)){unset($clients[$id]);}}update_option('wpgptvibe_oauth_clients',$clients,false);
        wp_safe_redirect(self::page_url());exit;
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

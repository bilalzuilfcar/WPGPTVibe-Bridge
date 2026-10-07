<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WPGPTVibe_Auth {
    private const TOKEN_HASH_OPTION = 'wpgptvibe_api_token_hash';
    private const TOKEN_HINT_OPTION = 'wpgptvibe_api_token_hint';
    private const SITE_ID_OPTION = 'wpgptvibe_site_id';
    private const CAPABILITIES_OPTION = 'wpgptvibe_capabilities';

    public const CAPABILITIES = [
        'read_site',
        'read_theme_files',
        'edit_theme_files',
        'create_draft_themes',
        'publish_themes',
        'rollback_themes',
        'manage_content',
        'manage_meta',
        'manage_media',
        'manage_seo',
        'purge_cache',
        'run_wpcli',
        'manage_calculators',
    ];

    public static function activate(): void {
        if (!get_option(self::SITE_ID_OPTION)) {
            update_option(self::SITE_ID_OPTION, wp_generate_uuid4(), false);
        }

        if (!get_option(self::CAPABILITIES_OPTION)) {
            update_option(self::CAPABILITIES_OPTION, [
                'read_site' => true,
                'read_theme_files' => true,
                'edit_theme_files' => false,
                'create_draft_themes' => true,
                'publish_themes' => false,
                'rollback_themes' => false,
                'manage_content' => false,
                'manage_meta' => false,
                'manage_media' => false,
                'manage_seo' => false,
                'purge_cache' => false,
                'run_wpcli' => false,
                'manage_calculators' => false,
            ], false);
        }
    }

    public static function site_id(): string {
        $site_id = (string) get_option(self::SITE_ID_OPTION, '');
        if ($site_id === '') {
            $site_id = wp_generate_uuid4();
            update_option(self::SITE_ID_OPTION, $site_id, false);
        }
        return $site_id;
    }

    public static function generate_token(): string {
        $token = 'wpgv_' . bin2hex(random_bytes(32));
        update_option(self::TOKEN_HASH_OPTION, password_hash($token, PASSWORD_DEFAULT), false);
        update_option(self::TOKEN_HINT_OPTION, substr($token, -8), false);
        WPGPTVibe_Audit_Log::record('token_generate', 'api_token', 'success');
        return $token;
    }

    public static function revoke_token(): void {
        delete_option(self::TOKEN_HASH_OPTION);
        delete_option(self::TOKEN_HINT_OPTION);
        WPGPTVibe_Audit_Log::record('token_revoke', 'api_token', 'success');
    }

    public static function token_status(): array {
        $hash = (string) get_option(self::TOKEN_HASH_OPTION, '');
        return [
            'configured' => $hash !== '',
            'hint' => $hash !== '' ? (string) get_option(self::TOKEN_HINT_OPTION, '') : '',
        ];
    }

    public static function capabilities(): array {
        $stored = get_option(self::CAPABILITIES_OPTION, []);
        return is_array($stored) ? $stored : [];
    }

    public static function enabled_capabilities(): array {
        return array_keys(array_filter(self::capabilities(), static fn($enabled): bool => $enabled === true || $enabled === '1' || $enabled === 1));
    }

    public static function update_capabilities(array $submitted): void {
        $next = [];
        foreach (self::CAPABILITIES as $capability) {
            $next[$capability] = !empty($submitted[$capability]);
        }
        update_option(self::CAPABILITIES_OPTION, $next, false);
        WPGPTVibe_Audit_Log::record('capabilities_update', 'bridge_permissions', 'success');
    }

    public static function authorize(string $required_capability) {
        if (!self::is_https_request()) {
            return new WP_Error('wpgptvibe_https_required', 'WPGPTVibe requires HTTPS.', ['status' => 403]);
        }

        if (!in_array($required_capability, self::enabled_capabilities(), true)) {
            return new WP_Error('wpgptvibe_capability_disabled', 'This WPGPTVibe capability is disabled.', ['status' => 403]);
        }

        $token = self::bearer_token();
        $hash = (string) get_option(self::TOKEN_HASH_OPTION, '');

        if ($token === '' || $hash === '' || !password_verify($token, $hash)) {
            return new WP_Error('wpgptvibe_unauthorized', 'Invalid or missing WPGPTVibe API token.', ['status' => 401]);
        }

        $site_header = isset($_SERVER['HTTP_X_WPGPTVIBE_SITE_ID']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_WPGPTVIBE_SITE_ID'])) : '';
        if ($site_header !== '' && !hash_equals(self::site_id(), $site_header)) {
            return new WP_Error('wpgptvibe_site_mismatch', 'Site identity mismatch.', ['status' => 403]);
        }

        return true;
    }

    private static function bearer_token(): string {
        $header = isset($_SERVER['HTTP_AUTHORIZATION']) ? trim((string) wp_unslash($_SERVER['HTTP_AUTHORIZATION'])) : '';
        if ($header === '' && function_exists('getallheaders')) {
            $headers = getallheaders();
            $header = isset($headers['Authorization']) ? trim((string) $headers['Authorization']) : '';
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return '';
        }

        return trim($matches[1]);
    }

    private static function is_https_request(): bool {
        if (is_ssl()) {
            return true;
        }

        // Permit local development only.
        $host = isset($_SERVER['HTTP_HOST']) ? strtolower((string) $_SERVER['HTTP_HOST']) : '';
        return str_starts_with($host, 'localhost') || str_starts_with($host, '127.0.0.1');
    }
}

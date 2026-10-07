<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WPGPTVibe_REST {
    private const NS = 'wpgptvibe/v1';

    public static function init(): void {
        add_action('rest_api_init', [self::class, 'routes']);
    }

    public static function routes(): void {
        register_rest_route(self::NS, '/site', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'site_info'],
            'permission_callback' => self::permission('read_site'),
        ]);

        register_rest_route(self::NS, '/theme/files', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'list_files'],
            'permission_callback' => self::permission('read_theme_files'),
            'args' => [
                'theme' => ['type' => 'string', 'required' => false],
                'path' => ['type' => 'string', 'required' => false],
            ],
        ]);

        register_rest_route(self::NS, '/theme/file', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'read_file'],
            'permission_callback' => self::permission('read_theme_files'),
            'args' => [
                'path' => ['type' => 'string', 'required' => true],
                'theme' => ['type' => 'string', 'required' => false],
                'start_line' => ['type' => 'integer', 'minimum' => 1, 'required' => false],
                'end_line' => ['type' => 'integer', 'minimum' => 1, 'required' => false],
            ],
        ]);

        register_rest_route(self::NS, '/theme/draft/create', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'create_draft'],
            'permission_callback' => self::permission('create_draft_themes'),
        ]);

        register_rest_route(self::NS, '/theme/draft', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'draft_status'],
            'permission_callback' => self::permission('read_theme_files'),
        ]);

        register_rest_route(self::NS, '/theme/draft/preview', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'draft_preview'],
            'permission_callback' => self::permission('read_theme_files'),
        ]);

        register_rest_route(self::NS, '/theme/file/edit', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'edit_file'],
            'permission_callback' => self::permission('edit_theme_files'),
            'args' => [
                'path' => ['type' => 'string', 'required' => true],
                'old_content' => ['type' => 'string', 'required' => true],
                'new_content' => ['type' => 'string', 'required' => true],
                'replace_all' => ['type' => 'boolean', 'required' => false, 'default' => false],
            ],
        ]);

        register_rest_route(self::NS, '/theme/file/write', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'write_file'],
            'permission_callback' => self::permission('edit_theme_files'),
            'args' => [
                'path' => ['type' => 'string', 'required' => true],
                'content' => ['type' => 'string', 'required' => true],
            ],
        ]);

        register_rest_route(self::NS, '/theme/draft/publish', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'publish_draft'],
            'permission_callback' => self::permission('publish_themes'),
        ]);

        register_rest_route(self::NS, '/theme/rollback', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'rollback_theme'],
            'permission_callback' => self::permission('rollback_themes'),
            'args' => [
                'release_id' => ['type' => 'string', 'required' => true],
            ],
        ]);

        register_rest_route(self::NS, '/audit', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'audit'],
            'permission_callback' => self::permission('read_site'),
            'args' => [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
        ]);
    }

    private static function permission(string $capability): callable {
        return static fn(WP_REST_Request $request) => WPGPTVibe_Auth::authorize($capability);
    }

    public static function site_info(): WP_REST_Response {
        global $wp_version;
        return self::ok([
            'site_id' => WPGPTVibe_Auth::site_id(),
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url('/'),
            'wordpress_version' => $wp_version,
            'php_version' => PHP_VERSION,
            'active_theme' => WPGPTVibe_Theme_Manager::active_theme_info(),
            'plugin_version' => WPGPTVIBE_VERSION,
            'capabilities' => WPGPTVibe_Auth::enabled_capabilities(),
        ]);
    }

    public static function list_files(WP_REST_Request $request) {
        return self::attempt(static fn() => WPGPTVibe_Theme_Manager::list_files(
            $request->get_param('theme') ?: null,
            $request->get_param('path') ?: null
        ));
    }

    public static function read_file(WP_REST_Request $request) {
        return self::attempt(static fn() => WPGPTVibe_Theme_Manager::read_file(
            (string) $request->get_param('path'),
            $request->get_param('theme') ?: null,
            $request->get_param('start_line') !== null ? (int) $request->get_param('start_line') : null,
            $request->get_param('end_line') !== null ? (int) $request->get_param('end_line') : null
        ));
    }

    public static function create_draft(WP_REST_Request $request) {
        return self::attempt(static fn() => WPGPTVibe_Theme_Manager::create_draft((bool) $request->get_param('replace')));
    }

    public static function draft_status() {
        return self::attempt(static fn() => WPGPTVibe_Theme_Manager::draft_status());
    }

    public static function draft_preview() {
        return self::attempt(static fn() => WPGPTVibe_Theme_Manager::preview_url());
    }

    public static function edit_file(WP_REST_Request $request) {
        return self::attempt(static fn() => WPGPTVibe_Theme_Manager::edit_file(
            (string) $request->get_param('path'),
            (string) $request->get_param('old_content'),
            (string) $request->get_param('new_content'),
            (bool) $request->get_param('replace_all')
        ));
    }

    public static function write_file(WP_REST_Request $request) {
        return self::attempt(static fn() => WPGPTVibe_Theme_Manager::write_file(
            (string) $request->get_param('path'),
            (string) $request->get_param('content')
        ));
    }

    public static function publish_draft() {
        return self::attempt(static fn() => WPGPTVibe_Theme_Manager::publish_draft());
    }

    public static function rollback_theme(WP_REST_Request $request) {
        return self::attempt(static fn() => WPGPTVibe_Theme_Manager::rollback((string) $request->get_param('release_id')));
    }

    public static function audit(WP_REST_Request $request): WP_REST_Response {
        return self::ok(WPGPTVibe_Audit_Log::recent((int) $request->get_param('limit')));
    }

    private static function attempt(callable $callback) {
        try {
            return self::ok($callback());
        } catch (Throwable $e) {
            return new WP_Error('wpgptvibe_error', $e->getMessage(), ['status' => 400]);
        }
    }

    private static function ok($data): WP_REST_Response {
        return new WP_REST_Response([
            'ok' => true,
            'data' => $data,
        ], 200);
    }
}

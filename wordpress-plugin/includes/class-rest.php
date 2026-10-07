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
        self::get('/site', 'read_site', [self::class, 'site_info']);
        self::get('/audit', 'read_site', [self::class, 'audit']);

        self::get('/theme/files', 'read_theme_files', [self::class, 'list_files']);
        self::get('/theme/file', 'read_theme_files', [self::class, 'read_file']);
        self::get('/theme/search', 'read_theme_files', [self::class, 'search_files']);
        self::get('/theme/file/diff', 'read_theme_files', [self::class, 'file_diff']);
        self::post('/theme/file/edit', 'edit_theme_files', [self::class, 'edit_file']);
        self::post('/theme/file/write', 'edit_theme_files', [self::class, 'write_file']);
        self::post('/theme/file/delete', 'edit_theme_files', [self::class, 'delete_file']);
        self::post('/theme/files/batch-edit', 'edit_theme_files', [self::class, 'batch_edit_files']);
        self::post('/theme/draft/create', 'create_draft_themes', [self::class, 'create_draft']);
        self::get('/theme/draft', 'read_theme_files', [self::class, 'draft_status']);
        self::get('/theme/draft/preview', 'read_theme_files', [self::class, 'draft_preview']);
        self::post('/theme/draft/publish', 'publish_themes', [self::class, 'publish_draft']);
        self::post('/theme/rollback', 'rollback_themes', [self::class, 'rollback_theme']);

        self::get('/content/types', 'manage_content', [self::class, 'content_types']);
        self::get('/content', 'manage_content', [self::class, 'list_content']);
        self::get('/content/(?P<id>\d+)', 'manage_content', [self::class, 'get_content']);
        self::post('/content', 'manage_content', [self::class, 'create_content']);
        self::put('/content/(?P<id>\d+)', 'manage_content', [self::class, 'update_content']);
        self::delete('/content/(?P<id>\d+)', 'manage_content', [self::class, 'delete_content']);
        self::post('/content/batch-update', 'manage_content', [self::class, 'batch_update_content']);

        self::get('/content/(?P<id>\d+)/meta', 'manage_meta', [self::class, 'get_meta']);
        self::put('/content/(?P<id>\d+)/meta', 'manage_meta', [self::class, 'update_meta']);
        self::post('/meta/batch-update', 'manage_meta', [self::class, 'batch_update_meta']);

        self::get('/media', 'manage_media', [self::class, 'list_media']);
        self::get('/media/(?P<id>\d+)', 'manage_media', [self::class, 'get_media']);
        self::post('/media/upload', 'manage_media', [self::class, 'upload_media']);
        self::post('/media/import', 'manage_media', [self::class, 'import_media']);
        self::put('/media/(?P<id>\d+)', 'manage_media', [self::class, 'update_media']);

        self::get('/seo/(?P<id>\d+)', 'manage_seo', [self::class, 'get_seo']);
        self::put('/seo/(?P<id>\d+)', 'manage_seo', [self::class, 'update_seo']);
        self::post('/seo/batch-update', 'manage_seo', [self::class, 'batch_update_seo']);

        self::post('/cache/purge', 'purge_cache', [self::class, 'purge_cache']);
        self::post('/rewrite/flush', 'purge_cache', [self::class, 'flush_rewrites']);

        self::get('/wpcli/status', 'run_wpcli', [self::class, 'wpcli_status']);
        self::post('/wpcli/run', 'run_wpcli', [self::class, 'wpcli_run']);

        self::get('/calculators', 'manage_calculators', [self::class, 'list_calculators']);
        self::get('/calculators/validate-all', 'manage_calculators', [self::class, 'validate_all_calculators']);
        self::post('/calculators/batch-update', 'manage_calculators', [self::class, 'batch_update_calculators']);
        self::get('/calculators/(?P<slug>[a-z0-9-]+)', 'manage_calculators', [self::class, 'get_calculator']);
        self::put('/calculators/(?P<slug>[a-z0-9-]+)', 'manage_calculators', [self::class, 'update_calculator']);
        self::get('/calculators/(?P<slug>[a-z0-9-]+)/validate', 'manage_calculators', [self::class, 'validate_calculator']);
        self::post('/calculators/(?P<slug>[a-z0-9-]+)/test', 'manage_calculators', [self::class, 'test_calculator']);
    }

    private static function get(string $route, string $capability, callable $callback): void {
        self::register($route, WP_REST_Server::READABLE, $capability, $callback);
    }

    private static function post(string $route, string $capability, callable $callback): void {
        self::register($route, WP_REST_Server::CREATABLE, $capability, $callback);
    }

    private static function put(string $route, string $capability, callable $callback): void {
        self::register($route, WP_REST_Server::EDITABLE, $capability, $callback);
    }

    private static function delete(string $route, string $capability, callable $callback): void {
        self::register($route, WP_REST_Server::DELETABLE, $capability, $callback);
    }

    private static function register(string $route, string $methods, string $capability, callable $callback): void {
        register_rest_route(self::NS, $route, [
            'methods' => $methods,
            'callback' => $callback,
            'permission_callback' => self::permission($capability),
        ]);
    }

    private static function permission(string $capability): callable {
        return static function (WP_REST_Request $request) use ($capability) {
            $auth = WPGPTVibe_Auth::authorize($capability);
            if (is_wp_error($auth)) {
                return $auth;
            }
            return WPGPTVibe_Request_Guard::check($request);
        };
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
            'content_types' => WPGPTVibe_Content_Manager::public_post_types(),
            'seo_provider' => WPGPTVibe_SEO_Manager::provider()->name(),
            'wpcli' => WPGPTVibe_WPCLI_Manager::status(),
        ]);
    }

    public static function audit(WP_REST_Request $request): WP_REST_Response {
        return self::ok(WPGPTVibe_Audit_Log::recent(max(1, min(200, (int) ($request->get_param('limit') ?: 50)))));
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

    public static function search_files(WP_REST_Request $request) {
        $extensions = $request->get_param('extensions');
        if (is_string($extensions)) {
            $extensions = array_filter(array_map('trim', explode(',', $extensions)));
        }
        return self::attempt(static fn() => WPGPTVibe_Theme_Operations::search(
            (string) $request->get_param('pattern'),
            is_array($extensions) ? $extensions : [],
            filter_var($request->get_param('case_sensitive'), FILTER_VALIDATE_BOOLEAN),
            (int) ($request->get_param('max_results') ?: 100),
            $request->get_param('theme') ?: null
        ));
    }

    public static function file_diff(WP_REST_Request $request) {
        return self::attempt(static fn() => WPGPTVibe_Theme_Operations::diff((string) $request->get_param('path')));
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

    public static function delete_file(WP_REST_Request $request) {
        if ((string) $request->get_param('confirm') !== 'DELETE') {
            return new WP_Error('wpgptvibe_confirmation_required', 'Theme file deletion requires confirm=DELETE.', ['status' => 400]);
        }
        return self::attempt(static fn() => WPGPTVibe_Theme_Operations::delete_draft_file((string) $request->get_param('path')));
    }

    public static function batch_edit_files(WP_REST_Request $request) {
        return self::attempt(static fn() => WPGPTVibe_Theme_Operations::batch_edit(self::array_param($request, 'items')));
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

    public static function publish_draft(WP_REST_Request $request) {
        if ((string) $request->get_param('confirm') !== 'PUBLISH') {
            return new WP_Error('wpgptvibe_confirmation_required', 'Publishing requires confirm=PUBLISH.', ['status' => 400]);
        }
        return self::attempt(static fn() => WPGPTVibe_Theme_Manager::publish_draft());
    }

    public static function rollback_theme(WP_REST_Request $request) {
        if ((string) $request->get_param('confirm') !== 'ROLLBACK') {
            return new WP_Error('wpgptvibe_confirmation_required', 'Rollback requires confirm=ROLLBACK.', ['status' => 400]);
        }
        return self::attempt(static fn() => WPGPTVibe_Theme_Manager::rollback((string) $request->get_param('release_id')));
    }

    public static function content_types() {
        return self::ok(WPGPTVibe_Content_Manager::public_post_types());
    }

    public static function list_content(WP_REST_Request $request) {
        return self::attempt(static fn() => WPGPTVibe_Content_Manager::list(
            (string) ($request->get_param('post_type') ?: 'page'),
            $request->get_params()
        ));
    }

    public static function get_content(WP_REST_Request $request) {
        return self::attempt(static fn() => WPGPTVibe_Content_Manager::get((int) $request['id']));
    }

    public static function create_content(WP_REST_Request $request) {
        return self::attempt(static fn() => WPGPTVibe_Content_Manager::create(
            (string) ($request->get_param('post_type') ?: 'page'),
            self::body($request)
        ));
    }

    public static function update_content(WP_REST_Request $request) {
        return self::attempt(static fn() => WPGPTVibe_Content_Manager::update((int) $request['id'], self::body($request)));
    }

    public static function delete_content(WP_REST_Request $request) {
        if ((string) $request->get_param('confirm') !== 'DELETE') {
            return new WP_Error('wpgptvibe_confirmation_required', 'Content deletion requires confirm=DELETE.', ['status' => 400]);
        }
        return self::attempt(static fn() => WPGPTVibe_Content_Manager::delete((int) $request['id'], (bool) $request->get_param('force')));
    }

    public static function batch_update_content(WP_REST_Request $request) {
        return self::attempt(static fn() => WPGPTVibe_Content_Manager::batch_update(self::array_param($request, 'items')));
    }

    public static function get_meta(WP_REST_Request $request) {
        return self::attempt(static fn() => WPGPTVibe_Meta_Manager::get((int) $request['id']));
    }

    public static function update_meta(WP_REST_Request $request) {
        return self::attempt(static fn() => WPGPTVibe_Meta_Manager::update((int) $request['id'], self::array_param($request, 'meta')));
    }

    public static function batch_update_meta(WP_REST_Request $request) {
        return self::attempt(static fn() => WPGPTVibe_Meta_Manager::batch_update(self::array_param($request, 'items')));
    }

    public static function list_media(WP_REST_Request $request) {
        return self::attempt(static fn() => WPGPTVibe_Media_Manager::list($request->get_params()));
    }

    public static function get_media(WP_REST_Request $request) {
        return self::attempt(static fn() => WPGPTVibe_Media_Manager::get((int) $request['id']));
    }

    public static function upload_media(WP_REST_Request $request) {
        return self::attempt(static fn() => WPGPTVibe_Media_Manager::upload_base64(
            (string) $request->get_param('filename'),
            (string) $request->get_param('base64'),
            $request->get_param('mime_type') ? (string) $request->get_param('mime_type') : null
        ));
    }

    public static function import_media(WP_REST_Request $request) {
        return self::attempt(static fn() => WPGPTVibe_Media_Manager::import_url(
            (string) $request->get_param('url'),
            $request->get_param('filename') ? (string) $request->get_param('filename') : null
        ));
    }

    public static function update_media(WP_REST_Request $request) {
        return self::attempt(static fn() => WPGPTVibe_Media_Manager::update((int) $request['id'], self::body($request)));
    }

    public static function get_seo(WP_REST_Request $request) {
        return self::attempt(static fn() => WPGPTVibe_SEO_Manager::get((int) $request['id']));
    }

    public static function update_seo(WP_REST_Request $request) {
        return self::attempt(static fn() => WPGPTVibe_SEO_Manager::update((int) $request['id'], self::body($request)));
    }

    public static function batch_update_seo(WP_REST_Request $request) {
        return self::attempt(static fn() => WPGPTVibe_SEO_Manager::batch_update(self::array_param($request, 'items')));
    }

    public static function purge_cache(WP_REST_Request $request) {
        return self::attempt(static fn() => WPGPTVibe_Cache_Manager::purge(self::array_param($request, 'layers')));
    }

    public static function flush_rewrites(WP_REST_Request $request) {
        if ((string) $request->get_param('confirm') !== 'FLUSH') {
            return new WP_Error('wpgptvibe_confirmation_required', 'Rewrite flush requires confirm=FLUSH.', ['status' => 400]);
        }
        return self::attempt(static fn() => WPGPTVibe_Cache_Manager::flush_rewrites());
    }

    public static function wpcli_status() {
        return self::ok(WPGPTVibe_WPCLI_Manager::status());
    }

    public static function wpcli_run(WP_REST_Request $request) {
        $operation = (string) $request->get_param('operation');
        $mutating = in_array($operation, ['cache_flush', 'rewrite_flush'], true);
        $confirmed = !$mutating || (string) $request->get_param('confirm') === 'RUN';
        return self::attempt(static fn() => WPGPTVibe_WPCLI_Manager::run($operation, self::array_param($request, 'args'), $confirmed));
    }

    public static function list_calculators() {
        return self::attempt(static fn() => WPGPTVibe_Calculator_Manager::list());
    }

    public static function get_calculator(WP_REST_Request $request) {
        return self::attempt(static fn() => WPGPTVibe_Calculator_Manager::get((string) $request['slug']));
    }

    public static function update_calculator(WP_REST_Request $request) {
        return self::attempt(static fn() => WPGPTVibe_Calculator_Manager::update((string) $request['slug'], self::body($request)));
    }

    public static function validate_calculator(WP_REST_Request $request) {
        return self::attempt(static fn() => WPGPTVibe_Calculator_Manager::validate((string) $request['slug']));
    }

    public static function validate_all_calculators() {
        return self::attempt(static fn() => WPGPTVibe_Calculator_Manager::validate_all());
    }

    public static function test_calculator(WP_REST_Request $request) {
        return self::attempt(static fn() => WPGPTVibe_Calculator_Manager::test((string) $request['slug'], self::body($request)));
    }

    public static function batch_update_calculators(WP_REST_Request $request) {
        return self::attempt(static fn() => WPGPTVibe_Calculator_Manager::batch_update(self::array_param($request, 'items')));
    }

    private static function body(WP_REST_Request $request): array {
        $body = $request->get_json_params();
        return is_array($body) ? $body : $request->get_params();
    }

    private static function array_param(WP_REST_Request $request, string $key): array {
        $body = self::body($request);
        $value = $body[$key] ?? $request->get_param($key);
        return is_array($value) ? $value : [];
    }

    private static function attempt(callable $callback) {
        try {
            return self::ok($callback());
        } catch (Throwable $e) {
            WPGPTVibe_Audit_Log::record('request_error', 'rest', 'failure', ['meta' => ['message' => mb_substr($e->getMessage(), 0, 300)]]);
            return new WP_Error('wpgptvibe_error', $e->getMessage(), ['status' => 400]);
        }
    }

    private static function ok($data): WP_REST_Response {
        return new WP_REST_Response(['ok' => true, 'data' => $data], 200);
    }
}

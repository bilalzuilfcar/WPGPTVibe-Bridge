<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WPGPTVibe_Theme_Manager {
    private const DRAFT_OPTION = 'wpgptvibe_draft_theme';
    private const RELEASES_OPTION = 'wpgptvibe_theme_releases';
    private const ALLOWED_EXTENSIONS = ['php', 'css', 'js', 'json', 'html', 'txt', 'svg'];

    public static function init(): void {
        add_action('plugins_loaded', [self::class, 'register_preview_hooks'], 1);
    }

    public static function register_preview_hooks(): void {
        if (empty($_GET['wpgptvibe_preview'])) {
            return;
        }

        $token = sanitize_text_field(wp_unslash($_GET['wpgptvibe_preview']));
        $draft = get_transient('wpgptvibe_preview_' . hash('sha256', $token));

        if (!is_array($draft) || empty($draft['stylesheet'])) {
            return;
        }

        $theme = wp_get_theme($draft['stylesheet']);
        if (!$theme->exists()) {
            return;
        }

        add_filter('pre_option_stylesheet', static fn() => $draft['stylesheet']);
        add_filter('pre_option_template', static fn() => $theme->get_template());
    }

    public static function active_theme_info(): array {
        $theme = wp_get_theme();
        return [
            'name' => $theme->get('Name'),
            'slug' => get_stylesheet(),
            'version' => $theme->get('Version'),
        ];
    }

    public static function list_files(?string $theme_slug = null, ?string $path_prefix = null): array {
        $root = self::theme_root($theme_slug ?: get_stylesheet());
        $files = [];
        $prefix = $path_prefix ? self::normalize_relative_path($path_prefix) : '';

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');
            if ($prefix !== '' && !str_starts_with($relative, $prefix)) {
                continue;
            }

            $files[] = [
                'path' => $relative,
                'size' => $file->getSize(),
                'modified' => gmdate('c', $file->getMTime()),
            ];
        }

        usort($files, static fn(array $a, array $b): int => strcmp($a['path'], $b['path']));
        return $files;
    }

    public static function read_file(string $path, ?string $theme_slug = null, ?int $start_line = null, ?int $end_line = null): array {
        $root = self::theme_root($theme_slug ?: get_stylesheet());
        $full = self::existing_file_path($root, $path);
        $content = file_get_contents($full);

        if ($content === false) {
            throw new RuntimeException('Unable to read theme file.');
        }

        $lines = preg_split('/\R/', $content);
        $total = count($lines);

        if ($start_line !== null || $end_line !== null) {
            $start = max(1, $start_line ?? 1);
            $end = min($total, $end_line ?? $total);
            if ($end < $start) {
                throw new InvalidArgumentException('end_line must be greater than or equal to start_line.');
            }
            $content = implode("\n", array_slice($lines, $start - 1, $end - $start + 1));
        }

        return [
            'path' => self::normalize_relative_path($path),
            'theme' => basename($root),
            'content' => $content,
            'total_lines' => $total,
            'sha256' => hash('sha256', file_get_contents($full) ?: ''),
        ];
    }

    public static function create_draft(bool $replace = false): array {
        $source_slug = get_stylesheet();
        $source_root = self::theme_root($source_slug);
        $draft_slug = sanitize_key($source_slug . '-wpgptvibe-draft');
        $draft_root = trailingslashit(get_theme_root($source_slug)) . $draft_slug;

        if (is_dir($draft_root)) {
            if (!$replace) {
                $existing = get_option(self::DRAFT_OPTION, []);
                return is_array($existing) ? $existing : ['stylesheet' => $draft_slug];
            }
            self::delete_directory($draft_root);
        }

        self::copy_directory($source_root, $draft_root);

        $info = [
            'stylesheet' => $draft_slug,
            'source_stylesheet' => $source_slug,
            'created_at' => gmdate('c'),
            'path' => $draft_root,
        ];
        update_option(self::DRAFT_OPTION, $info, false);

        WPGPTVibe_Audit_Log::record('draft_theme_create', $draft_slug, 'success', [
            'meta' => ['source' => $source_slug],
        ]);

        return $info;
    }

    public static function draft_status(): array {
        $draft = get_option(self::DRAFT_OPTION, []);
        if (!is_array($draft) || empty($draft['stylesheet'])) {
            return ['exists' => false];
        }

        $theme = wp_get_theme($draft['stylesheet']);
        return [
            'exists' => $theme->exists(),
            'stylesheet' => $draft['stylesheet'],
            'source_stylesheet' => $draft['source_stylesheet'] ?? null,
            'created_at' => $draft['created_at'] ?? null,
            'theme_name' => $theme->exists() ? $theme->get('Name') : null,
            'version' => $theme->exists() ? $theme->get('Version') : null,
        ];
    }

    public static function preview_url(): array {
        $draft = self::require_draft();
        $token = bin2hex(random_bytes(24));
        set_transient('wpgptvibe_preview_' . hash('sha256', $token), $draft, 15 * MINUTE_IN_SECONDS);

        return [
            'url' => add_query_arg('wpgptvibe_preview', rawurlencode($token), home_url('/')),
            'expires_in' => 900,
        ];
    }

    public static function edit_file(string $path, string $old_content, string $new_content, bool $replace_all = false): array {
        $draft = self::require_draft();
        $root = self::theme_root($draft['stylesheet']);
        $full = self::existing_file_path($root, $path);
        self::assert_allowed_extension($full);

        $before = file_get_contents($full);
        if ($before === false) {
            throw new RuntimeException('Unable to read target file.');
        }

        $matches = substr_count($before, $old_content);
        if ($matches === 0) {
            throw new RuntimeException('Exact old_content was not found.');
        }
        if ($matches > 1 && !$replace_all) {
            throw new RuntimeException('old_content matched multiple locations. Set replace_all=true to continue.');
        }

        $after = $replace_all
            ? str_replace($old_content, $new_content, $before)
            : self::replace_first($before, $old_content, $new_content);

        self::validate_content($full, $after);
        self::atomic_write($full, $after);

        $result = [
            'path' => self::normalize_relative_path($path),
            'replacements' => $replace_all ? $matches : 1,
            'before_hash' => hash('sha256', $before),
            'after_hash' => hash('sha256', $after),
        ];

        WPGPTVibe_Audit_Log::record('theme_file_edit', $result['path'], 'success', $result);
        return $result;
    }

    public static function write_file(string $path, string $content): array {
        $draft = self::require_draft();
        $root = self::theme_root($draft['stylesheet']);
        $relative = self::normalize_relative_path($path);
        $full = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        self::assert_path_inside_root($root, dirname($full), true);
        self::assert_allowed_extension($full);

        $before = is_file($full) ? (file_get_contents($full) ?: '') : '';
        self::validate_content($full, $content);

        if (!is_dir(dirname($full)) && !wp_mkdir_p(dirname($full))) {
            throw new RuntimeException('Unable to create target directory.');
        }

        self::atomic_write($full, $content);

        $result = [
            'path' => $relative,
            'before_hash' => hash('sha256', $before),
            'after_hash' => hash('sha256', $content),
        ];

        WPGPTVibe_Audit_Log::record('theme_file_write', $relative, 'success', $result);
        return $result;
    }

    public static function publish_draft(): array {
        $draft = self::require_draft();
        $draft_theme = wp_get_theme($draft['stylesheet']);
        if (!$draft_theme->exists() || !empty($draft_theme->errors())) {
            throw new RuntimeException('Draft theme is not valid and cannot be published.');
        }

        self::validate_php_tree(self::theme_root($draft['stylesheet']));

        $previous = get_stylesheet();
        $release_id = wp_generate_uuid4();
        $releases = get_option(self::RELEASES_OPTION, []);
        if (!is_array($releases)) {
            $releases = [];
        }

        $releases[$release_id] = [
            'release_id' => $release_id,
            'previous_stylesheet' => $previous,
            'published_stylesheet' => $draft['stylesheet'],
            'created_at' => gmdate('c'),
        ];
        update_option(self::RELEASES_OPTION, $releases, false);

        switch_theme($draft['stylesheet']);

        WPGPTVibe_Audit_Log::record('draft_theme_publish', $draft['stylesheet'], 'success', [
            'meta' => ['release_id' => $release_id, 'previous_stylesheet' => $previous],
        ]);

        return [
            'release_id' => $release_id,
            'backup_id' => $release_id,
            'active_theme' => get_stylesheet(),
        ];
    }

    public static function releases(): array {
        $releases = get_option(self::RELEASES_OPTION, []);
        if (!is_array($releases)) {
            return [];
        }

        $rows = array_values($releases);
        usort($rows, static function (array $a, array $b): int {
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });

        return $rows;
    }

    public static function rollback(string $release_id): array {
        $releases = get_option(self::RELEASES_OPTION, []);
        if (!is_array($releases) || empty($releases[$release_id]['previous_stylesheet'])) {
            throw new RuntimeException('Release record not found.');
        }

        $target = sanitize_key($releases[$release_id]['previous_stylesheet']);
        $theme = wp_get_theme($target);
        if (!$theme->exists()) {
            throw new RuntimeException('Rollback theme no longer exists.');
        }

        switch_theme($target);
        WPGPTVibe_Audit_Log::record('theme_rollback', $target, 'success', [
            'meta' => ['release_id' => $release_id],
        ]);

        return ['release_id' => $release_id, 'active_theme' => get_stylesheet()];
    }

    private static function require_draft(): array {
        $draft = get_option(self::DRAFT_OPTION, []);
        if (!is_array($draft) || empty($draft['stylesheet'])) {
            throw new RuntimeException('No WPGPTVibe draft theme exists.');
        }
        return $draft;
    }

    private static function theme_root(string $slug): string {
        $slug = sanitize_key($slug);
        $theme = wp_get_theme($slug);
        if (!$theme->exists()) {
            throw new RuntimeException('Theme not found.');
        }

        $root = realpath($theme->get_stylesheet_directory());
        if ($root === false) {
            throw new RuntimeException('Theme directory could not be resolved.');
        }
        return $root;
    }

    private static function existing_file_path(string $root, string $path): string {
        $relative = self::normalize_relative_path($path);
        $candidate = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
        if ($candidate === false || !is_file($candidate)) {
            throw new RuntimeException('Theme file not found.');
        }

        self::assert_path_inside_root($root, $candidate);
        return $candidate;
    }

    private static function normalize_relative_path(string $path): string {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, "\0")) {
            throw new InvalidArgumentException('Invalid file path.');
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new InvalidArgumentException('Path traversal is not allowed.');
            }
        }

        return $path;
    }

    private static function assert_path_inside_root(string $root, string $candidate, bool $allow_nonexistent = false): void {
        $resolved_root = realpath($root);
        $resolved_candidate = $allow_nonexistent ? realpath($candidate) : realpath($candidate);

        if ($allow_nonexistent && $resolved_candidate === false) {
            $probe = $candidate;
            while (!is_dir($probe) && dirname($probe) !== $probe) {
                $probe = dirname($probe);
            }
            $resolved_candidate = realpath($probe);
        }

        if ($resolved_root === false || $resolved_candidate === false) {
            throw new RuntimeException('Unable to validate path.');
        }

        $prefix = rtrim($resolved_root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if ($resolved_candidate !== $resolved_root && !str_starts_with($resolved_candidate . DIRECTORY_SEPARATOR, $prefix)) {
            throw new RuntimeException('Path is outside the allowed theme directory.');
        }
    }

    private static function assert_allowed_extension(string $path): void {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException('File extension is not allowed.');
        }
    }

    private static function validate_content(string $path, string $content): void {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'json') {
            json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        }

        if ($extension === 'php') {
            self::lint_php($content);
        }
    }

    private static function lint_php(string $content): void {
        if (!defined('PHP_BINARY') || PHP_BINARY === '' || !function_exists('proc_open')) {
            return;
        }

        $tmp = wp_tempnam('wpgptvibe-lint.php');
        if (!$tmp) {
            return;
        }

        file_put_contents($tmp, $content);
        $pipes = [];
        $process = @proc_open([PHP_BINARY, '-l', $tmp], [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (!is_resource($process)) {
            @unlink($tmp);
            return;
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        @unlink($tmp);

        if ($exit !== 0) {
            throw new RuntimeException('PHP syntax validation failed: ' . trim($stderr ?: $stdout));
        }
    }

    private static function validate_php_tree(string $root): void {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                $content = file_get_contents($file->getPathname());
                if ($content !== false) {
                    self::lint_php($content);
                }
            }
        }
    }

    private static function atomic_write(string $path, string $content): void {
        $tmp = $path . '.wpgptvibe-' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($tmp, $content, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write temporary file.');
        }
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Unable to replace target file atomically.');
        }
    }

    private static function replace_first(string $haystack, string $needle, string $replacement): string {
        $position = strpos($haystack, $needle);
        if ($position === false) {
            return $haystack;
        }
        return substr_replace($haystack, $replacement, $position, strlen($needle));
    }

    private static function copy_directory(string $source, string $destination): void {
        if (!wp_mkdir_p($destination)) {
            throw new RuntimeException('Unable to create draft theme directory.');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $target = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            if ($item->isDir()) {
                if (!is_dir($target) && !wp_mkdir_p($target)) {
                    throw new RuntimeException('Unable to create draft subdirectory.');
                }
            } else {
                if (!copy($item->getPathname(), $target)) {
                    throw new RuntimeException('Unable to copy draft theme file.');
                }
            }
        }
    }

    private static function delete_directory(string $directory): void {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }
}

WPGPTVibe_Theme_Manager::init();

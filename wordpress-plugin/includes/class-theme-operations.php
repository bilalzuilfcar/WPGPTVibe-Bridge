<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WPGPTVibe_Theme_Operations {
    private const ALLOWED_EXTENSIONS = ['php', 'css', 'js', 'json', 'html', 'txt', 'svg'];

    public static function search(string $pattern, array $extensions = [], bool $case_sensitive = false, int $max_results = 100, ?string $theme = null): array {
        $files = WPGPTVibe_Theme_Manager::list_files($theme);
        $extensions = array_values(array_filter(array_map(static fn($v) => strtolower(sanitize_key((string) $v)), $extensions)));
        $max_results = max(1, min(500, $max_results));
        $results = [];

        foreach ($files as $file) {
            $path = (string) ($file['path'] ?? '');
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($extensions && !in_array($ext, $extensions, true)) {
                continue;
            }
            if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                continue;
            }

            $read = WPGPTVibe_Theme_Manager::read_file($path, $theme);
            $lines = preg_split('/\R/', (string) $read['content']);
            foreach ($lines as $index => $line) {
                $matched = $case_sensitive ? str_contains($line, $pattern) : str_contains(strtolower($line), strtolower($pattern));
                if (!$matched) {
                    continue;
                }
                $results[] = [
                    'path' => $path,
                    'line' => $index + 1,
                    'excerpt' => mb_substr(trim($line), 0, 500),
                ];
                if (count($results) >= $max_results) {
                    return $results;
                }
            }
        }

        return $results;
    }

    public static function delete_draft_file(string $path): array {
        $draft = self::draft();
        $root = self::theme_root((string) $draft['stylesheet']);
        $full = self::existing_file($root, $path);
        self::assert_extension($full);
        $before = file_get_contents($full) ?: '';

        if (!unlink($full)) {
            throw new RuntimeException('Unable to delete draft theme file.');
        }

        $result = ['path' => self::normalize($path), 'before_hash' => hash('sha256', $before)];
        WPGPTVibe_Audit_Log::record('theme_file_delete', $result['path'], 'success', $result);
        return $result;
    }

    public static function diff(string $path): array {
        $draft = self::draft();
        $source = (string) ($draft['source_stylesheet'] ?? '');
        if ($source === '') {
            throw new RuntimeException('Draft source theme is unknown.');
        }

        $before = WPGPTVibe_Theme_Manager::read_file($path, $source)['content'] ?? '';
        $after = WPGPTVibe_Theme_Manager::read_file($path, (string) $draft['stylesheet'])['content'] ?? '';
        return [
            'path' => self::normalize($path),
            'before_hash' => hash('sha256', (string) $before),
            'after_hash' => hash('sha256', (string) $after),
            'diff' => self::unified_diff((string) $before, (string) $after),
        ];
    }

    public static function batch_edit(array $items): array {
        $results = [];
        foreach (array_slice($items, 0, 100) as $index => $item) {
            try {
                $results[] = [
                    'index' => $index,
                    'ok' => true,
                    'data' => WPGPTVibe_Theme_Manager::edit_file(
                        (string) ($item['path'] ?? ''),
                        (string) ($item['old_content'] ?? ''),
                        (string) ($item['new_content'] ?? ''),
                        !empty($item['replace_all'])
                    ),
                ];
            } catch (Throwable $e) {
                $results[] = ['index' => $index, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return ['total' => count($results), 'results' => $results];
    }

    private static function unified_diff(string $before, string $after): string {
        if ($before === $after) {
            return '';
        }
        $a = preg_split('/\R/', $before);
        $b = preg_split('/\R/', $after);
        $max = max(count($a), count($b));
        $out = ["--- live", "+++ draft"];
        for ($i = 0; $i < $max; $i++) {
            $left = $a[$i] ?? null;
            $right = $b[$i] ?? null;
            if ($left === $right) {
                continue;
            }
            $out[] = '@@ line ' . ($i + 1) . ' @@';
            if ($left !== null) {
                $out[] = '-' . $left;
            }
            if ($right !== null) {
                $out[] = '+' . $right;
            }
        }
        return implode("\n", array_slice($out, 0, 1200));
    }

    private static function draft(): array {
        $draft = get_option('wpgptvibe_draft_theme', []);
        if (!is_array($draft) || empty($draft['stylesheet'])) {
            throw new RuntimeException('No WPGPTVibe draft theme exists.');
        }
        return $draft;
    }

    private static function theme_root(string $slug): string {
        $theme = wp_get_theme(sanitize_key($slug));
        if (!$theme->exists()) {
            throw new RuntimeException('Theme not found.');
        }
        $root = realpath($theme->get_stylesheet_directory());
        if (!$root) {
            throw new RuntimeException('Theme root could not be resolved.');
        }
        return $root;
    }

    private static function existing_file(string $root, string $path): string {
        $candidate = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::normalize($path)));
        if (!$candidate || !is_file($candidate)) {
            throw new RuntimeException('Theme file not found.');
        }
        $prefix = rtrim(realpath($root), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($candidate . DIRECTORY_SEPARATOR, $prefix)) {
            throw new RuntimeException('Path is outside the allowed theme directory.');
        }
        return $candidate;
    }

    private static function normalize(string $path): string {
        $path = ltrim(str_replace('\\', '/', trim($path)), '/');
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

    private static function assert_extension(string $path): void {
        if (!in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException('File extension is not allowed.');
        }
    }
}

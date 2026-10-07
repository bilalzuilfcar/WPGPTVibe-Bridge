<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WPGPTVibe_Meta_Manager {
    private const ALLOWLIST_OPTION = 'wpgptvibe_protected_meta_allowlist';

    public static function get(int $content_id): array {
        self::assert_content($content_id);
        $all = get_post_meta($content_id);
        $result = [];
        foreach ($all as $key => $values) {
            if (!self::key_allowed((string) $key)) {
                continue;
            }
            $result[$key] = array_map('maybe_unserialize', $values);
        }
        return $result;
    }

    public static function update(int $content_id, array $meta): array {
        self::assert_content($content_id);
        $updated = [];
        foreach ($meta as $key => $value) {
            $key = sanitize_key((string) $key);
            if ($key === '' || !self::key_allowed($key)) {
                throw new RuntimeException('Meta key is not allowed: ' . $key);
            }

            if ($value === null) {
                delete_post_meta($content_id, $key);
            } else {
                update_post_meta($content_id, $key, self::sanitize_value($value));
            }
            $updated[] = $key;
        }

        WPGPTVibe_Audit_Log::record('meta_update', 'post:' . $content_id, 'success', ['meta' => ['keys' => implode(',', $updated)]]);
        return ['content_id' => $content_id, 'updated_keys' => $updated];
    }

    public static function batch_update(array $items): array {
        $results = [];
        foreach (array_slice($items, 0, 100) as $index => $item) {
            try {
                $results[] = [
                    'index' => $index,
                    'ok' => true,
                    'data' => self::update((int) ($item['id'] ?? 0), is_array($item['meta'] ?? null) ? $item['meta'] : []),
                ];
            } catch (Throwable $e) {
                $results[] = ['index' => $index, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return ['total' => count($results), 'results' => $results];
    }

    public static function allowlist(): array {
        $stored = get_option(self::ALLOWLIST_OPTION, []);
        if (!is_array($stored)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map('sanitize_key', $stored))));
    }

    public static function update_allowlist(array $keys): void {
        update_option(self::ALLOWLIST_OPTION, array_values(array_unique(array_filter(array_map('sanitize_key', $keys)))), false);
        WPGPTVibe_Audit_Log::record('meta_allowlist_update', 'protected_meta', 'success');
    }

    private static function key_allowed(string $key): bool {
        if (!str_starts_with($key, '_')) {
            return true;
        }
        return in_array($key, self::allowlist(), true);
    }

    private static function assert_content(int $id): void {
        if ($id <= 0 || !get_post($id)) {
            throw new RuntimeException('Content not found.');
        }
    }

    private static function sanitize_value($value) {
        if (is_array($value)) {
            return array_map([self::class, 'sanitize_value'], $value);
        }
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        return wp_kses_post((string) $value);
    }
}

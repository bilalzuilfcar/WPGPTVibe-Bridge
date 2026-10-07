<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WPGPTVibe_Audit_Log {
    private const OPTION = 'wpgptvibe_audit_log';
    private const MAX_ENTRIES = 200;

    public static function record(string $operation, string $target, string $result, array $context = []): void {
        $entries = get_option(self::OPTION, []);
        if (!is_array($entries)) {
            $entries = [];
        }

        $entry = [
            'timestamp' => gmdate('c'),
            'request_id' => self::request_id(),
            'operation' => sanitize_key($operation),
            'target' => sanitize_text_field($target),
            'result' => sanitize_key($result),
            'before_hash' => isset($context['before_hash']) ? sanitize_text_field((string) $context['before_hash']) : null,
            'after_hash' => isset($context['after_hash']) ? sanitize_text_field((string) $context['after_hash']) : null,
            'meta' => isset($context['meta']) && is_array($context['meta']) ? self::sanitize_meta($context['meta']) : [],
        ];

        array_unshift($entries, $entry);
        $entries = array_slice($entries, 0, self::MAX_ENTRIES);
        update_option(self::OPTION, $entries, false);
    }

    public static function recent(int $limit = 50): array {
        $entries = get_option(self::OPTION, []);
        if (!is_array($entries)) {
            return [];
        }

        return array_slice($entries, 0, max(1, min(200, $limit)));
    }

    private static function request_id(): string {
        $header = isset($_SERVER['HTTP_X_REQUEST_ID']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_REQUEST_ID'])) : '';
        return $header !== '' ? $header : wp_generate_uuid4();
    }

    private static function sanitize_meta(array $meta): array {
        $clean = [];
        foreach ($meta as $key => $value) {
            $key = sanitize_key((string) $key);
            if ($key === '' || preg_match('/token|secret|authorization|password|key/i', $key)) {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $clean[$key] = sanitize_text_field((string) $value);
            }
        }
        return $clean;
    }
}

<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WPGPTVibe_Request_Guard {
    private const WINDOW = 60;
    private const LIMIT = 120;
    private const MAX_BODY_BYTES = 2097152;

    public static function check(WP_REST_Request $request) {
        $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($length > self::MAX_BODY_BYTES) {
            return new WP_Error('wpgptvibe_request_too_large', 'Request body exceeds the 2 MB bridge limit.', ['status' => 413]);
        }

        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if (str_starts_with($host, 'localhost') || str_starts_with($host, '127.0.0.1')) {
            return true;
        }

        $identity = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $key = 'wpgptvibe_rate_' . md5($identity . '|' . WPGPTVibe_Auth::site_id());
        $state = get_transient($key);
        $state = is_array($state) ? $state : ['count' => 0, 'started' => time()];

        if ((time() - (int) $state['started']) >= self::WINDOW) {
            $state = ['count' => 0, 'started' => time()];
        }

        $state['count'] = (int) $state['count'] + 1;
        set_transient($key, $state, self::WINDOW);

        if ($state['count'] > self::LIMIT) {
            return new WP_Error('wpgptvibe_rate_limited', 'Too many WPGPTVibe requests. Retry shortly.', ['status' => 429]);
        }

        return true;
    }
}

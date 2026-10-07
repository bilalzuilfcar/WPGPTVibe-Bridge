<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WPGPTVibe_Media_Manager {
    private const MAX_BYTES = 10485760;

    public static function list(array $filters = []): array {
        $query = new WP_Query([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => min(200, max(1, (int) ($filters['per_page'] ?? 100))),
            's' => sanitize_text_field((string) ($filters['search'] ?? '')),
            'post_mime_type' => !empty($filters['mime_type']) ? sanitize_mime_type((string) $filters['mime_type']) : '',
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        return array_map([self::class, 'normalize'], $query->posts);
    }

    public static function get(int $id): array {
        $post = get_post($id);
        if (!$post instanceof WP_Post || $post->post_type !== 'attachment') {
            throw new RuntimeException('Media attachment not found.');
        }
        return self::normalize($post, true);
    }

    public static function upload_base64(string $filename, string $base64, ?string $mime_type = null): array {
        $filename = sanitize_file_name($filename);
        if ($filename === '') {
            throw new RuntimeException('A valid filename is required.');
        }
        $data = base64_decode($base64, true);
        if ($data === false) {
            throw new RuntimeException('Invalid base64 media payload.');
        }
        if (strlen($data) > self::MAX_BYTES) {
            throw new RuntimeException('Media exceeds the 10 MB upload limit.');
        }
        return self::store_bytes($filename, $data, $mime_type);
    }

    public static function import_url(string $url, ?string $filename = null): array {
        $parsed = wp_parse_url($url);
        if (!is_array($parsed) || strtolower((string) ($parsed['scheme'] ?? '')) !== 'https') {
            throw new RuntimeException('Remote media imports require HTTPS.');
        }

        $response = wp_safe_remote_get($url, [
            'timeout' => 20,
            'redirection' => 3,
            'limit_response_size' => self::MAX_BYTES + 1,
            'user-agent' => 'WPGPTVibe/' . WPGPTVIBE_VERSION,
        ]);
        if (is_wp_error($response)) {
            throw new RuntimeException($response->get_error_message());
        }
        if ((int) wp_remote_retrieve_response_code($response) >= 400) {
            throw new RuntimeException('Remote media request failed.');
        }

        $body = (string) wp_remote_retrieve_body($response);
        if ($body === '' || strlen($body) > self::MAX_BYTES) {
            throw new RuntimeException('Remote media is empty or exceeds the 10 MB limit.');
        }

        $content_type = sanitize_mime_type((string) wp_remote_retrieve_header($response, 'content-type'));
        $path = (string) ($parsed['path'] ?? '');
        $filename = sanitize_file_name($filename ?: basename($path));
        if ($filename === '') {
            $filename = 'remote-media-' . time();
        }

        return self::store_bytes($filename, $body, $content_type ?: null);
    }

    public static function update(int $id, array $data): array {
        $post = get_post($id);
        if (!$post instanceof WP_Post || $post->post_type !== 'attachment') {
            throw new RuntimeException('Media attachment not found.');
        }

        $postarr = ['ID' => $id];
        if (array_key_exists('title', $data)) {
            $postarr['post_title'] = sanitize_text_field((string) $data['title']);
        }
        if (array_key_exists('caption', $data)) {
            $postarr['post_excerpt'] = wp_kses_post((string) $data['caption']);
        }
        if (array_key_exists('description', $data)) {
            $postarr['post_content'] = wp_kses_post((string) $data['description']);
        }

        $updated = wp_update_post($postarr, true);
        if (is_wp_error($updated)) {
            throw new RuntimeException($updated->get_error_message());
        }

        if (array_key_exists('alt_text', $data)) {
            update_post_meta($id, '_wp_attachment_image_alt', sanitize_text_field((string) $data['alt_text']));
        }

        WPGPTVibe_Audit_Log::record('media_update', 'attachment:' . $id, 'success');
        return self::get($id);
    }

    private static function store_bytes(string $filename, string $bytes, ?string $declared_mime): array {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = wp_tempnam($filename);
        if (!$tmp || file_put_contents($tmp, $bytes) === false) {
            throw new RuntimeException('Unable to create temporary media file.');
        }

        try {
            $check = wp_check_filetype_and_ext($tmp, $filename, get_allowed_mime_types());
            $type = sanitize_mime_type((string) ($check['type'] ?? $declared_mime ?? ''));
            $proper = sanitize_file_name((string) ($check['proper_filename'] ?? $filename));
            if ($type === '' || !in_array($type, get_allowed_mime_types(), true)) {
                throw new RuntimeException('Media MIME type is not allowed by WordPress.');
            }

            $file = [
                'name' => $proper ?: $filename,
                'type' => $type,
                'tmp_name' => $tmp,
                'error' => 0,
                'size' => strlen($bytes),
            ];
            $id = media_handle_sideload($file, 0);
            if (is_wp_error($id)) {
                throw new RuntimeException($id->get_error_message());
            }

            WPGPTVibe_Audit_Log::record('media_upload', 'attachment:' . $id, 'success');
            return self::get((int) $id);
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    private static function normalize(WP_Post $post, bool $full = false): array {
        $data = [
            'id' => $post->ID,
            'title' => get_the_title($post),
            'mime_type' => $post->post_mime_type,
            'url' => wp_get_attachment_url($post->ID),
            'alt_text' => (string) get_post_meta($post->ID, '_wp_attachment_image_alt', true),
            'date_gmt' => $post->post_date_gmt,
        ];
        if ($full) {
            $data['caption'] = $post->post_excerpt;
            $data['description'] = $post->post_content;
            $data['metadata'] = wp_get_attachment_metadata($post->ID);
        }
        return $data;
    }
}

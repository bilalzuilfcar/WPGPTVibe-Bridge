<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WPGPTVibe_Generic_SEO_Provider implements WPGPTVibe_SEO_Provider {
    public function name(): string { return 'generic'; }
    public function is_available(): bool { return true; }

    public function get(int $post_id): array {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            throw new RuntimeException('Content not found.');
        }
        return [
            'provider' => $this->name(),
            'title' => get_the_title($post),
            'description' => $post->post_excerpt,
            'canonical' => get_permalink($post),
            'robots' => [],
            'focus_keyword' => '',
            'warnings' => ['No supported SEO plugin detected. Canonical/robots/focus-keyword writes are unavailable in generic mode.'],
        ];
    }

    public function update(int $post_id, array $data): array {
        $postarr = ['ID' => $post_id];
        if (array_key_exists('title', $data)) {
            $postarr['post_title'] = sanitize_text_field((string) $data['title']);
        }
        if (array_key_exists('description', $data)) {
            $postarr['post_excerpt'] = wp_kses_post((string) $data['description']);
        }
        if (count($postarr) > 1) {
            $updated = wp_update_post($postarr, true);
            if (is_wp_error($updated)) {
                throw new RuntimeException($updated->get_error_message());
            }
        }
        return $this->get($post_id);
    }
}

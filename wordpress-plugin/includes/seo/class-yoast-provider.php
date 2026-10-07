<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WPGPTVibe_Yoast_Provider implements WPGPTVibe_SEO_Provider {
    public function name(): string { return 'yoast'; }
    public function is_available(): bool { return defined('WPSEO_VERSION') || class_exists('WPSEO_Options'); }

    public function get(int $post_id): array {
        $noindex = (string) get_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', true);
        return [
            'provider' => $this->name(),
            'title' => (string) get_post_meta($post_id, '_yoast_wpseo_title', true),
            'description' => (string) get_post_meta($post_id, '_yoast_wpseo_metadesc', true),
            'canonical' => (string) get_post_meta($post_id, '_yoast_wpseo_canonical', true),
            'robots' => $noindex === '1' ? ['noindex'] : ($noindex === '2' ? ['index'] : []),
            'focus_keyword' => (string) get_post_meta($post_id, '_yoast_wpseo_focuskw', true),
        ];
    }

    public function update(int $post_id, array $data): array {
        $map = [
            'title' => '_yoast_wpseo_title',
            'description' => '_yoast_wpseo_metadesc',
            'canonical' => '_yoast_wpseo_canonical',
            'focus_keyword' => '_yoast_wpseo_focuskw',
        ];
        foreach ($map as $field => $key) {
            if (array_key_exists($field, $data)) {
                update_post_meta($post_id, $key, sanitize_text_field((string) $data[$field]));
            }
        }
        if (array_key_exists('robots', $data)) {
            $robots = is_array($data['robots']) ? array_map('sanitize_key', $data['robots']) : [];
            update_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', in_array('noindex', $robots, true) ? '1' : '2');
        }
        return $this->get($post_id);
    }
}

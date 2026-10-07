<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WPGPTVibe_Rank_Math_Provider implements WPGPTVibe_SEO_Provider {
    public function name(): string { return 'rank_math'; }
    public function is_available(): bool { return defined('RANK_MATH_VERSION') || class_exists('RankMath'); }

    public function get(int $post_id): array {
        return [
            'provider' => $this->name(),
            'title' => (string) get_post_meta($post_id, 'rank_math_title', true),
            'description' => (string) get_post_meta($post_id, 'rank_math_description', true),
            'canonical' => (string) get_post_meta($post_id, 'rank_math_canonical_url', true),
            'robots' => get_post_meta($post_id, 'rank_math_robots', true),
            'focus_keyword' => (string) get_post_meta($post_id, 'rank_math_focus_keyword', true),
        ];
    }

    public function update(int $post_id, array $data): array {
        $map = [
            'title' => 'rank_math_title',
            'description' => 'rank_math_description',
            'canonical' => 'rank_math_canonical_url',
            'robots' => 'rank_math_robots',
            'focus_keyword' => 'rank_math_focus_keyword',
        ];
        foreach ($map as $field => $key) {
            if (!array_key_exists($field, $data)) continue;
            $value = $field === 'robots' && is_array($data[$field])
                ? array_values(array_map('sanitize_key', $data[$field]))
                : sanitize_text_field((string) $data[$field]);
            update_post_meta($post_id, $key, $value);
        }
        return $this->get($post_id);
    }
}

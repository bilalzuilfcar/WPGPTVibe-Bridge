<?php

if (!defined('ABSPATH')) {
    exit;
}

interface WPGPTVibe_SEO_Provider {
    public function name(): string;
    public function is_available(): bool;
    public function get(int $post_id): array;
    public function update(int $post_id, array $data): array;
}

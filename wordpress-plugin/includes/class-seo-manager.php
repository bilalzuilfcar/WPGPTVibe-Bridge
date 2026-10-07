<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WPGPTVibe_SEO_Manager {
    public static function provider(): WPGPTVibe_SEO_Provider {
        $providers = [
            new WPGPTVibe_Rank_Math_Provider(),
            new WPGPTVibe_Yoast_Provider(),
            new WPGPTVibe_Generic_SEO_Provider(),
        ];
        foreach ($providers as $provider) {
            if ($provider->is_available()) {
                return $provider;
            }
        }
        throw new RuntimeException('No SEO provider available.');
    }

    public static function get(int $post_id): array {
        self::assert_post($post_id);
        return self::provider()->get($post_id);
    }

    public static function update(int $post_id, array $data): array {
        self::assert_post($post_id);
        $provider = self::provider();
        $result = $provider->update($post_id, $data);
        WPGPTVibe_Audit_Log::record('seo_update', 'post:' . $post_id, 'success', ['meta' => ['provider' => $provider->name()]]);
        return $result;
    }

    public static function batch_update(array $items): array {
        $results = [];
        foreach (array_slice($items, 0, 100) as $index => $item) {
            try {
                $results[] = ['index' => $index, 'ok' => true, 'data' => self::update((int) ($item['id'] ?? 0), is_array($item['seo'] ?? null) ? $item['seo'] : [])];
            } catch (Throwable $e) {
                $results[] = ['index' => $index, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return ['total' => count($results), 'results' => $results];
    }

    private static function assert_post(int $post_id): void {
        if ($post_id <= 0 || !get_post($post_id)) {
            throw new RuntimeException('Content not found.');
        }
    }
}

<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WPGPTVibe_Cache_Manager {
    public static function purge(array $layers = []): array {
        $layers = $layers ?: ['object', 'rank_math', 'plugins', 'hostinger'];
        $purged = [];

        if (in_array('object', $layers, true) && function_exists('wp_cache_flush')) {
            wp_cache_flush();
            $purged[] = 'object';
        }

        if (in_array('rank_math', $layers, true)) {
            foreach (['rank_math_sitemap_cache', 'rank_math_redirections_cache'] as $transient) {
                delete_transient($transient);
                delete_site_transient($transient);
            }
            if (has_action('rank_math/sitemap/flush_cache')) {
                do_action('rank_math/sitemap/flush_cache');
                $purged[] = 'rank_math';
            }
        }

        if (in_array('plugins', $layers, true)) {
            $hooks = ['litespeed_purge_all', 'w3tc_flush_all', 'wp_cache_clear_cache'];
            foreach ($hooks as $hook) {
                if (has_action($hook)) {
                    do_action($hook);
                    $purged[] = $hook;
                }
            }
        }

        if (in_array('hostinger', $layers, true) && has_action('hostinger_clear_cache')) {
            do_action('hostinger_clear_cache');
            $purged[] = 'hostinger';
        }

        $purged = array_values(array_unique($purged));
        WPGPTVibe_Audit_Log::record('cache_purge', 'cache', 'success', ['meta' => ['layers' => implode(',', $purged)]]);
        return ['purged' => $purged];
    }

    public static function flush_rewrites(): array {
        flush_rewrite_rules(false);
        WPGPTVibe_Audit_Log::record('rewrite_flush', 'rewrite_rules', 'success');
        return ['flushed' => true];
    }
}

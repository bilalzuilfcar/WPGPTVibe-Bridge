<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WPGPTVibe_WPCLI_Manager {
    private const READ_OPERATIONS = ['plugin_list', 'theme_list', 'option_get', 'post_list', 'cron_list'];
    private const MUTATING_OPERATIONS = ['cache_flush', 'rewrite_flush'];

    public static function run(string $operation, array $args = [], bool $confirmed = false): array {
        $operation = sanitize_key($operation);
        $mutating = in_array($operation, self::MUTATING_OPERATIONS, true);
        if (!in_array($operation, self::READ_OPERATIONS, true) && !$mutating) {
            throw new RuntimeException('WP-CLI operation is not allowlisted.');
        }
        if ($mutating && !$confirmed) {
            throw new RuntimeException('Mutating WP-CLI operation requires explicit confirmation.');
        }
        if (!function_exists('proc_open')) {
            throw new RuntimeException('WP-CLI execution is unavailable because proc_open is disabled.');
        }

        $command = self::command($operation, $args);
        $pipes = [];
        $process = @proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, ABSPATH, null, ['bypass_shell' => true]);

        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start WP-CLI.');
        }
        fclose($pipes[0]);
        stream_set_timeout($pipes[1], 20);
        stream_set_timeout($pipes[2], 20);
        $stdout = stream_get_contents($pipes[1], 1048576);
        $stderr = stream_get_contents($pipes[2], 1048576);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        if ($mutating) {
            WPGPTVibe_Audit_Log::record('wpcli_' . $operation, 'wp-cli', $exit === 0 ? 'success' : 'failure');
        }
        if ($exit !== 0) {
            throw new RuntimeException('WP-CLI failed: ' . trim((string) $stderr));
        }

        $output = trim((string) $stdout);
        $decoded = json_decode($output, true);
        return [
            'operation' => $operation,
            'exit_code' => $exit,
            'output' => json_last_error() === JSON_ERROR_NONE ? $decoded : $output,
        ];
    }

    public static function status(): array {
        return [
            'proc_open' => function_exists('proc_open'),
            'binary' => defined('WPGPTVIBE_WPCLI_BINARY') ? (string) WPGPTVIBE_WPCLI_BINARY : 'wp',
            'read_operations' => self::READ_OPERATIONS,
            'mutating_operations' => self::MUTATING_OPERATIONS,
        ];
    }

    private static function command(string $operation, array $args): array {
        $wp = defined('WPGPTVIBE_WPCLI_BINARY') ? (string) WPGPTVIBE_WPCLI_BINARY : 'wp';
        $base = [$wp, '--path=' . ABSPATH, '--no-color'];
        return match ($operation) {
            'plugin_list' => [...$base, 'plugin', 'list', '--format=json'],
            'theme_list' => [...$base, 'theme', 'list', '--format=json'],
            'option_get' => [...$base, 'option', 'get', sanitize_key((string) ($args['name'] ?? '')), '--format=json'],
            'post_list' => [...$base, 'post', 'list', '--post_type=' . sanitize_key((string) ($args['post_type'] ?? 'post')), '--posts_per_page=' . min(200, max(1, (int) ($args['per_page'] ?? 50))), '--format=json'],
            'cron_list' => [...$base, 'cron', 'event', 'list', '--format=json'],
            'cache_flush' => [...$base, 'cache', 'flush'],
            'rewrite_flush' => [...$base, 'rewrite', 'flush'],
            default => throw new RuntimeException('WP-CLI operation is not allowlisted.'),
        };
    }
}

<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WPGPTVibe_Calculator_Manager {
    public static function list(): array {
        $calculators = apply_filters('wpgptvibe_calculators', []);
        if (!is_array($calculators)) {
            return [];
        }
        $out = [];
        foreach ($calculators as $slug => $config) {
            $slug = is_string($slug) ? sanitize_title($slug) : sanitize_title((string) ($config['slug'] ?? ''));
            if ($slug === '') continue;
            $out[] = [
                'slug' => $slug,
                'title' => sanitize_text_field((string) ($config['title'] ?? $slug)),
                'type' => sanitize_key((string) ($config['type'] ?? 'calculator')),
            ];
        }
        return $out;
    }

    public static function get(string $slug): array {
        $slug = sanitize_title($slug);
        $filtered = apply_filters('wpgptvibe_calculator_get', null, $slug);
        if (is_array($filtered)) {
            return $filtered;
        }

        $calculators = apply_filters('wpgptvibe_calculators', []);
        if (is_array($calculators) && isset($calculators[$slug]) && is_array($calculators[$slug])) {
            return $calculators[$slug];
        }
        foreach ((array) $calculators as $config) {
            if (is_array($config) && sanitize_title((string) ($config['slug'] ?? '')) === $slug) {
                return $config;
            }
        }
        throw new RuntimeException('Calculator not found: ' . $slug);
    }

    public static function update(string $slug, array $config): array {
        $slug = sanitize_title($slug);
        $result = apply_filters('wpgptvibe_calculator_update', null, $slug, $config);
        if (!is_array($result)) {
            throw new RuntimeException('Calculator provider does not support updates.');
        }
        WPGPTVibe_Audit_Log::record('calculator_update', $slug, 'success');
        return $result;
    }

    public static function validate(string $slug): array {
        $slug = sanitize_title($slug);
        $provider = apply_filters('wpgptvibe_calculator_validate', null, $slug);
        if (is_array($provider)) {
            return self::normalize_validation($slug, $provider);
        }

        try {
            $config = self::get($slug);
        } catch (Throwable $e) {
            return self::normalize_validation($slug, [
                'config_found' => false,
                'syntax_ok' => false,
                'errors' => [$e->getMessage()],
            ]);
        }

        $inputs = is_array($config['inputs'] ?? null) ? $config['inputs'] : [];
        $outputs = is_array($config['outputs'] ?? null) ? $config['outputs'] : [];
        $errors = [];
        if (!$inputs) $errors[] = 'No inputs declared.';
        if (!$outputs) $errors[] = 'No outputs declared.';

        return self::normalize_validation($slug, [
            'config_found' => true,
            'syntax_ok' => true,
            'inputs' => $inputs,
            'outputs' => $outputs,
            'formula_checks' => is_array($config['formula_checks'] ?? null) ? $config['formula_checks'] : [],
            'validation_checks' => is_array($config['validation_checks'] ?? null) ? $config['validation_checks'] : [],
            'errors' => $errors,
            'warnings' => is_array($config['warnings'] ?? null) ? $config['warnings'] : [],
        ]);
    }

    public static function validate_all(): array {
        $results = [];
        foreach (self::list() as $calculator) {
            $results[] = self::validate((string) $calculator['slug']);
        }
        $failed = count(array_filter($results, static fn($r) => empty($r['syntax_ok']) || !empty($r['errors'])));
        return [
            'total' => count($results),
            'passed' => count($results) - $failed,
            'failed' => $failed,
            'results' => $results,
        ];
    }

    public static function test(string $slug, array $input = []): array {
        $slug = sanitize_title($slug);
        $result = apply_filters('wpgptvibe_calculator_test', null, $slug, $input);
        if (!is_array($result)) {
            throw new RuntimeException('Calculator provider does not expose deterministic tests for this calculator.');
        }
        return $result;
    }

    public static function batch_update(array $items): array {
        $results = [];
        foreach (array_slice($items, 0, 100) as $index => $item) {
            try {
                $results[] = [
                    'index' => $index,
                    'ok' => true,
                    'data' => self::update((string) ($item['slug'] ?? ''), is_array($item['config'] ?? null) ? $item['config'] : []),
                ];
            } catch (Throwable $e) {
                $results[] = ['index' => $index, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return ['total' => count($results), 'results' => $results];
    }

    private static function normalize_validation(string $slug, array $data): array {
        return [
            'slug' => $slug,
            'config_found' => (bool) ($data['config_found'] ?? true),
            'syntax_ok' => (bool) ($data['syntax_ok'] ?? true),
            'inputs' => array_values((array) ($data['inputs'] ?? [])),
            'outputs' => array_values((array) ($data['outputs'] ?? [])),
            'formula_checks' => array_values((array) ($data['formula_checks'] ?? [])),
            'validation_checks' => array_values((array) ($data['validation_checks'] ?? [])),
            'errors' => array_values(array_map('strval', (array) ($data['errors'] ?? []))),
            'warnings' => array_values(array_map('strval', (array) ($data['warnings'] ?? []))),
        ];
    }
}

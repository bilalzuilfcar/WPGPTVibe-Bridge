<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WPGPTVibe_Content_Manager {
    public static function list(string $post_type = 'page', array $filters = []): array {
        $post_type = self::assert_post_type($post_type);
        $args = [
            'post_type' => $post_type,
            'post_status' => $filters['status'] ?? ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page' => min(200, max(1, (int) ($filters['per_page'] ?? 100))),
            'orderby' => 'modified',
            'order' => 'DESC',
            's' => sanitize_text_field((string) ($filters['search'] ?? '')),
        ];

        if (isset($filters['parent']) && $filters['parent'] !== '') {
            $args['post_parent'] = (int) $filters['parent'];
        }
        if (!empty($filters['slug'])) {
            $args['name'] = sanitize_title((string) $filters['slug']);
        }

        $query = new WP_Query($args);
        return array_map([self::class, 'normalize_post'], $query->posts);
    }

    public static function get(int $id): array {
        $post = get_post($id);
        if (!$post instanceof WP_Post) {
            throw new RuntimeException('Content not found.');
        }
        self::assert_post_type($post->post_type);
        return self::normalize_post($post, true);
    }

    public static function create(string $post_type, array $data): array {
        $post_type = self::assert_post_type($post_type);
        $postarr = self::map_fields($data);
        $postarr['post_type'] = $post_type;
        $postarr['post_status'] = sanitize_key((string) ($data['status'] ?? 'draft')) ?: 'draft';

        $id = wp_insert_post($postarr, true);
        if (is_wp_error($id)) {
            throw new RuntimeException($id->get_error_message());
        }

        self::apply_extras((int) $id, $data);
        WPGPTVibe_Audit_Log::record('content_create', $post_type . ':' . $id, 'success');
        return self::get((int) $id);
    }

    public static function update(int $id, array $data): array {
        $post = get_post($id);
        if (!$post instanceof WP_Post) {
            throw new RuntimeException('Content not found.');
        }
        self::assert_post_type($post->post_type);
        $postarr = self::map_fields($data);
        $postarr['ID'] = $id;

        $updated = wp_update_post($postarr, true);
        if (is_wp_error($updated)) {
            throw new RuntimeException($updated->get_error_message());
        }

        self::apply_extras($id, $data);
        WPGPTVibe_Audit_Log::record('content_update', $post->post_type . ':' . $id, 'success');
        return self::get($id);
    }

    public static function delete(int $id, bool $force = false): array {
        $post = get_post($id);
        if (!$post instanceof WP_Post) {
            throw new RuntimeException('Content not found.');
        }
        self::assert_post_type($post->post_type);
        $deleted = wp_delete_post($id, $force);
        if (!$deleted) {
            throw new RuntimeException('Unable to delete content.');
        }
        WPGPTVibe_Audit_Log::record('content_delete', $post->post_type . ':' . $id, 'success', ['meta' => ['force' => $force ? '1' : '0']]);
        return ['id' => $id, 'deleted' => true, 'force' => $force];
    }

    public static function batch_update(array $items): array {
        $results = [];
        foreach (array_slice($items, 0, 100) as $index => $item) {
            try {
                $results[] = ['index' => $index, 'ok' => true, 'data' => self::update((int) ($item['id'] ?? 0), is_array($item['data'] ?? null) ? $item['data'] : [])];
            } catch (Throwable $e) {
                $results[] = ['index' => $index, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return ['total' => count($results), 'results' => $results];
    }

    public static function public_post_types(): array {
        $objects = get_post_types(['public' => true, 'show_ui' => true], 'objects');
        unset($objects['attachment']);
        return array_map(static fn($o) => ['name' => $o->name, 'label' => $o->label], array_values($objects));
    }

    private static function assert_post_type(string $post_type): string {
        $post_type = sanitize_key($post_type);
        $object = get_post_type_object($post_type);
        if (!$object || !$object->public || !$object->show_ui || $post_type === 'attachment') {
            throw new RuntimeException('Post type is not allowed.');
        }
        return $post_type;
    }

    private static function map_fields(array $data): array {
        $map = [
            'title' => 'post_title',
            'slug' => 'post_name',
            'content' => 'post_content',
            'excerpt' => 'post_excerpt',
            'parent' => 'post_parent',
            'status' => 'post_status',
            'date' => 'post_date',
            'date_gmt' => 'post_date_gmt',
        ];
        $out = [];
        foreach ($map as $input => $wp_key) {
            if (!array_key_exists($input, $data)) {
                continue;
            }
            $value = $data[$input];
            if ($input === 'parent') {
                $value = (int) $value;
            } elseif ($input === 'status') {
                $value = sanitize_key((string) $value);
            } elseif ($input === 'slug') {
                $value = sanitize_title((string) $value);
            } else {
                $value = wp_kses_post((string) $value);
            }
            $out[$wp_key] = $value;
        }
        return $out;
    }

    private static function apply_extras(int $id, array $data): void {
        if (array_key_exists('template', $data)) {
            update_post_meta($id, '_wp_page_template', sanitize_text_field((string) $data['template']));
        }
        if (array_key_exists('featured_media', $data)) {
            $media = (int) $data['featured_media'];
            if ($media > 0) {
                set_post_thumbnail($id, $media);
            } else {
                delete_post_thumbnail($id);
            }
        }
    }

    private static function normalize_post(WP_Post $post, bool $full = false): array {
        $data = [
            'id' => $post->ID,
            'post_type' => $post->post_type,
            'status' => $post->post_status,
            'title' => get_the_title($post),
            'slug' => $post->post_name,
            'parent' => (int) $post->post_parent,
            'modified_gmt' => $post->post_modified_gmt,
            'permalink' => get_permalink($post),
            'template' => (string) get_post_meta($post->ID, '_wp_page_template', true),
            'featured_media' => (int) get_post_thumbnail_id($post->ID),
        ];
        if ($full) {
            $data['content'] = $post->post_content;
            $data['excerpt'] = $post->post_excerpt;
            $data['date'] = $post->post_date;
            $data['date_gmt'] = $post->post_date_gmt;
        }
        return $data;
    }
}

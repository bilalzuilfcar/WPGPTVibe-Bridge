<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Human-in-the-loop publishing controls.
 *
 * API operations may prepare drafts, but only WordPress administrators
 * can authorize publication. API credentials cannot approve their own work.
 */
final class WPGPTVibe_Review_Gate {
    private const THEME_APPROVAL_OPTION = 'wpgptvibe_theme_publish_approval';

    public static function assert_draft_status(array $data): void {
        if (isset($data['status']) && !in_array(sanitize_key((string) $data['status']), ['draft', 'pending', 'auto-draft'], true)) {
            throw new RuntimeException('Manual review required. API content must remain draft or pending; publish in WordPress admin after review.');
        }
    }

    public static function assert_unpublished(int $id): void {
        $post = get_post($id);
        if (!$post instanceof WP_Post) {
            throw new RuntimeException('Content not found.');
        }
        if (!in_array($post->post_status, ['draft', 'pending', 'auto-draft'], true)) {
            throw new RuntimeException('Manual review required. Published content cannot be changed through the Bridge API. Prepare a separate draft for review.');
        }
    }

    public static function theme_fingerprint(): string {
        $draft = WPGPTVibe_Theme_Manager::draft_status();
        if (empty($draft['exists']) || empty($draft['stylesheet'])) {
            throw new RuntimeException('No draft theme available for review.');
        }
        $theme = wp_get_theme(sanitize_key((string) $draft['stylesheet']));
        if (!$theme->exists()) {
            throw new RuntimeException('Draft theme not found.');
        }
        $root = realpath($theme->get_stylesheet_directory());
        if (!$root) {
            throw new RuntimeException('Draft theme path unavailable.');
        }
        $entries = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                continue;
            }
            $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root)));
            $entries[$path] = hash_file('sha256', $file->getPathname());
        }
        ksort($entries, SORT_STRING);
        return hash('sha256', wp_json_encode($entries));
    }

    public static function approve_theme(): void {
        $fingerprint = self::theme_fingerprint();
        update_option(self::THEME_APPROVAL_OPTION, [
            'fingerprint' => $fingerprint,
            'approved_by' => get_current_user_id(),
            'expires' => time() + 15 * MINUTE_IN_SECONDS,
        ], false);
        WPGPTVibe_Audit_Log::record('theme_review_approved', 'draft_theme', 'success');
    }

    public static function consume_theme_approval(): void {
        $approval = get_option(self::THEME_APPROVAL_OPTION, []);
        if (!is_array($approval) || empty($approval['expires']) || (int) $approval['expires'] < time()
            || empty($approval['approved_by']) || empty($approval['fingerprint'])
            || !hash_equals((string) $approval['fingerprint'], self::theme_fingerprint())) {
            delete_option(self::THEME_APPROVAL_OPTION);
            throw new RuntimeException('A WordPress administrator must review and approve this exact draft theme in Tools > WPGPTVibe before API publication. Approval lasts 15 minutes and expires if draft files change.');
        }
        // One-time approval. Publication cannot reuse it.
        delete_option(self::THEME_APPROVAL_OPTION);
    }

    public static function theme_approval_status(): array {
        $approval = get_option(self::THEME_APPROVAL_OPTION, []);
        return [
            'approved' => is_array($approval) && !empty($approval['expires']) && (int) $approval['expires'] >= time(),
            'expires' => is_array($approval) ? (int) ($approval['expires'] ?? 0) : 0,
        ];
    }
}

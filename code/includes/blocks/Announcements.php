<?php
// includes/blocks/Announcements.php
//
// Centre-column block: recent pinned threads across the forum. Falls back to
// a friendly empty state if no pinned threads exist.
// Maps to k_blocks.html_file_name = 'block_announcements.html'.

namespace App\Blocks;

class Announcements
{
    public function render(array $context): string
    {
        $limit = (int)k_var('k_announcements_to_display', 5);
        if ($limit < 1)  $limit = 5;
        if ($limit > 20) $limit = 20;

        // Announcement = any thread that contains at least one post an
        // admin has flagged with posts.type = 1. EXISTS deduplicates
        // the thread when multiple posts are tagged inside it.
        $stmt = db()->prepare("
            SELECT t.id, t.title, t.slug, t.created_at,
                   u.id AS user_id, u.username,
                   g.colour AS group_colour,
                   f.name AS forum_name, f.slug AS forum_slug
            FROM threads t
            JOIN      users   u ON u.id = t.user_id
            LEFT JOIN `groups` g ON g.id = u.group_id
            JOIN      forums  f ON f.id = t.forum_id
            WHERE t.is_active = 1
              AND EXISTS (
                  SELECT 1 FROM posts p
                  WHERE p.thread_id = t.id
                    AND p.is_active = 1
                    AND p.type = 1
              )
            ORDER BY t.created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $announcements = $stmt->fetchAll();

        return \View::make('blocks.announcements', [
            'blockTitle'    => $context['block_title'] ?? 'Announcements',
            'blockImage'    => $context['block_image'] ?? '',
            'announcements' => $announcements,
        ]);
    }
}

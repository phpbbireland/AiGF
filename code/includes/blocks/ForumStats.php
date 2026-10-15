<?php
// includes/blocks/ForumStats.php
//
// Right-column block: members / threads / posts / newest-user summary.
// Maps to the html_file_name 'block_forum_stats.html' — there is NO existing
// k_blocks row for this. Add one via /admin/blocks.php after deploying:
//   Position: Right column
//   Implementation: ForumStats (block_forum_stats.html)
//   Visible on pages: portal
// Then remove the inline stats card from templates/views/forum/portal.php.

namespace App\Blocks;

class ForumStats
{
    public function render(array $context): string
    {
        $stats = db()->query("SELECT
            (SELECT COUNT(*) FROM users   WHERE is_active = 1) AS total_users,
            (SELECT COUNT(*) FROM threads WHERE is_active = 1) AS total_threads,
            (SELECT COUNT(*) FROM posts   WHERE is_active = 1) AS total_posts,
            (SELECT username FROM users ORDER BY created_at DESC LIMIT 1) AS newest_user
        ")->fetch();

        return \View::make('blocks.forum_stats', [
            'blockTitle' => $context['block_title'] ?? 'Forum Stats',
            'blockImage' => $context['block_image'] ?? '',
            'stats'      => $stats ?: [],
        ]);
    }
}

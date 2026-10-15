<?php
// includes/blocks/RecentTopicsWide.php
//
// Centre-column block: list of the most recent topics across the forum.
// Maps to k_blocks.html_file_name = 'block_recent_topics_wide.html'.
//
// Config via k_vars:
//   k_recent_topics_to_display — how many to show (default 10, capped 1..50)
//   k_recent_days              — only include threads from the last N days
//                                (default 7, capped 1..365; set 0 to disable)

namespace App\Blocks;

class RecentTopicsWide
{
    public function render(array $context): string
    {
        $limit = (int)k_var('k_recent_topics_to_display', 10);
        if ($limit < 1)  $limit = 10;
        if ($limit > 50) $limit = 50;

        // Time window — k_recent_days. 0 (or below) disables the filter.
        $days = (int)k_var('k_recent_days', 7);
        if ($days > 365) $days = 365;
        $dateClause = $days > 0
            ? 'AND t.created_at >= (NOW() - INTERVAL ? DAY)'
            : '';

        // Row data: enough to render the 6-column row (icon / title / avatar /
        // author / replies / posted). The first post's `type` is what defines
        // the thread's category for the title colouring (1 = announcement,
        // 2 = news); `t.is_pinned` paints sticky threads green. We pull the
        // first post via a LEFT JOIN on (thread_id, is_first_post=1) — LEFT
        // because a freshly-created thread row could (briefly) have no rows in
        // posts, and we'd rather render "untyped" than drop it.
        $stmt = db()->prepare("
            SELECT t.id, t.title, t.slug, t.reply_count, t.created_at, t.is_pinned,
                   u.id AS user_id, u.username, u.avatar,
                   g.colour AS group_colour,
                   p.type   AS post_type
            FROM threads t
            JOIN      users   u ON u.id = t.user_id
            LEFT JOIN `groups` g ON g.id = u.group_id
            LEFT JOIN posts   p ON p.thread_id = t.id AND p.is_first_post = 1
            WHERE t.is_active = 1
              {$dateClause}
            ORDER BY t.created_at DESC
            LIMIT ?
        ");
        $paramIdx = 1;
        if ($days > 0) {
            $stmt->bindValue($paramIdx++, $days,  \PDO::PARAM_INT);
        }
        $stmt->bindValue($paramIdx, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $topics = $stmt->fetchAll();

        return \View::make('blocks.recent_topics_wide', [
            'blockTitle' => $context['block_title'] ?? 'Recent Topics',
            'blockImage' => $context['block_image'] ?? '',
            'topics'     => $topics,
        ]);
    }
}

<?php
// includes/blocks/RecentThreads.php
//
// Sidebar block: compact list of the N most recent threads — just title,
// author, and timestamp. Designed for left/right columns.
//
// Differs from RecentTopicsWide (centre-column, wide rows with reply/view
// counts and forum badges) by being a tight sidebar-card.
//
// Config via k_vars:
//   k_recent_threads_to_display — how many to show (default 10, capped 1..30)
//   k_recent_days               — only include threads from the last N days
//                                 (default 7, capped 1..365; set 0 to disable)
//
// Maps to k_blocks.html_file_name = 'block_recent_threads.html'.

namespace App\Blocks;

class RecentThreads
{
    public function render(array $context): string
    {
        $limit = (int)k_var('k_recent_threads_to_display', 10);
        if ($limit < 1)  $limit = 10;
        if ($limit > 30) $limit = 30;

        // Time window — k_recent_days. 0 (or below) disables the filter.
        $days = (int)k_var('k_recent_days', 7);
        if ($days > 365) $days = 365;
        $dateClause = $days > 0
            ? 'AND t.created_at >= (NOW() - INTERVAL ? DAY)'
            : '';

        $stmt = db()->prepare("
            SELECT t.id, t.title, t.slug, t.created_at,
                   u.id AS user_id, u.username,
                   g.colour AS group_colour
            FROM threads t
            JOIN      users   u ON u.id = t.user_id
            LEFT JOIN `groups` g ON g.id = u.group_id
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
        $threads = $stmt->fetchAll();

        return \View::make('blocks.recent_threads', [
            'blockTitle' => $context['block_title'] ?? 'Recent Threads',
            'blockImage' => $context['block_image'] ?? '',
            'threads'    => $threads,
        ]);
    }
}

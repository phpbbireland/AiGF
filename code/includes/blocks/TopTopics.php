<?php
// includes/blocks/TopTopics.php
//
// Right-column block: "Most Active Topics" — top threads by reply count,
// then view count as a tiebreaker.
// Maps to k_blocks.html_file_name = 'block_top_topics.html'.

namespace App\Blocks;

class TopTopics
{
    public function render(array $context): string
    {
        $limit = (int)k_var('k_top_topics_to_display', 5);
        if ($limit < 1)  $limit = 5;
        if ($limit > 50) $limit = 50;

        $stmt = db()->prepare("
            SELECT t.id, t.title, t.slug, t.reply_count, t.view_count,
                   f.name AS forum_name, f.slug AS forum_slug
            FROM threads t
            JOIN forums f ON f.id = t.forum_id
            WHERE t.is_active = 1
            ORDER BY t.reply_count DESC, t.view_count DESC, t.created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $topics = $stmt->fetchAll();

        return \View::make('blocks.top_topics', [
            'blockTitle' => $context['block_title'] ?? 'Most Active Topics',
            'blockImage' => $context['block_image'] ?? '',
            'topics'     => $topics,
        ]);
    }
}

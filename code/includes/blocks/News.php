<?php
// includes/blocks/News.php
//
// Centre-column block: latest posts feed with a short excerpt.
// Maps to k_blocks.html_file_name = 'block_news.html'.
//
// This differs from RecentTopicsWide — which lists new THREADS by title —
// by rendering the latest POSTS with a trimmed excerpt of each post body,
// so activity inside existing threads also surfaces.
//
// Optional: set k_vars.k_news_forum_slug to limit to a single forum (e.g.
// an announcements or news forum). Leave empty to show site-wide activity.

namespace App\Blocks;

class News
{
    public function render(array $context): string
    {
        $limit       = (int)k_var('k_news_items_to_display', 5);
        $excerptLen  = (int)k_var('k_news_item_max_length', 180);
        $newsSlug    = trim((string)k_var('k_news_forum_slug', ''));

        if ($limit < 1)        $limit = 5;
        if ($limit > 20)       $limit = 20;
        if ($excerptLen < 40)  $excerptLen = 40;
        if ($excerptLen > 800) $excerptLen = 800;

        $where  = "WHERE p.is_active = 1 AND t.is_active = 1 AND p.type = 2";
        $params = [];
        if ($newsSlug !== '') {
            $where   .= " AND f.slug = ?";
            $params[] = $newsSlug;
        }

        $sql = "
            SELECT p.id AS post_id, p.content, p.created_at,
                   t.id AS thread_id, t.title AS thread_title, t.slug AS thread_slug,
                   u.id AS user_id, u.username, u.avatar,
                   g.colour AS group_colour,
                   f.name AS forum_name, f.slug AS forum_slug
            FROM posts p
            JOIN      threads t ON t.id = p.thread_id
            JOIN      users   u ON u.id = p.user_id
            LEFT JOIN `groups` g ON g.id = u.group_id
            JOIN      forums  f ON f.id = t.forum_id
            $where
            ORDER BY p.created_at DESC
            LIMIT $limit
        ";
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $posts = $stmt->fetchAll();

        // Build a plain-text excerpt for each post (strip HTML, clamp length).
        foreach ($posts as &$p) {
            $plain = trim(strip_tags((string)$p['content']));
            if (function_exists('mb_strlen') && mb_strlen($plain) > $excerptLen) {
                $plain = rtrim(mb_substr($plain, 0, $excerptLen)) . '…';
            } elseif (strlen($plain) > $excerptLen) {
                $plain = rtrim(substr($plain, 0, $excerptLen)) . '…';
            }
            $p['excerpt'] = $plain;
        }
        unset($p);

        return \View::make('blocks.news', [
            'blockTitle' => $context['block_title'] ?? 'News',
            'blockImage' => $context['block_image'] ?? '',
            'posts'      => $posts,
        ]);
    }
}

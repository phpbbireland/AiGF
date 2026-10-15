<?php
// includes/blocks/TopPosters.php
//
// Right-column block: leaderboard of top-posting members.
// Maps to k_blocks.html_file_name = 'block_top_posters.html'.
//
// The view calls $view->flag($u['country_code']) which bottoms out in
// country_flag() from includes/countries.php. config.php does NOT auto-load
// that file, so we require it here — otherwise portal.php (which never
// includes countries.php directly) would hit an undefined-function Error
// mid-render, BlockEngine would swallow it, and the orphaned ob_start buffer
// would leak the partially-rendered block into the response. Pulling the
// helper in at the top of the dispatcher keeps the block self-contained.

namespace App\Blocks;

require_once dirname(__DIR__) . '/countries.php';

class TopPosters
{
    public function render(array $context): string
    {
        $limit = (int)k_var('k_top_posters_to_display', 10);
        if ($limit < 1)  $limit = 10;
        if ($limit > 50) $limit = 50;

        $stmt = db()->prepare("
            SELECT u.id, u.username, u.post_count, u.avatar, u.country_code,
                   g.colour AS group_colour
            FROM users u
            LEFT JOIN `groups` g ON g.id = u.group_id
            WHERE u.is_active = 1 AND u.is_banned = 0
            ORDER BY u.post_count DESC, u.username ASC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $users = $stmt->fetchAll();

        return \View::make('blocks.top_posters', [
            'blockTitle' => $context['block_title'] ?? 'Top Posters',
            'blockImage' => $context['block_image'] ?? '',
            'users'      => $users,
        ]);
    }
}

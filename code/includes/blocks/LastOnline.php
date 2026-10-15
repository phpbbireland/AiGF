<?php
// includes/blocks/LastOnline.php
//
// Left-column block: users seen within a broader window than OnlineUsers.
// OnlineUsers covers "currently online" (k_last_online_max minutes, typically
// 10–15). LastOnline widens to the last k_last_online_hours hours — so it
// shows people who checked in earlier today / this session.
// Maps to k_blocks.html_file_name = 'block_last_online.html'.

namespace App\Blocks;

class LastOnline
{
    public function render(array $context): string
    {
        $hours = (int)k_var('k_last_online_hours', 24);
        if ($hours < 1)   $hours = 1;
        if ($hours > 720) $hours = 720; // clamp to a month

        $stmt = db()->prepare("
            SELECT u.id, u.username, u.last_seen,
                   g.colour AS group_colour
            FROM users u
            LEFT JOIN `groups` g ON g.id = u.group_id
            WHERE u.is_active = 1
              AND u.is_banned = 0
              AND u.last_seen IS NOT NULL
              AND u.last_seen >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            ORDER BY u.last_seen DESC
            LIMIT 50
        ");
        $stmt->bindValue(1, $hours, \PDO::PARAM_INT);
        $stmt->execute();
        $users = $stmt->fetchAll();

        return \View::make('blocks.last_online', [
            'blockTitle' => $context['block_title'] ?? 'Last Online',
            'blockImage' => $context['block_image'] ?? '',
            'users'      => $users,
            'hours'      => $hours,
        ]);
    }
}

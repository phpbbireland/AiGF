<?php
// includes/blocks/OnlineUsers.php
//
// Left-column block: users seen within the last N minutes (N = k_vars.k_last_online_max).
// Maps to k_blocks.html_file_name = 'block_online_users.html'.

namespace App\Blocks;

class OnlineUsers
{
    public function render(array $context): string
    {
        $minutes = (int)k_var('k_last_online_max', 10);
        if ($minutes < 1)    $minutes = 1;
        if ($minutes > 1440) $minutes = 1440;

        $stmt = db()->prepare("
            SELECT u.id, u.username, u.last_seen,
                   g.colour AS group_colour
            FROM users u
            LEFT JOIN `groups` g ON g.id = u.group_id
            WHERE u.is_active = 1
              AND u.is_banned = 0
              AND u.last_seen >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
            ORDER BY u.last_seen DESC
            LIMIT 30
        ");
        $stmt->bindValue(1, $minutes, \PDO::PARAM_INT);
        $stmt->execute();
        $users = $stmt->fetchAll();

        return \View::make('blocks.online_users', [
            'blockTitle'    => $context['block_title'] ?? 'Online now',
            'blockImage'    => $context['block_image'] ?? '',
            'users'         => $users,
            'lastOnlineMax' => $minutes,
        ]);
    }
}

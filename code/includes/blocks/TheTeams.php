<?php
// includes/blocks/TheTeams.php
//
// Right-column block: admin + moderator roster with avatars.
// Maps to k_blocks.html_file_name = 'block_the_teams.html'.

namespace App\Blocks;

class TheTeams
{
    public function render(array $context): string
    {
        $stmt = db()->query("
            SELECT u.id, u.username, u.avatar, u.role,
                   g.colour AS group_colour
            FROM users u
            LEFT JOIN `groups` g ON g.id = u.group_id
            WHERE u.is_active = 1
              AND u.is_banned = 0
              AND u.role IN ('admin', 'moderator')
            ORDER BY FIELD(u.role, 'admin', 'moderator'), u.username ASC
            LIMIT 30
        ");
        $users = $stmt->fetchAll();

        return \View::make('blocks.the_teams', [
            'blockTitle' => $context['block_title'] ?? 'The Team',
            'blockImage' => $context['block_image'] ?? '',
            'users'      => $users,
        ]);
    }
}

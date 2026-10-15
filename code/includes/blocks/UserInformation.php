<?php
// includes/blocks/UserInformation.php
//
// Right-column block: "your account" summary for the logged-in user. Nothing
// to show for guests, so render returns an empty string (visibility gate at
// the k_blocks level can also hide it by setting view_all = 0).
// Maps to k_blocks.html_file_name = 'block_user_information.html'.

namespace App\Blocks;

class UserInformation
{
    public function render(array $context): string
    {
        $user = $context['user'] ?? null;
        if (!$user) return '';

        return \View::make('blocks.user_information', [
            'blockTitle' => $context['block_title'] ?? 'Your account',
            'blockImage' => $context['block_image'] ?? '',
            'user'       => $user,
        ]);
    }
}

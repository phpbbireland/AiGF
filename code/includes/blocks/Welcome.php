<?php
// includes/blocks/Welcome.php
//
// Centre-column block: site greeting card with login/register CTA for guests.
// Maps to k_blocks.html_file_name = 'block_welcome.html'.

namespace App\Blocks;

class Welcome
{
    public function render(array $context): string
    {

        $user = $context['user'] ?? null;
        if (!$user) return '';

        return \View::make('blocks.welcome', [
            'blockTitle' => $context['block_title'] ?? 'Welcome',
            'blockImage' => $context['block_image'] ?? '',
            'user'       => $context['user'] ?? null,
            'siteName'   => SITE_NAME,
            'siteDesc'   => SITE_DESC,
        ]);
    }
}

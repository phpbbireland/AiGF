<?php
// includes/blocks/Builder.php
//
// Left-column block: sponsor / ad slot. No data query — the partial is the
// content. Admins customise by editing templates/views/blocks/block_builder.php.
// The corresponding k_blocks row defaults to view_all = 0 so guests don't
// see it; flip that in the admin CP if you want public visibility.
// Maps to k_blocks.html_file_name = 'block_builder.html'.

namespace App\Blocks;

class Builder
{
    public function render(array $context): string
    {
        return \View::make('blocks.builder', [
            'blockTitle' => $context['block_title'] ?? 'Sponsor',
            'blockImage' => $context['block_image'] ?? '',
            'contents'   => "Some info",
        ]);
    }
}

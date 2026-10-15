<?php
// includes/blocks/Search.php
//
// Left-column block: a compact search form that posts to /search.php.
// Maps to k_blocks.html_file_name = 'block_search.html'.
//
// No DB query — the form itself is stateless markup.

namespace App\Blocks;

class Search
{
    public function render(array $context): string
    {
        return \View::make('blocks.search', [
            'blockTitle' => $context['block_title'] ?? 'Search',
            'blockImage' => $context['block_image'] ?? '',
        ]);
    }
}

<?php
// includes/blocks/Clock.php
//
// Right-column block: a small live clock. Pure client-side — no DB query.
// Maps to k_blocks.html_file_name = 'block_clock.html'.

namespace App\Blocks;

class Clock
{
    public function render(array $context): string
    {
        return \View::make('blocks.clock', [
            'blockTitle' => $context['block_title'] ?? 'Clock',
            'blockImage' => $context['block_image'] ?? '',
            'blockId'    => (int)($context['block_id'] ?? 0),
        ]);
    }
}

<?php
// includes/blocks/Links.php
//
// Right-column block: partner/project image links from k_links.
// Maps to k_blocks.html_file_name = 'block_links.html'.

namespace App\Blocks;

class Links
{
    public function render(array $context): string
    {
        $limit = (int)k_var('k_links_to_display', 5);
        if ($limit < 1)  $limit = 5;
        if ($limit > 50) $limit = 50;

        $stmt = db()->prepare("
            SELECT link, url, image, active, open_in_tab
            FROM k_links
            WHERE active = 1
            ORDER BY link ASC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $links = $stmt->fetchAll();

        return \View::make('blocks.image_links', [
            'blockTitle' => $context['block_title'] ?? 'Links',
            'blockImage' => $context['block_image'] ?? '',
            'imageLinks' => $links,
        ]);
    }
}

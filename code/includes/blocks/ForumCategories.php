<?php
// includes/blocks/ForumCategories.php
//
// Left-column block: categories → forums tree as a compact navigator.
// Maps to k_blocks.html_file_name = 'block_forum_categories.html'.
//
// Produces a grouped list: each category is a section heading, each forum
// below it is a link to /forum.php?slug=... — handy as a sidebar jump list.

namespace App\Blocks;

class ForumCategories
{
    public function render(array $context): string
    {
        $rows = db()->query("
            SELECT c.id AS cat_id, c.name AS cat_name, c.icon AS cat_icon, c.sort_order AS cat_sort,
                   f.id AS forum_id, f.name AS forum_name, f.slug AS forum_slug,
                   f.icon AS forum_icon, f.thread_count, f.sort_order AS forum_sort
            FROM categories c
            LEFT JOIN forums f
                   ON f.category_id = c.id AND f.is_active = 1
            WHERE c.is_active = 1
            ORDER BY c.sort_order ASC, c.id ASC, f.sort_order ASC, f.id ASC
        ")->fetchAll();

        // Group rows by category
        $tree = [];
        foreach ($rows as $r) {
            $cid = (int)$r['cat_id'];
            if (!isset($tree[$cid])) {
                $tree[$cid] = [
                    'id'     => $cid,
                    'name'   => $r['cat_name'],
                    'icon'   => $r['cat_icon'] ?? '',
                    'forums' => [],
                ];
            }
            if ($r['forum_id'] !== null) {
                $tree[$cid]['forums'][] = [
                    'id'           => (int)$r['forum_id'],
                    'name'         => $r['forum_name'],
                    'slug'         => $r['forum_slug'],
                    'icon'         => $r['forum_icon'] ?? '',
                    'thread_count' => (int)$r['thread_count'],
                ];
            }
        }

        return \View::make('blocks.forum_categories', [
            'blockTitle' => $context['block_title'] ?? 'Categories',
            'blockImage' => $context['block_image'] ?? '',
            'tree'       => array_values($tree),
        ]);
    }
}

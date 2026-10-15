<?php
// ============================================================================
// index.php — forum home: category / forum listing with block-driven sidebars
// ============================================================================
// The centre column (flat forum tree) is unique to this page and stays inline.
// The left and right sidebars are driven by k_blocks via BlockEngine, same
// dispatcher that powers portal.php. To change what appears in those columns
// edit k_blocks rows in the admin CP — not this file.
// ============================================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageId = BlockEngine::pageId('index');

// ── Centre column data: categories + forums, grouped ────────────────────────
$categories = db()->query("
    SELECT c.*, COUNT(f.id) as forum_count
    FROM categories c
    LEFT JOIN forums f ON f.category_id = c.id AND f.is_active = 1
    WHERE c.is_active = 1
    GROUP BY c.id
    ORDER BY c.sort_order ASC
")->fetchAll();

$forums = db()->query("
    SELECT f.*,
        t.title as last_thread_title, t.slug as last_thread_slug,
        u.username as last_post_user,
        p.created_at as last_post_at
    FROM forums f
    LEFT JOIN posts p   ON p.id = f.last_post_id
    LEFT JOIN threads t ON t.id = p.thread_id
    LEFT JOIN users u   ON u.id = p.user_id
    WHERE f.is_active = 1
    ORDER BY f.category_id, f.sort_order ASC
")->fetchAll();

$forumsByCategory = [];
foreach ($forums as $f) {
    $forumsByCategory[$f['category_id']][] = $f;
}

// ── Sidebars: rendered by BlockEngine ───────────────────────────────────────
$leftCol  = BlockEngine::render(BlockEngine::POS_LEFT,  $pageId);
$rightCol = BlockEngine::render(BlockEngine::POS_RIGHT, $pageId);

View::renderWithLayout('forum.index', [
    'pageTitle'        => null,
    'categories'       => $categories,
    'forumsByCategory' => $forumsByCategory,
    'leftCol'          => $leftCol,
    'rightCol'         => $rightCol,
]);

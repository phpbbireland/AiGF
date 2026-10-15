<?php
// ============================================================================
// portal.php — block-driven portal landing page
// ============================================================================
// Three-column layout whose content is driven entirely by the k_blocks table
// via BlockEngine. To change what appears here, edit k_blocks / k_menus rows
// — not this file.
//
// Blocks are dispatched by k_blocks.html_file_name (e.g. "block_top_posters.html"
// -> App\Blocks\TopPosters in includes/blocks/TopPosters.php). See
// includes/BlockEngine.php for the full contract.
// ============================================================================

require_once __DIR__ . '/config.php';

$pageId = BlockEngine::pageId('portal');

// Render each column. Missing block implementations are skipped silently so
// a stray k_blocks row can't take the page down.
$leftCol   = BlockEngine::render(BlockEngine::POS_LEFT,   $pageId);
$centreCol = BlockEngine::render(BlockEngine::POS_CENTRE, $pageId);
$rightCol  = BlockEngine::render(BlockEngine::POS_RIGHT,  $pageId);

// Overall site stats — still rendered inline for now (no corresponding k_blocks
// row exists yet). Move into a ForumStats block once an admin-CP can insert it.
$stats = db()->query("SELECT
    (SELECT COUNT(*) FROM users   WHERE is_active = 1) AS total_users,
    (SELECT COUNT(*) FROM threads WHERE is_active = 1) AS total_threads,
    (SELECT COUNT(*) FROM posts   WHERE is_active = 1) AS total_posts,
    (SELECT username FROM users ORDER BY created_at DESC LIMIT 1) AS newest_user
")->fetch();

View::renderWithLayout('forum.portal', [
    'pageTitle' => 'Portal',
    'leftCol'   => $leftCol,
    'centreCol' => $centreCol,
    'rightCol'  => $rightCol,
    'stats'     => $stats,
]);

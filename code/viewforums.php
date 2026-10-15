<?php
// ============================================================================
// viewforums.php — per-category forum listing with block-driven sidebars
// ============================================================================
// Centre column (the 4-column Forum / Topics / Posts / Last Post table) is
// inline and unique to this page; the left and right sidebars are driven by
// k_blocks via BlockEngine, same dispatcher that powers index.php and
// portal.php. Edit k_blocks rows in admin/blocks.php (set view_pages to
// include 13) to change what surfaces here.
//
// Modes:
//   /viewforums.php?cat=<id>   single-category view (breadcrumb shows that
//                              category, page title = category name)
//   /viewforums.php            no-arg fallback — every active category is
//                              stacked on the page, each with its own table
//
// Linked from the cat-heading on index.php and from the forum_categories
// sidebar block, both of which emit `viewforums.php?cat=<id>`.
// ============================================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageId = BlockEngine::pageId('viewforums');

// ── Resolve category (optional) ─────────────────────────────────────────────
// `?cat=<id>` filters the page to a single category; missing or 0 falls back
// to the all-categories view. Inactive categories are treated as not found
// so a stale link 404s rather than rendering an empty shell.
$catId = (int)($_GET['cat'] ?? 0);
$singleCategory = null;

if ($catId > 0) {
    $stmt = db()->prepare('SELECT * FROM categories WHERE id = ? AND is_active = 1');
    $stmt->execute([$catId]);
    $singleCategory = $stmt->fetch();
    if (!$singleCategory) {
        http_response_code(404);
        die('<p>Category not found.</p>');
    }
}

// ── Fetch categories + their forums ────────────────────────────────────────
// The query mirrors index.php so the same row data (icon/name/desc + last-post
// join) is available, but is scoped to a single category when one is selected.
if ($singleCategory) {
    $stmtCats = db()->prepare('
        SELECT c.*
        FROM categories c
        WHERE c.id = ? AND c.is_active = 1
    ');
    $stmtCats->execute([$catId]);
    $categories = $stmtCats->fetchAll();

    $stmtForums = db()->prepare("
        SELECT f.*,
            t.title as last_thread_title, t.slug as last_thread_slug,
            u.username as last_post_user,
            p.created_at as last_post_at
        FROM forums f
        LEFT JOIN posts p   ON p.id = f.last_post_id
        LEFT JOIN threads t ON t.id = p.thread_id
        LEFT JOIN users u   ON u.id = p.user_id
        WHERE f.is_active = 1 AND f.category_id = ?
        ORDER BY f.sort_order ASC, f.id ASC
    ");
    $stmtForums->execute([$catId]);
    $forums = $stmtForums->fetchAll();
} else {
    $categories = db()->query("
        SELECT c.*
        FROM categories c
        WHERE c.is_active = 1
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
}

$forumsByCategory = [];
foreach ($forums as $f) {
    $forumsByCategory[$f['category_id']][] = $f;
}

// ── Sidebars: rendered by BlockEngine ───────────────────────────────────────
$leftCol  = BlockEngine::render(BlockEngine::POS_LEFT,  $pageId);
$rightCol = BlockEngine::render(BlockEngine::POS_RIGHT, $pageId);

View::renderWithLayout('forum.viewforums', [
    'pageTitle'        => $singleCategory ? $singleCategory['name'] : 'All Forums',
    'singleCategory'   => $singleCategory,
    'categories'       => $categories,
    'forumsByCategory' => $forumsByCategory,
    'leftCol'          => $leftCol,
    'rightCol'         => $rightCol,
]);

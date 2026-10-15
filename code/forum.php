<?php
// ============================================================================
// forum.php — viewforum (single-forum thread listing) with block-driven sidebars
// ============================================================================
// The centre column (thread listing for one forum) is unique to this page and
// stays inline. The left and right sidebars are driven by k_blocks via
// BlockEngine — same dispatcher that powers index.php and portal.php. Edit
// k_blocks rows in admin/blocks.php (set view_pages to include 3) to change
// what surfaces here.
// ============================================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageId = BlockEngine::pageId('viewforum');

// ── Resolve forum from slug ─────────────────────────────────────────────────
$slug = trim($_GET['slug'] ?? '');
if (!$slug) redirect(FORUM_URL . '/index.php');

$stmt = db()->prepare('
    SELECT f.*, c.name as cat_name
    FROM forums f
    JOIN categories c ON c.id = f.category_id
    WHERE f.slug = ? AND f.is_active = 1
');
$stmt->execute([$slug]);
$forum = $stmt->fetch();
if (!$forum) { http_response_code(404); die('<p>Forum not found.</p>'); }

// ── Centre column data: paginated thread list ───────────────────────────────
$page = max(1, (int)($_GET['page'] ?? 1));

$stmtCount = db()->prepare('SELECT COUNT(*) FROM threads WHERE forum_id = ? AND is_active = 1');
$stmtCount->execute([$forum['id']]);
$total = (int)$stmtCount->fetchColumn();

$pag = paginate($total, THREADS_PER_PAGE, $page);

$stmt3 = db()->prepare("
    SELECT t.*, u.username as author,
        lp.created_at as last_post_at, lu.username as last_post_user
    FROM threads t
    JOIN users u ON u.id = t.user_id
    LEFT JOIN posts lp ON lp.id = t.last_post_id
    LEFT JOIN users lu ON lu.id = lp.user_id
    WHERE t.forum_id = ? AND t.is_active = 1
    ORDER BY t.is_pinned DESC, COALESCE(t.last_post_at, t.created_at) DESC
    LIMIT ? OFFSET ?
");
$stmt3->execute([$forum['id'], $pag['limit'], $pag['offset']]);
$threads = $stmt3->fetchAll();

// ── Sidebars: rendered by BlockEngine ───────────────────────────────────────
$leftCol  = BlockEngine::render(BlockEngine::POS_LEFT,  $pageId);
$rightCol = BlockEngine::render(BlockEngine::POS_RIGHT, $pageId);

View::renderWithLayout('forum.forum', [
    'pageTitle' => $forum['name'],
    'forum'     => $forum,
    'threads'   => $threads,
    'pag'       => $pag,
    'slug'      => $slug,
    'leftCol'   => $leftCol,
    'rightCol'  => $rightCol,
]);

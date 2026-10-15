<?php
// memberlist.php
//
// "Show All Members" — a paginated, sortable directory of every active user
// on the forum. Reachable from:
//
//   • the new "Show All Members" card on search.php
//   • the existing "Members" item in the Site Navigator menu (k_menus id=9,
//     link_to=memberlist.php), which previously 404'd because no controller
//     existed.
//
// Sort modes (?sort=…):
//   username, posts, role, last_seen, joined
// Each toggles asc/desc on repeat-click via ?dir=asc|desc.
//
// Pagination: 20 per page, ?page=N. Banned and inactive users are excluded.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/countries.php';   // country_flag() used by the view

// ── Sort whitelist ───────────────────────────────────────────────────────────
// Map URL ?sort= keys to (SQL column, default direction). The default direction
// is what a fresh click on the column should yield (e.g. clicking "Posts" first
// sorts highest-first, clicking "Joined" first sorts most-recent-first).
$SORTS = [
    'username'  => ['u.username',   'ASC'],
    'posts'     => ['u.post_count', 'DESC'],
    'role'      => ['u.role',       'DESC'],  // ENUM order is member,moderator,admin — DESC puts admins on top first
    'last_seen' => ['u.last_seen',  'DESC'],
    'joined'    => ['u.created_at', 'DESC'],
];

$sort = $_GET['sort'] ?? 'username';
if (!isset($SORTS[$sort])) $sort = 'username';

[$sortCol, $defaultDir] = $SORTS[$sort];

// Direction: explicit ?dir overrides the default for the chosen sort.
$dir = strtoupper($_GET['dir'] ?? $defaultDir);
if ($dir !== 'ASC' && $dir !== 'DESC') $dir = $defaultDir;

// Stable secondary sort by username keeps tied rows (e.g. lots of post_count=0
// members) in a predictable order across pages.
$orderBy = $sortCol . ' ' . $dir . ($sortCol === 'u.username' ? '' : ', u.username ASC');

// last_seen is nullable — ORDER BY pushes NULLs to the end on DESC and the top
// on ASC, which looks weird ("never seen" surfaces above active users). Coerce
// NULL to a sentinel so it always sinks to the bottom.
if ($sortCol === 'u.last_seen') {
    $orderBy = 'COALESCE(u.last_seen, "1970-01-01") ' . $dir . ', u.username ASC';
}

// ── Pagination ───────────────────────────────────────────────────────────────
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$total = (int) db()->query("
    SELECT COUNT(*) FROM users u
    WHERE u.is_active = 1 AND u.is_banned = 0
")->fetchColumn();

$pag = paginate($total, $perPage, $page);

$rs = db()->prepare("
    SELECT u.id, u.username, u.role, u.avatar, u.bio,
           u.country_code, u.city, u.post_count, u.created_at, u.last_seen
      FROM users u
     WHERE u.is_active = 1 AND u.is_banned = 0
     ORDER BY $orderBy
     LIMIT ? OFFSET ?
");
$rs->execute([$pag['limit'], $pag['offset']]);
$members = $rs->fetchAll();

View::renderWithLayout('forum.memberlist', [
    'pageTitle' => 'All Members',
    'members'   => $members,
    'total'     => $total,
    'pag'       => $pag,
    'sort'      => $sort,
    'dir'       => $dir,
]);

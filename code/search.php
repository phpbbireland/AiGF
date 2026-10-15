<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/countries.php';

$q       = trim($_GET['q'] ?? '');
$type    = $_GET['type'] ?? 'posts';
$forumId = (int)($_GET['forum'] ?? 0);
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

if (!in_array($type, ['posts','threads','users','my_posts'])) $type = 'posts';

// "My posts" only makes sense for logged-in users — fall back silently otherwise.
if ($type === 'my_posts' && !is_logged_in()) $type = 'posts';

$isMyPosts = ($type === 'my_posts');

// "My posts" auto-triggers a search even without a query string.
$didSearch = ($q !== '') || $isMyPosts;

// ── Guest CAPTCHA gate ───────────────────────────────────────────────────────
// Guests can search but must pass one maths challenge per session. Logged-in
// users are unaffected. `$isMyPosts` implies logged-in (see fallback above).
$captchaQuestion = null;
$captchaError    = '';

if ($didSearch && !is_logged_in() && !captcha_session_verified()) {
    $submitted = trim($_GET['captcha'] ?? '');
    if ($submitted !== '' && captcha_verify($submitted)) {
        captcha_mark_verified();
        // passed — let the search run below
    } else {
        if ($submitted !== '') $captchaError = 'Incorrect answer — give it another try.';
        $captchaQuestion = captcha_new();
        $didSearch = false;   // hold the search until the challenge is solved
    }
}

$results = []; $total = 0; $pag = paginate(0,1,1);

$forums = db()->query("SELECT f.id,f.name,f.icon,c.name as cat_name FROM forums f JOIN categories c ON c.id=f.category_id WHERE f.is_active=1 AND c.is_active=1 ORDER BY c.sort_order,f.sort_order")->fetchAll();

$selectedForumName = '';
if ($forumId) foreach ($forums as $f) { if ($f['id']===$forumId){$selectedForumName=$f['name'];break;} }

if ($didSearch && ($isMyPosts || strlen($q) >= 2)) {
    $like = '%'.$q.'%';
    $fj   = $forumId ? 'AND t.forum_id='.$forumId : '';

    if ($isMyPosts) {
        $me  = current_user();
        $uid = (int)$me['id'];
        // Optional query filter: narrow by content/username if $q was supplied.
        $qClause = (strlen($q) >= 2) ? 'AND (p.content LIKE ? OR u.username LIKE ?)' : '';

        $cs = db()->prepare("SELECT COUNT(*) FROM posts p JOIN threads t ON t.id=p.thread_id JOIN users u ON u.id=p.user_id WHERE p.is_active=1 AND t.is_active=1 AND p.user_id=? $qClause $fj");
        $params = [$uid];
        if ($qClause) { $params[] = $like; $params[] = $like; }
        $cs->execute($params);
        $total = (int)$cs->fetchColumn();
        $pag = paginate($total,$perPage,$page);

        $rs = db()->prepare("SELECT p.id,p.content,p.created_at,u.id as user_id,u.username,u.avatar,u.role,t.id as thread_id,t.title as thread_title,t.slug as thread_slug,f.name as forum_name,f.slug as forum_slug,f.icon as forum_icon FROM posts p JOIN threads t ON t.id=p.thread_id JOIN forums f ON f.id=t.forum_id JOIN users u ON u.id=p.user_id WHERE p.is_active=1 AND t.is_active=1 AND p.user_id=? $qClause $fj ORDER BY p.created_at DESC LIMIT ? OFFSET ?");
        $params2 = [$uid];
        if ($qClause) { $params2[] = $like; $params2[] = $like; }
        $params2[] = $pag['limit'];
        $params2[] = $pag['offset'];
        $rs->execute($params2);
        $results = $rs->fetchAll();
    }
    elseif ($type === 'posts') {
        $cs = db()->prepare("SELECT COUNT(*) FROM posts p JOIN threads t ON t.id=p.thread_id JOIN users u ON u.id=p.user_id WHERE p.is_active=1 AND t.is_active=1 AND (p.content LIKE ? OR u.username LIKE ?) $fj");
        $cs->execute([$like,$like]); $total=(int)$cs->fetchColumn();
        $pag = paginate($total,$perPage,$page);
        $rs = db()->prepare("SELECT p.id,p.content,p.created_at,u.id as user_id,u.username,u.avatar,u.role,t.id as thread_id,t.title as thread_title,t.slug as thread_slug,f.name as forum_name,f.slug as forum_slug,f.icon as forum_icon FROM posts p JOIN threads t ON t.id=p.thread_id JOIN forums f ON f.id=t.forum_id JOIN users u ON u.id=p.user_id WHERE p.is_active=1 AND t.is_active=1 AND (p.content LIKE ? OR u.username LIKE ?) $fj ORDER BY p.created_at DESC LIMIT ? OFFSET ?");
        $rs->execute([$like,$like,$pag['limit'],$pag['offset']]); $results=$rs->fetchAll();
    } elseif ($type === 'threads') {
        $cs = db()->prepare("SELECT COUNT(*) FROM threads t JOIN users u ON u.id=t.user_id WHERE t.is_active=1 AND (t.title LIKE ? OR u.username LIKE ?) $fj");
        $cs->execute([$like,$like]); $total=(int)$cs->fetchColumn();
        $pag = paginate($total,$perPage,$page);
        $rs = db()->prepare("SELECT t.*,u.id as user_id,u.username,u.avatar,u.role,f.name as forum_name,f.slug as forum_slug,f.icon as forum_icon,lp.created_at as last_post_at,lu.username as last_post_user FROM threads t JOIN users u ON u.id=t.user_id JOIN forums f ON f.id=t.forum_id LEFT JOIN posts lp ON lp.id=t.last_post_id LEFT JOIN users lu ON lu.id=lp.user_id WHERE t.is_active=1 AND (t.title LIKE ? OR u.username LIKE ?) $fj ORDER BY t.last_post_at DESC LIMIT ? OFFSET ?");
        $rs->execute([$like,$like,$pag['limit'],$pag['offset']]); $results=$rs->fetchAll();
    } elseif ($type === 'users') {
        $cs = db()->prepare("SELECT COUNT(*) FROM users WHERE is_active=1 AND (username LIKE ? OR email LIKE ?)");
        $cs->execute([$like,$like]); $total=(int)$cs->fetchColumn();
        $pag = paginate($total,$perPage,$page);
        $rs = db()->prepare("SELECT id,username,role,avatar,bio,country_code,city,post_count,created_at,last_seen,is_banned FROM users WHERE is_active=1 AND (username LIKE ? OR email LIKE ?) ORDER BY post_count DESC LIMIT ? OFFSET ?");
        $rs->execute([$like,$like,$pag['limit'],$pag['offset']]); $results=$rs->fetchAll();
    }
}

function highlight(string $text, string $query): string {
    $safe = $GLOBALS['__view_highlight_fn']($text);
    // Nothing to highlight — return the escaped text as-is. (An empty pattern
    // matches every zero-width position and would wrap <mark></mark> between
    // every character.)
    if ($query === '') return $safe;
    $safeQ = htmlspecialchars($query, ENT_QUOTES|ENT_HTML5, 'UTF-8');
    return preg_replace('/('.preg_quote($safeQ,'/').')/iu', '<mark>$1</mark>', $safe);
}
// Store h() as closure for use in highlight
$GLOBALS['__view_highlight_fn'] = fn($t) => htmlspecialchars($t,ENT_QUOTES|ENT_HTML5,'UTF-8');

if ($isMyPosts) {
    $me        = current_user();
    $pageTitle = ($me['username'] ?? 'My') . "'s Posts";
} else {
    $pageTitle = $q ? 'Search: '.$q : 'Search';
}

View::renderWithLayout('forum.search', [
    'pageTitle'         => $pageTitle,
    'q'                 => $q,
    'type'              => $type,
    'isMyPosts'         => $isMyPosts,
    'forumId'           => $forumId,
    'results'           => $results,
    'total'             => $total,
    'pag'               => $pag,
    'didSearch'         => $didSearch,
    'forums'            => $forums,
    'selectedForumName' => $selectedForumName,
    'captchaQuestion'   => $captchaQuestion,
    'captchaError'      => $captchaError,
    'highlight'         => 'highlight',   // pass function name
]);

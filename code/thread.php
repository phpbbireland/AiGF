<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$slug = trim($_GET['slug'] ?? '');
if (!$slug) redirect(FORUM_URL . '/index.php');

$stmt = db()->prepare("
    SELECT t.*, f.name as forum_name, f.slug as forum_slug, f.id as fid,
           c.name as cat_name, u.username as author_name, u.id as author_id
    FROM threads t
    JOIN forums f ON f.id = t.forum_id
    JOIN categories c ON c.id = f.category_id
    JOIN users u ON u.id = t.user_id
    WHERE t.slug = ? AND t.is_active = 1
");
$stmt->execute([$slug]);
$thread = $stmt->fetch();
if (!$thread) { http_response_code(404); die('<p>Thread not found.</p>'); }

db()->prepare('UPDATE threads SET view_count = view_count + 1 WHERE id = ?')->execute([$thread['id']]);

$page = max(1, (int)($_GET['page'] ?? 1));
$totalStmt = db()->prepare('SELECT COUNT(*) FROM posts WHERE thread_id = ? AND is_active = 1');
$totalStmt->execute([$thread['id']]);
$total = (int)$totalStmt->fetchColumn();
$pag   = paginate($total, POSTS_PER_PAGE, $page);

$user = current_user();
$postsStmt = db()->prepare("
    SELECT p.*, u.username, u.role, u.avatar, u.post_count, u.created_at as member_since,
           u.country_code, u.city,
           (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as like_count,
           (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id AND user_id = ?) as liked_by_me
    FROM posts p
    JOIN users u ON u.id = p.user_id
    WHERE p.thread_id = ? AND p.is_active = 1
    ORDER BY p.created_at ASC
    LIMIT ? OFFSET ?
");
$postsStmt->execute([$user['id'] ?? 0, $thread['id'], $pag['limit'], $pag['offset']]);
$posts = $postsStmt->fetchAll();

$errors = [];

// Handle new reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reply') {
    require_login();
    if (!csrf_verify()) {
        $errors[] = 'Invalid request.';
    } elseif ($thread['is_locked'] && !is_moderator()) {
        $errors[] = 'This thread is locked.';
    } else {
        $content = trim($_POST['content'] ?? '');
        if (strlen(strip_tags($content)) < 5)   $errors[] = 'Reply is too short (minimum 5 characters).';
        elseif (strlen($content) > 50000)        $errors[] = 'Reply is too long.';
        else {
            // Validate uploaded images (only if attachments are enabled in config)
            $pendingFiles = [];
            if (ALLOW_ATTACHMENTS && !empty($_FILES['post_image']['name'][0])) {
                $fileCount = count($_FILES['post_image']['name']);
                for ($fi = 0; $fi < $fileCount; $fi++) {
                    if ($_FILES['post_image']['error'][$fi] === UPLOAD_ERR_NO_FILE) continue;
                    $singleFile = [
                        'name'     => $_FILES['post_image']['name'][$fi],
                        'type'     => $_FILES['post_image']['type'][$fi],
                        'tmp_name' => $_FILES['post_image']['tmp_name'][$fi],
                        'error'    => $_FILES['post_image']['error'][$fi],
                        'size'     => $_FILES['post_image']['size'][$fi],
                    ];
                    $imgResult = upload_post_image($singleFile);
                    if (!$imgResult['ok']) {
                        $errors[] = 'Image ' . ($fi + 1) . ': ' . $imgResult['error'];
                    } elseif ($imgResult['filename']) {
                        $pendingFiles[] = $imgResult['filename'];
                    }
                }
            }

            if (empty($errors)) {
                // Admin-only post classifier (0=normal, 1=announcement, 2=news, 3=special).
                // Non-admins always get 0 — the form field isn't rendered for them.
                $type = is_admin() ? max(0, min(3, (int)($_POST['type'] ?? 0))) : 0;
                db()->prepare('INSERT INTO posts (thread_id,user_id,content,type,ip_address) VALUES (?,?,?,?,?)')->execute([$thread['id'],$user['id'],$content,$type,$_SERVER['REMOTE_ADDR']]);
                $postId = db()->lastInsertId();

                // Save attachments and append [attachment:N] tokens to content
                if ($pendingFiles) {
                    $attachContent = $content;
                    foreach ($pendingFiles as $filename) {
                        $attachId      = save_attachment($postId, $filename);
                        $attachContent .= '[attachment:' . $attachId . ']';
                    }
                    db()->prepare('UPDATE posts SET content=? WHERE id=?')->execute([$attachContent, $postId]);
                }

                db()->prepare('UPDATE threads SET reply_count=reply_count+1,last_post_id=?,last_post_at=NOW() WHERE id=?')->execute([$postId,$thread['id']]);
                db()->prepare('UPDATE forums SET post_count=post_count+1,last_post_id=? WHERE id=?')->execute([$postId,$thread['fid']]);
                db()->prepare('UPDATE users SET post_count=post_count+1 WHERE id=?')->execute([$user['id']]);
                set_flash('success','Your reply has been posted.');
                $lastPage = (int)ceil(($total + 1) / POSTS_PER_PAGE);
                redirect(FORUM_URL.'/thread.php?slug='.urlencode($slug).'&page='.$lastPage.'#post-'.$postId);
            }
        }
    }
}

// Mod: pin / lock toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['lock','unlock','pin','unpin']) && is_moderator() && csrf_verify()) {
    $a = $_POST['action'];
    if ($a==='lock')   db()->prepare('UPDATE threads SET is_locked=1 WHERE id=?')->execute([$thread['id']]);
    if ($a==='unlock') db()->prepare('UPDATE threads SET is_locked=0 WHERE id=?')->execute([$thread['id']]);
    if ($a==='pin')    db()->prepare('UPDATE threads SET is_pinned=1 WHERE id=?')->execute([$thread['id']]);
    if ($a==='unpin')  db()->prepare('UPDATE threads SET is_pinned=0 WHERE id=?')->execute([$thread['id']]);
    redirect(FORUM_URL.'/thread.php?slug='.urlencode($slug));
}

View::renderWithLayout('forum.thread', [
    'pageTitle' => $thread['title'],
    'thread'    => $thread,
    'posts'     => $posts,
    'pag'       => $pag,
    'errors'    => $errors,
    'slug'      => $slug,
]);

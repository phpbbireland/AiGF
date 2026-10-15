<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$user = current_user();
$preForumId = (int)($_GET['forum'] ?? 0);

$forums = db()->query("
    SELECT f.*, c.name as cat_name FROM forums f
    JOIN categories c ON c.id=f.category_id
    WHERE f.is_active=1 AND c.is_active=1
    ORDER BY c.sort_order, f.sort_order
")->fetchAll();

$errors = [];
$values = ['title' => '', 'forum_id' => $preForumId, 'content' => '', 'type' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid request.';
    } else {
        $title   = trim($_POST['title'] ?? '');
        $forumId = (int)($_POST['forum_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        // Post type — admin-only classifier: 0=normal, 1=announcement, 2=news, 3=special.
        // Non-admins always get 0; the form field isn't even rendered for them.
        $type    = is_admin() ? max(0, min(3, (int)($_POST['type'] ?? 0))) : 0;
        $values  = ['title' => $title, 'forum_id' => $forumId, 'content' => $content, 'type' => $type];

        if (strlen($title) < 5 || strlen($title) > 255) $errors[] = 'Title must be between 5 and 255 characters.';
        if (!$forumId) {
            $errors[] = 'Please select a forum.';
        } else {
            $s = db()->prepare('SELECT id FROM forums WHERE id=? AND is_active=1');
            $s->execute([$forumId]);
            if (!$s->fetch()) $errors[] = 'Invalid forum selected.';
        }
        if (strlen(strip_tags($content)) < 10) $errors[] = 'Post content must be at least 10 characters.';
        if (strlen($content) > 100000)          $errors[] = 'Post content is too long.';

        // Validate uploaded images (only if attachments enabled in config)
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
            $slug = unique_slug('threads', $title);
            db()->beginTransaction();
            try {
                db()->prepare('INSERT INTO threads (forum_id,user_id,title,slug,last_post_at) VALUES (?,?,?,?,NOW())')->execute([$forumId,$user['id'],$title,$slug]);
                $threadId = db()->lastInsertId();
                db()->prepare('INSERT INTO posts (thread_id,user_id,content,type,is_first_post,ip_address) VALUES (?,?,?,?,1,?)')->execute([$threadId,$user['id'],$content,$type,$_SERVER['REMOTE_ADDR']]);
                $postId = db()->lastInsertId();

                // Save each image to attachments table, append [attachment:N] to content
                if ($pendingFiles) {
                    $attachContent = $content;
                    foreach ($pendingFiles as $filename) {
                        $attachId     = save_attachment($postId, $filename);
                        $attachContent .= '[attachment:' . $attachId . ']';
                    }
                    db()->prepare('UPDATE posts SET content=? WHERE id=?')->execute([$attachContent, $postId]);
                }

                db()->prepare('UPDATE threads SET last_post_id=? WHERE id=?')->execute([$postId,$threadId]);
                db()->prepare('UPDATE forums SET thread_count=thread_count+1,post_count=post_count+1,last_post_id=? WHERE id=?')->execute([$postId,$forumId]);
                db()->prepare('UPDATE users SET post_count=post_count+1 WHERE id=?')->execute([$user['id']]);
                db()->commit();
                set_flash('success', 'Your thread has been posted!');
                redirect(FORUM_URL . '/thread.php?slug=' . urlencode($slug));
            } catch (Exception $e) {
                db()->rollBack();
                // Clean up saved image files if transaction failed
                foreach ($pendingFiles as $fn) {
                    $fp = __DIR__ . '/uploads/images/' . $fn;
                    if (file_exists($fp)) @unlink($fp);
                }
                $errors[] = 'An error occurred. Please try again.';
            }
        }
    }
}

$groupedForums = [];
foreach ($forums as $f) $groupedForums[$f['cat_name']][] = $f;

View::renderWithLayout('forum.new_thread', [
    'pageTitle'     => 'New Thread',
    'errors'        => $errors,
    'values'        => $values,
    'groupedForums' => $groupedForums,
]);

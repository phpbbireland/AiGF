<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$user   = current_user();
$postId = (int)($_GET['id'] ?? 0);

$stmt = db()->prepare('
    SELECT p.*, t.slug as thread_slug, t.title as thread_title,
           t.id as thread_id, t.forum_id
    FROM posts p
    JOIN threads t ON t.id = p.thread_id
    WHERE p.id = ? AND p.is_active = 1
');
$stmt->execute([$postId]);
$post = $stmt->fetch();

if (!$post) { http_response_code(404); die('<p>Post not found.</p>'); }
if ($post['user_id'] !== $user['id'] && !is_moderator()) {
    http_response_code(403); die('<p>Access denied.</p>');
}

$isFirstPost = (bool)$post['is_first_post'];
$errors  = [];
$content = $post['content'];
$title   = $post['thread_title'];
$type    = (int)($post['type'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid request.';
    } else {
        $content = trim($_POST['content'] ?? '');
        // Admin-only: re-classify the post (0=normal, 1=announcement, 2=news, 3=special).
        // Non-admins keep the existing value — the form doesn't render for them.
        if (is_admin()) {
            $type = max(0, min(3, (int)($_POST['type'] ?? $type)));
        }

        if (strlen(strip_tags($content)) < 5) {
            $errors[] = 'Post content is too short (minimum 5 characters).';
        } elseif (strlen($content) > 100000) {
            $errors[] = 'Post content is too long.';
        }

        if ($isFirstPost) {
            $title = trim($_POST['title'] ?? '');
            if (strlen($title) < 5)       $errors[] = 'Thread title must be at least 5 characters.';
            elseif (strlen($title) > 255) $errors[] = 'Thread title must be under 255 characters.';
        }

        // Validate and save uploaded images (only if attachments enabled in config)
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
            // Append [attachment:N] tokens for each new image
            foreach ($pendingFiles as $filename) {
                $attachId  = save_attachment($post['id'], $filename);
                $content  .= '[attachment:' . $attachId . ']';
            }

            db()->prepare('UPDATE posts SET content=?, type=?, edited_at=NOW(), edited_by=? WHERE id=?')
               ->execute([$content, $type, $user['id'], $post['id']]);

            if ($isFirstPost && $title !== $post['thread_title']) {
                $newSlug = unique_slug('threads', $title, $post['thread_id']);
                db()->prepare('UPDATE threads SET title=?, slug=? WHERE id=?')
                   ->execute([$title, $newSlug, $post['thread_id']]);
                set_flash('success', 'Post and thread title updated successfully.');
                redirect(FORUM_URL . '/thread.php?slug=' . urlencode($newSlug) . '#post-' . $post['id']);
            }

            set_flash('success', 'Post updated successfully.');
            redirect(FORUM_URL . '/thread.php?slug=' . urlencode($post['thread_slug']) . '#post-' . $post['id']);
        }
    }
}

View::renderWithLayout('forum.edit_post', [
    'pageTitle'   => 'Edit Post',
    'errors'      => $errors,
    'content'     => $content,
    'title'       => $title,
    'type'        => $type,
    'post'        => $post,
    'isFirstPost' => $isFirstPost,
]);

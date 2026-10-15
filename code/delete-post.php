<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

if (!is_moderator()) { http_response_code(403); die('Access denied.'); }

$postId   = (int)($_GET['id'] ?? 0);
$threadSlug = $_GET['thread'] ?? '';

$post = db()->prepare('SELECT p.*, t.slug as thread_slug, t.id as thread_id, t.forum_id FROM posts p JOIN threads t ON t.id = p.thread_id WHERE p.id = ? AND p.is_active = 1');
$post->execute([$postId]);
$post = $post->fetch();

if (!$post) { set_flash('error', 'Post not found.'); redirect(FORUM_URL . '/index.php'); }

// Soft-delete the post
db()->prepare('UPDATE posts SET is_active = 0 WHERE id = ?')->execute([$postId]);

// Update thread reply_count
db()->prepare('UPDATE threads SET reply_count = GREATEST(0, reply_count - 1) WHERE id = ?')->execute([$post['thread_id']]);
// Update forum post_count
db()->prepare('UPDATE forums SET post_count = GREATEST(0, post_count - 1) WHERE id = ?')->execute([$post['forum_id']]);

set_flash('success', 'Post deleted.');
redirect(FORUM_URL . '/thread.php?slug=' . urlencode($post['thread_slug']));

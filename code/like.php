<?php
// like.php — dedicated AJAX endpoint for post like/unlike
// Called via fetch() from thread.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

// Always respond with JSON
header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Must be logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Login required']);
    exit;
}

// CSRF check
if (!csrf_verify()) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$postId = (int)($_POST['post_id'] ?? 0);
if (!$postId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing post_id']);
    exit;
}

// Verify post exists and is active
$postCheck = db()->prepare('SELECT id FROM posts WHERE id = ? AND is_active = 1');
$postCheck->execute([$postId]);
if (!$postCheck->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Post not found']);
    exit;
}

$user = current_user();
$userId = $user['id'];

// Check if already liked
$existing = db()->prepare('SELECT 1 FROM post_likes WHERE user_id = ? AND post_id = ?');
$existing->execute([$userId, $postId]);

if ($existing->fetch()) {
    // Unlike
    db()->prepare('DELETE FROM post_likes WHERE user_id = ? AND post_id = ?')
       ->execute([$userId, $postId]);
    $liked = false;
} else {
    // Like
    db()->prepare('INSERT INTO post_likes (user_id, post_id) VALUES (?, ?)')
       ->execute([$userId, $postId]);
    $liked = true;
}

// Get fresh count
$countStmt = db()->prepare('SELECT COUNT(*) FROM post_likes WHERE post_id = ?');
$countStmt->execute([$postId]);
$count = (int) $countStmt->fetchColumn();

echo json_encode([
    'liked' => $liked,
    'count' => $count,
]);
exit;

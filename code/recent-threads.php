<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$recentThreads = db()->query("
    SELECT t.title, t.slug, u.username, t.created_at
    FROM threads t
    JOIN users u ON u.id = t.user_id
    WHERE t.is_active = 1
    ORDER BY t.created_at DESC
    LIMIT 5
")->fetchAll();

View::renderWithLayout('forum.recent_threads', [
    'pageTitle'     => 'Recent Threads',
    'recentThreads' => $recentThreads,
]);
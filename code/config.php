<?php
// ============================================
// FORUM CONFIGURATION
// ============================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'forum_db');
define('DB_USER', 'root');          // Change to your MySQL user
define('DB_PASS', '');              // Change to your MySQL password
define('DB_CHARSET', 'utf8mb4');

// Fallback site identity — overridden at runtime from the config DB table.
// Change these only if the config table is not yet available (fresh install).
define('FORUM_NAME',    'CommunityHub');           // legacy — kept for compatibility
define('FORUM_TAGLINE', 'Where conversations happen.'); // legacy — kept for compatibility
define('FORUM_URL', 'http://localhost/forum');

define('POSTS_PER_PAGE', 15);
define('THREADS_PER_PAGE', 20);
define('SESSION_LIFETIME', 86400 * 30); // 30 days

define('ALLOWED_AVATAR_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('MAX_AVATAR_SIZE', 2 * 1024 * 1024); // 2MB

define('ALLOWED_POST_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('MAX_POST_IMAGE_SIZE', 2 * 1024 * 1024); // 2MB
define('POST_IMAGE_MAX_WIDTH', 300);             // px — images resized to this width

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => false, // Set to true in production with HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Load all functions (database, helpers, auth, formatting, etc.)
require_once __DIR__ . '/includes/functions.php';

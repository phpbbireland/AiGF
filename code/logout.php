<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    session_destroy();
    session_start();
    set_flash('success', 'You have been logged out.');
}
redirect(FORUM_URL . '/index.php');

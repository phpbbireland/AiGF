<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) redirect(FORUM_URL . '/index.php');

$errors   = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $errors[] = 'Please enter your username/email and password.';
        } else {
            $stmt = db()->prepare('SELECT * FROM users WHERE (LOWER(username)=LOWER(?) OR LOWER(email)=LOWER(?)) AND is_active=1');
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $errors[] = 'Invalid username/email or password.';
            } elseif ($user['is_banned']) {
                $errors[] = 'Your account has been banned.' . ($user['ban_reason'] ? ' Reason: ' . $user['ban_reason'] : '');
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];

                if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
                    $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$newHash, $user['id']]);
                }

                $redirect = $_SESSION['redirect_after_login'] ?? FORUM_URL . '/index.php';
                unset($_SESSION['redirect_after_login']);
                set_flash('success', 'Welcome back, ' . $user['username'] . '!');
                redirect($redirect);
            }
        }
    }
}

View::renderWithLayout('auth.login', [
    'pageTitle' => 'Login',
    'errors'    => $errors,
    'username'  => $username,
]);

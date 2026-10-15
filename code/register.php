<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) redirect(FORUM_URL . '/index.php');

$errors = [];
$values = ['username' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        $values   = compact('username', 'email');

        if (strlen($username) < 3 || strlen($username) > 50)
            $errors[] = 'Username must be between 3 and 50 characters.';
        elseif (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username))
            $errors[] = 'Username may only contain letters, numbers, underscores, hyphens, and dots.';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            $errors[] = 'Please enter a valid email address.';

        if (strlen($password) < 8)
            $errors[] = 'Password must be at least 8 characters long.';
        elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password))
            $errors[] = 'Password must contain at least one uppercase letter and one number.';

        if ($password !== $confirm)
            $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            $stmt = db()->prepare('SELECT id FROM users WHERE LOWER(username)=LOWER(?) OR LOWER(email)=LOWER(?)');
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) $errors[] = 'That username or email is already registered.';
        }

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            db()->prepare('INSERT INTO users (username, email, password_hash) VALUES (?,?,?)')->execute([$username, $email, $hash]);
            $_SESSION['user_id'] = db()->lastInsertId();
            set_flash('success', 'Welcome to ' . SITE_NAME . ', ' . $username . '! Your account has been created.');
            redirect(FORUM_URL . '/index.php');
        }
    }
}

View::renderWithLayout('auth.register', [
    'pageTitle' => 'Create an Account',
    'errors'    => $errors,
    'values'    => $values,
]);

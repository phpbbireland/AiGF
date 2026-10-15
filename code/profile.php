<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/countries.php';

// Default to the logged-in user when ?id is missing, so /profile.php is a
// usable canonical "my profile" URL (and a menu item can just be
// link_to = profile.php). Guests fall through to login.
$profileId = (int)($_GET['id'] ?? 0);
if (!$profileId) {
    $cu = current_user();
    if (!$cu) redirect(FORUM_URL . '/login.php');
    $profileId = (int)$cu['id'];
}

$stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1');
$stmt->execute([$profileId]);
$profile = $stmt->fetch();
if (!$profile) { http_response_code(404); die('<p>User not found.</p>'); }

$currentUser  = current_user();
$isOwnProfile = $currentUser && $currentUser['id'] === $profile['id'];
$activeTab    = $_GET['tab'] ?? 'posts';
$errors = []; $pwErrors = []; $avatarError = '';

if ($isOwnProfile && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile' && csrf_verify()) {
        $bio        = trim($_POST['bio']         ?? '');
        $city       = trim($_POST['city']        ?? '');
        $cc         = strtoupper(trim($_POST['country_code'] ?? ''));
        $email      = trim($_POST['email']       ?? '');
        // Theme allowlist comes from the `themes` table (admin/themes.php).
        // Falls back to a hardcoded list pre-migration so the form keeps
        // working before build/migrate_themes.sql is applied.
        try {
            $allowedThemes = db()->query('SELECT slug FROM themes WHERE is_active = 1')
                ->fetchAll(PDO::FETCH_COLUMN, 0);
            if (empty($allowedThemes)) {
                $allowedThemes = ['dark', 'light', 'autumn', 'user', 'winter', 'kiss-classic', 'olympus'];
            }
        } catch (PDOException $e) {
            $allowedThemes = ['dark', 'light', 'autumn', 'user', 'winter', 'kiss-classic', 'olympus'];
        }
        $theme = in_array($_POST['theme'] ?? '', $allowedThemes, true) ? $_POST['theme'] : 'dark';

        if (strlen($bio) > 1000)  $errors[] = 'Bio must be under 1000 characters.';
        if (strlen($city) > 100)  $errors[] = 'City must be under 100 characters.';
        if ($cc && !preg_match('/^[A-Z]{2}$/', $cc)) $errors[] = 'Invalid country.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';

        // Ensure email isn't already used by another account
        if (empty($errors)) {
            $chk = db()->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $chk->execute([$email, $profile['id']]);
            if ($chk->fetch()) $errors[] = 'That email address is already in use by another account.';
        }

        if (empty($errors)) {
            // Try to include theme — falls back gracefully if column doesn't exist yet
            try {
                db()->prepare('UPDATE users SET bio=?, city=?, country_code=?, email=?, theme=? WHERE id=?')
                   ->execute([$bio ?: null, $city ?: null, $cc ?: null, $email, $theme, $profile['id']]);
            } catch (PDOException $e) {
                // theme column not yet migrated — save without it
                db()->prepare('UPDATE users SET bio=?, city=?, country_code=?, email=? WHERE id=?')
                   ->execute([$bio ?: null, $city ?: null, $cc ?: null, $email, $profile['id']]);
            }
            set_flash('success', 'Profile updated successfully.');
            redirect(FORUM_URL . '/profile.php?id=' . $profile['id'] . '&tab=edit');
        }
        $activeTab = 'edit';
    }

    if ($action === 'upload_avatar') {
        if (!csrf_verify()) { $avatarError = 'Invalid request.'; }
        elseif (empty($_FILES['avatar']['tmp_name'])) { $avatarError = 'No file uploaded.'; }
        else {
            $file = $_FILES['avatar'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($file['tmp_name']);
            $uploadErrors = [UPLOAD_ERR_INI_SIZE=>'File too large.',UPLOAD_ERR_FORM_SIZE=>'File too large.',UPLOAD_ERR_PARTIAL=>'Partial upload.',UPLOAD_ERR_NO_FILE=>'No file.',UPLOAD_ERR_NO_TMP_DIR=>'Server error.',UPLOAD_ERR_CANT_WRITE=>'Write error.',UPLOAD_ERR_EXTENSION=>'Blocked.'];
            if ($file['error'] !== UPLOAD_ERR_OK) { $avatarError = $uploadErrors[$file['error']] ?? 'Upload error.'; }
            elseif ($file['size'] > MAX_AVATAR_SIZE) { $avatarError = 'Image must be under 2 MB.'; }
            elseif (!in_array($mime, ALLOWED_AVATAR_TYPES)) { $avatarError = 'Only JPEG, PNG, GIF and WebP allowed.'; }
            else {
                $ext = match($mime){'image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp',default=>'jpg'};
                $dir = __DIR__.'/uploads/avatars/';
                if (!is_dir($dir)) mkdir($dir,0755,true);
                $fn = 'avatar_'.$profile['id'].'_'.bin2hex(random_bytes(6)).'.'.$ext;
                if (!move_uploaded_file($file['tmp_name'],$dir.$fn)) { $avatarError = 'Could not save image.'; }
                else {
                    if (!empty($profile['avatar']) && file_exists($dir.$profile['avatar'])) @unlink($dir.$profile['avatar']);
                    db()->prepare('UPDATE users SET avatar=? WHERE id=?')->execute([$fn,$profile['id']]);
                    set_flash('success','Avatar updated.');
                    redirect(FORUM_URL.'/profile.php?id='.$profile['id'].'&tab=edit');
                }
            }
        }
        $activeTab = 'edit';
    }

    if ($action === 'remove_avatar' && csrf_verify() && !empty($profile['avatar'])) {
        $dir = __DIR__.'/uploads/avatars/';
        if (file_exists($dir.$profile['avatar'])) @unlink($dir.$profile['avatar']);
        db()->prepare('UPDATE users SET avatar=NULL WHERE id=?')->execute([$profile['id']]);
        set_flash('success','Avatar removed.');
        redirect(FORUM_URL.'/profile.php?id='.$profile['id'].'&tab=edit');
    }

    if ($action === 'change_password' && csrf_verify()) {
        $curPw = $_POST['current_password'] ?? '';
        $newPw = $_POST['new_password'] ?? '';
        $conPw = $_POST['confirm_password'] ?? '';
        if (empty($curPw)) $pwErrors[] = 'Please enter your current password.';
        elseif (!password_verify($curPw,$profile['password_hash'])) $pwErrors[] = 'Current password is incorrect.';
        if (strlen($newPw) < 8) $pwErrors[] = 'New password must be at least 8 characters.';
        elseif (!preg_match('/[A-Z]/',$newPw)) $pwErrors[] = 'Must contain an uppercase letter.';
        elseif (!preg_match('/[0-9]/',$newPw)) $pwErrors[] = 'Must contain a number.';
        if ($newPw !== $conPw) $pwErrors[] = 'New passwords do not match.';
        if (empty($pwErrors)) {
            db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($newPw,PASSWORD_BCRYPT,['cost'=>12]),$profile['id']]);
            session_destroy(); session_start();
            set_flash('success','Password changed. Please log in again.');
            redirect(FORUM_URL.'/login.php');
        }
        $activeTab = 'password';
    }
}

// Re-fetch fresh
$stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$profileId]);
$profile = $stmt->fetch();

$countries   = get_countries();
$flagEmoji   = !empty($profile['country_code']) ? country_flag($profile['country_code']) : '';
$countryName = !empty($profile['country_code']) ? country_name($profile['country_code']) : '';

$rpStmt = db()->prepare("SELECT p.id,p.content,p.created_at,t.title as thread_title,t.slug as thread_slug FROM posts p JOIN threads t ON t.id=p.thread_id WHERE p.user_id=? AND p.is_active=1 ORDER BY p.created_at DESC LIMIT 10");
$rpStmt->execute([$profile['id']]);
$recentPosts = $rpStmt->fetchAll();

// Active themes for the picker — fetched from the `themes` table
// (admin/themes.php). Falls back to a hardcoded set pre-migration so
// the picker keeps rendering before build/migrate_themes.sql runs.
try {
    $themesStmt = db()->query(
        'SELECT slug, name, description, icon, author
           FROM themes
          WHERE is_active = 1
          ORDER BY sort_order ASC, slug ASC'
    );
    $themes = $themesStmt->fetchAll();
} catch (PDOException $e) {
    $themes = [];
}
if (empty($themes)) {
    $themes = [
        ['slug' => 'dark',         'name' => 'Dark',         'description' => 'Easy on the eyes at night',  'icon' => '🌙', 'author' => 'Mioke'],
        ['slug' => 'light',        'name' => 'Light',        'description' => 'Clean and bright',           'icon' => '☀️', 'author' => 'Mioke'],
        ['slug' => 'autumn',       'name' => 'Autumn',       'description' => 'Warm amber & burnt orange',  'icon' => '🍂', 'author' => 'Mioke'],
        ['slug' => 'user',         'name' => 'User',         'description' => 'Custom user theme',          'icon' => '👤', 'author' => 'Mioke'],
        ['slug' => 'winter',       'name' => 'Winter',       'description' => 'Cool & crisp',               'icon' => '❄️', 'author' => 'Mioke'],
        ['slug' => 'kiss-classic', 'name' => 'Kiss Classic', 'description' => 'olympus phpBB port',         'icon' => '💋', 'author' => 'Mioke'],
        ['slug' => 'olympus',      'name' => 'Olympus',      'description' => 'olympus phpBB port',         'icon' => '💋', 'author' => 'Mioke'],        
    ];
}

View::renderWithLayout('forum.profile', [
    'pageTitle'    => $profile['username'] . "'s Profile",
    'profile'      => $profile,
    'isOwnProfile' => $isOwnProfile,
    'activeTab'    => $activeTab,
    'errors'       => $errors,
    'pwErrors'     => $pwErrors,
    'avatarError'  => $avatarError,
    'countries'    => $countries,
    'flagEmoji'    => $flagEmoji,
    'countryName'  => $countryName,
    'recentPosts'  => $recentPosts,
    'themes'       => $themes,
]);

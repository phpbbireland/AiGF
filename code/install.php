<?php
// =====================================================================
// INSTALL LOCKDOWN GATE
// ---------------------------------------------------------------------
// Goal: never let install.php render its setup instructions on a forum
// that's already been installed. A live install.php on a public box is a
// privilege-escalation surface (it exposes DB requirement diagnostics
// and step-by-step setup hints).
//
// Strategy:
//   1. If the sentinel file `build/.installed` exists, render lockdown.
//   2. Otherwise, parse config.php for DB credentials *without* loading
//      the app bootstrap (so a half-configured install doesn't crash on
//      includes/functions.php), try a short-timeout PDO connect, and
//      check whether the `users` table contains at least one admin row.
//      If so: write the sentinel (best-effort) and render lockdown.
//   3. If neither check fires, fall through to the regular install page.
//
// To re-run the installer (legitimate case): delete `build/.installed`
// and reload. The DB-probe will only re-arm if an admin user is found.
// =====================================================================

$__SENTINEL_PATH = __DIR__ . '/build/.installed';

function __install_render_lockdown(string $reason, string $sentinelPath): void {
    http_response_code(403);
    $relSentinel = 'build/.installed';
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>CommunityHub — Install Locked</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{--bg:#0d0f14;--surface:#1e2330;--border:#2e3547;--accent:#6e8efb;--accent3:#34d399;--danger:#f87171;--warning:#fbbf24;--text:#e8eaf0;--text2:#9aa3b8}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--text);font-family:'DM Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
h1{font-family:'Syne',sans-serif;font-weight:800}
.card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:32px;max-width:680px;width:100%}
.box{padding:14px 16px;border:1px solid var(--border);border-radius:10px;margin-bottom:14px}
code{background:#13161e;padding:3px 8px;border-radius:5px;font-family:monospace;color:#a78bfa;font-size:13px}
ol{padding-left:20px;line-height:2;color:var(--text2);font-size:14px}
li{margin-bottom:4px}
.cta{display:inline-flex;align-items:center;gap:8px;padding:12px 28px;background:linear-gradient(135deg,#6e8efb,#a78bfa);color:#fff;border-radius:10px;font-weight:700;text-decoration:none;font-family:'Syne',sans-serif}
</style>
</head>
<body>
<div class="card">
  <div style="text-align:center;margin-bottom:24px">
    <div style="font-size:48px;margin-bottom:8px">🔒</div>
    <h1 style="font-size:24px;margin-bottom:6px">Install Already Complete</h1>
    <p style="color:var(--text2);font-size:14px">
      Detected via <strong><?= htmlspecialchars($reason, ENT_QUOTES) ?></strong>.
      The <code>install.php</code> file is now a security risk and should be removed.
    </p>
  </div>

  <div class="box" style="border-color:var(--danger)">
    <strong style="color:var(--danger)">⚠ Why am I seeing this?</strong>
    <p style="color:var(--text2);font-size:13px;margin-top:6px">
      Either the sentinel <code><?= htmlspecialchars($relSentinel, ENT_QUOTES) ?></code> exists, or this script connected to your database and saw at least one admin user. Either signal indicates the install is finished, so the setup instructions are now hidden.
    </p>
  </div>

  <div class="box" style="border-color:var(--warning)">
    <strong style="color:var(--warning)">Recommended actions (pick one):</strong>
    <ol>
      <li><strong>Delete this file:</strong> <code>rm install.php</code></li>
      <li><strong>Rename it:</strong> <code>mv install.php install.php.done</code></li>
      <li><strong>Block via web server:</strong> the shipped root <code>.htaccess</code> already denies <code>install.php</code> when <code>AllowOverride All</code> is enabled on your vhost — verify with <code>curl -I /install.php</code>.</li>
    </ol>
  </div>

  <div class="box">
    <strong>Need to re-run the installer?</strong>
    <p style="color:var(--text2);font-size:13px;margin-top:6px">
      Delete <code><?= htmlspecialchars($relSentinel, ENT_QUOTES) ?></code> and reload this page. The lockdown will only re-arm if a connected database still shows an admin user.
    </p>
  </div>

  <div style="text-align:center;margin-top:24px">
    <a href="index.php" class="cta">Go to forum →</a>
  </div>
</div>
</body>
</html><?php
    exit;
}

function __install_extract_db_creds(string $configPath): ?array {
    if (!is_readable($configPath)) return null;
    $src = file_get_contents($configPath);
    if ($src === false) return null;
    $get = static function (string $name) use ($src) {
        $pat = "/define\\s*\\(\\s*['\"]" . preg_quote($name, '/') . "['\"]\\s*,\\s*['\"]([^'\"]*)['\"]/";
        return preg_match($pat, $src, $m) ? $m[1] : null;
    };
    $host = $get('DB_HOST');
    $name = $get('DB_NAME');
    $user = $get('DB_USER');
    $pass = $get('DB_PASS'); // may be ''
    if ($host === null || $name === null || $user === null) return null;
    return ['host' => $host, 'name' => $name, 'user' => $user, 'pass' => (string)$pass];
}

function __install_is_complete(array $creds): bool {
    try {
        $dsn = "mysql:host={$creds['host']};dbname={$creds['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $creds['user'], $creds['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 2,
        ]);
        $row = $pdo->query("SELECT COUNT(*) AS c FROM users WHERE role='admin' AND is_active=1")->fetch(PDO::FETCH_ASSOC);
        return is_array($row) && (int)$row['c'] > 0;
    } catch (Throwable $e) {
        return false;
    }
}

// 1. Sentinel takes precedence — fastest path, no DB touch.
if (is_file($__SENTINEL_PATH)) {
    __install_render_lockdown('sentinel file present', $__SENTINEL_PATH);
}

// 2. Live-DB probe — auto-arms the lockdown if the install is effectively done.
$__creds = __install_extract_db_creds(__DIR__ . '/config.php');
if ($__creds && __install_is_complete($__creds)) {
    if (is_dir(dirname($__SENTINEL_PATH)) && is_writable(dirname($__SENTINEL_PATH))) {
        @file_put_contents(
            $__SENTINEL_PATH,
            "Installation completed at " . gmdate('c') . " UTC\n" .
            "Detected by install.php DB probe (admin user found in users table).\n" .
            "Delete this file and reload install.php to re-evaluate.\n"
        );
    }
    __install_render_lockdown('live database probe (admin user found)', $__SENTINEL_PATH);
}
// === End lockdown gate ===
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>CommunityHub — Install</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{--bg:#0d0f14;--surface:#1e2330;--border:#2e3547;--accent:#6e8efb;--accent2:#a78bfa;--accent3:#34d399;--danger:#f87171;--warning:#fbbf24;--text:#e8eaf0;--text2:#9aa3b8;--text3:#6b7590}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--text);font-family:'DM Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
h1{font-family:'Syne',sans-serif;font-weight:800}
.card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:32px;max-width:700px;width:100%}
.step{padding:16px;border:1px solid var(--border);border-radius:10px;margin-bottom:14px}
.step-num{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;background:var(--accent);color:#fff;border-radius:50%;font-weight:700;font-size:13px;margin-right:10px;flex-shrink:0}
code{background:#13161e;padding:3px 8px;border-radius:5px;font-family:monospace;color:var(--accent2);font-size:13px}
pre{background:#13161e;padding:16px;border-radius:10px;overflow-x:auto;font-size:13px;line-height:1.6;color:var(--text2);margin-top:10px}
.badge-ok{color:var(--accent3)}.badge-warn{color:var(--warning)}.badge-fail{color:var(--danger)}
table{width:100%;border-collapse:collapse;font-size:14px}
td{padding:8px 12px;border-bottom:1px solid var(--border)}
td:first-child{color:var(--text2);width:200px}
</style>
</head>
<body>
<div class="card">
  <div style="text-align:center;margin-bottom:28px">
    <div style="font-size:40px;margin-bottom:10px">⚙️</div>
    <h1 style="font-size:26px;margin-bottom:6px">CommunityHub Setup</h1>
    <p style="color:var(--text2)">Quick-start guide to get your forum running.</p>
  </div>

  <!-- Requirements check -->
  <div style="margin-bottom:24px">
    <h2 style="font-family:'Syne',sans-serif;font-size:16px;margin-bottom:12px">System Requirements</h2>
    <table>
      <tr>
        <td>PHP Version</td>
        <td><?php
          $phpOk = version_compare(PHP_VERSION, '8.0', '>=');
          echo $phpOk ? '<span class="badge-ok">✓ '.PHP_VERSION.'</span>' : '<span class="badge-fail">✗ '.PHP_VERSION.' (8.0+ required)</span>';
        ?></td>
      </tr>
      <tr>
        <td>PDO MySQL</td>
        <td><?php
          $pdoOk = extension_loaded('pdo_mysql');
          echo $pdoOk ? '<span class="badge-ok">✓ Enabled</span>' : '<span class="badge-fail">✗ Not found — enable pdo_mysql in php.ini</span>';
        ?></td>
      </tr>
      <tr>
        <td>mbstring</td>
        <td><?php
          $mbOk = extension_loaded('mbstring');
          echo $mbOk ? '<span class="badge-ok">✓ Enabled</span>' : '<span class="badge-warn">⚠ Not found (recommended)</span>';
        ?></td>
      </tr>
      <tr>
        <td>Session support</td>
        <td><?php
          echo function_exists('session_start') ? '<span class="badge-ok">✓ Available</span>' : '<span class="badge-fail">✗ Missing</span>';
        ?></td>
      </tr>
      <tr>
        <td>config.php writable</td>
        <td><?php
          $cfgPath = __DIR__ . '/config.php';
          if (is_file($cfgPath)) {
              // Normal case: shipped config.php is present — only the file itself needs to be writable.
              $cwOk = is_writable($cfgPath);
              if ($cwOk) {
                  echo '<span class="badge-ok">✓ Writable</span>';
              } else {
                  echo '<span class="badge-warn">⚠ Not writable</span> '
                     . '<small style="color:var(--text2)">— run <code>chmod 666 config.php</code> before step 2, then <code>chmod 644 config.php</code> after.</small>';
              }
          } else {
              // Edge case: shipped config.php was deleted. Install package is incomplete.
              echo '<span class="badge-fail">✗ config.php missing</span> '
                 . '<small style="color:var(--text2)">— restore it from the install package; it ships in the repo root.</small>';
          }
        ?></td>
      </tr>
    </table>
  </div>

  <!-- Steps -->
  <h2 style="font-family:'Syne',sans-serif;font-size:16px;margin-bottom:14px">Installation Steps</h2>

  <div class="step">
    <div style="display:flex;align-items:center;margin-bottom:8px">
      <span class="step-num">1</span>
      <strong>Import the database schema</strong>
    </div>
    <p style="color:var(--text2);font-size:14px;margin-bottom:8px">Create a MySQL database called <code>forum_db</code> (or any name), then import the SQL file:</p>
    <pre>mysql -u root -p forum_db &lt; build/database.sql</pre>
    <p style="color:var(--text2);font-size:13px;margin-top:8px">Or use phpMyAdmin → Import → select <code>build/database.sql</code></p>
  </div>

  <div class="step">
    <div style="display:flex;align-items:center;margin-bottom:8px">
      <span class="step-num">2</span>
      <strong>Configure database connection</strong>
    </div>
    <p style="color:var(--text2);font-size:14px;margin-bottom:8px">Edit <code>config.php</code> and update these four lines:</p>
    <pre>define('DB_HOST', 'localhost');
define('DB_NAME', 'forum_db');
define('DB_USER', 'your_mysql_user');
define('DB_PASS', 'your_mysql_password');</pre>
  </div>

  <div class="step">
    <div style="display:flex;align-items:center;margin-bottom:8px">
      <span class="step-num">3</span>
      <strong>Set your forum URL</strong>
    </div>
    <p style="color:var(--text2);font-size:14px;margin-bottom:8px">Also in <code>config.php</code>, set the URL to where the forum is hosted:</p>
    <pre>define('FORUM_URL', 'http://localhost/forum');
// or for production:
define('FORUM_URL', 'https://yourdomain.com');</pre>
  </div>

  <div class="step">
    <div style="display:flex;align-items:center;margin-bottom:8px">
      <span class="step-num">4</span>
      <strong>Create uploads directories</strong>
    </div>
    <p style="color:var(--text2);font-size:14px;margin-bottom:8px">Create writable directories for avatar uploads and post-image attachments:</p>
    <pre>mkdir -p uploads/avatars uploads/images</pre>
  </div>

  <div class="step">
    <div style="display:flex;align-items:center;margin-bottom:8px">
      <span class="step-num">5</span>
      <strong>Set file permissions</strong>
    </div>
    <p style="color:var(--text2);font-size:14px;margin-bottom:8px">Recommended permissions (phpBB3 convention):</p>
    <table style="margin-bottom:10px;font-size:13px">
      <tr><td><code>config.php</code></td><td><code>666</code> before step 2, then <code>644</code> after</td></tr>
      <tr><td>All other files</td><td><code>644</code></td></tr>
      <tr><td>All directories</td><td><code>755</code></td></tr>
      <tr><td><code>uploads/avatars</code>, <code>uploads/images</code></td><td><code>775</code> (or <code>777</code> if your web server runs as a different user from the file owner)</td></tr>
    </table>
    <p style="color:var(--text2);font-size:14px;margin-bottom:8px">Copy-paste from the project root:</p>
    <pre># Baseline: files 644, directories 755
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

# Uploads need to be writable by the web server
chmod 775 uploads/avatars uploads/images

# Lock down config.php once you're done editing it
chmod 644 config.php</pre>
    <p style="color:var(--text2);font-size:13px;margin-top:8px">
      <strong>Before</strong> step 2 (editing <code>config.php</code>), make it temporarily writable:
      <code>chmod 666 config.php</code>. Drop it back to <code>644</code> when you're finished.
    </p>
  </div>

  <div class="step">
    <div style="display:flex;align-items:center;margin-bottom:8px">
      <span class="step-num">6</span>
      <strong>Login with the demo accounts</strong>
    </div>
    <p style="color:var(--text2);font-size:14px">All demo accounts use password: <code>ChangeMe!2026</code></p>
    <table style="margin-top:10px">
      <tr><td>admin</td><td>Full admin access + admin panel</td></tr>
      <tr><td>moderator</td><td>Can pin, lock, delete posts</td></tr>
      <tr><td>alice / bob / charlie</td><td>Regular member accounts</td></tr>
    </table>
  </div>

  <div class="step" style="border-color:var(--warning)">
    <div style="display:flex;align-items:center;margin-bottom:8px">
      <span class="step-num" style="background:var(--warning);color:#000">!</span>
      <strong>Production security checklist</strong>
    </div>
    <ul style="color:var(--text2);font-size:13px;list-style:disc;padding-left:20px;line-height:2">
      <li>Change all demo account passwords immediately</li>
      <li>Set <code>secure: true</code> in session cookie params (requires HTTPS)</li>
      <li>Keep <code>config.php</code> outside your web root, or protect it with .htaccess</li>
      <li>Delete or password-protect this <code>install.php</code> file (it now self-locks via the sentinel <code>build/.installed</code> + a live-DB probe, but deleting it is still the safest option)</li>
      <li>Enable HTTPS / SSL certificate</li>
    </ul>
  </div>

  <div style="text-align:center;margin-top:24px">
    <a href="index.php" style="display:inline-flex;align-items:center;gap:8px;padding:12px 28px;background:linear-gradient(135deg,#6e8efb,#a78bfa);color:#fff;border-radius:10px;font-weight:700;text-decoration:none;font-family:'Syne',sans-serif">
      🚀 Go to Forum Homepage
    </a>
  </div>
</div>
</body>
</html>

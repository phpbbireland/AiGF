<?php
// ============================================
// FORUM FUNCTIONS
// All application functions: database, helpers,
// auth, formatting, flash messages, etc.
// Loaded by config.php — do not include directly.
// ============================================

// DATABASE CONNECTION (PDO Singleton)

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;padding:2rem;background:#fee;border:2px solid #c00;border-radius:8px;margin:2rem auto;max-width:600px">
                <h2 style="color:#c00">Database Connection Error</h2>
                <p>Could not connect to the database. Please check your configuration in <code>config.php</code>.</p>
                <p><em>Error: ' . htmlspecialchars($e->getMessage()) . '</em></p>
            </div>');
        }
    }
    return $pdo;
}

// ============================================
// HELPER FUNCTIONS
// ============================================

function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_verify(): bool {
    return isset($_POST['csrf_token']) && hash_equals(csrf_token(), $_POST['csrf_token']);
}

// ── Lightweight math CAPTCHA ──────────────────────────────────────────────────
// Session-based, single-use per challenge. captcha_session_verified() stays true
// for the rest of the browsing session once the visitor has passed once, so we
// don't nag real humans on every request.
function captcha_new(): string {
    $a = random_int(1, 9);
    $b = random_int(1, 9);
    $_SESSION['captcha_answer'] = $a + $b;
    return "What is {$a} + {$b}?";
}

function captcha_verify(string $answer): bool {
    $expected = $_SESSION['captcha_answer'] ?? null;
    unset($_SESSION['captcha_answer']);         // single use — regenerate on fail
    return $expected !== null && trim($answer) !== '' && (int)$answer === (int)$expected;
}

function captcha_session_verified(): bool {
    return !empty($_SESSION['captcha_human']);
}

function captcha_mark_verified(): void {
    $_SESSION['captcha_human'] = true;
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function current_user(): ?array {
    if (!empty($_SESSION['user_id'])) {
        static $user = null;
        if ($user === null) {
            $stmt = db()->prepare(
                'SELECT u.*, g.colour AS group_colour
                   FROM users u
                   LEFT JOIN `groups` g ON g.id = u.group_id
                  WHERE u.id = ? AND u.is_active = 1 AND u.is_banned = 0'
            );
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch() ?: null;
        }
        return $user;
    }
    return null;
}

function is_logged_in(): bool {
    return current_user() !== null;
}

function is_admin(): bool {
    $u = current_user();
    return $u && $u['role'] === 'admin';
}

function is_moderator(): bool {
    $u = current_user();
    return $u && in_array($u['role'], ['admin', 'moderator']);
}

function require_login(): void {
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        redirect(FORUM_URL . '/login.php');
    }
}

function require_admin(): void {
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        die('<p>Access denied.</p>');
    }
}

/**
 * Scan assets/images/forum_icons/ and return a naturally-sorted list of
 * image basenames. Shared by any admin page wiring the icon-picker with
 * a forum_icons tab (forums, categories, ...).
 */
function admin_scan_forum_icons(): array {
    $dir = dirname(__DIR__) . '/assets/images/forum_icons';
    if (!is_dir($dir)) return [];
    $found = [];
    foreach (glob($dir . '/*.{png,gif,jpg,jpeg,webp,svg,PNG,GIF,JPG,JPEG,WEBP,SVG}', GLOB_BRACE) ?: [] as $path) {
        $base = basename($path);
        if ($base[0] === '.') continue;
        $found[] = $base;
    }
    sort($found, SORT_NATURAL | SORT_FLAG_CASE);
    return $found;
}

/**
 * Scan assets/images/block_images/menu/ and return a naturally-sorted list of
 * image basenames. Used by admin/k_menus.php to populate the icon-picker.
 * Stored values in k_menus.menu_icon are bare filenames (e.g. "www.gif"),
 * so the picker is wired with prefix:'' to match.
 */
function admin_scan_menu_icons(): array {
    $dir = dirname(__DIR__) . '/assets/images/block_images/menu';
    if (!is_dir($dir)) return [];
    $found = [];
    foreach (glob($dir . '/*.{png,gif,jpg,jpeg,webp,svg,PNG,GIF,JPG,JPEG,WEBP,SVG}', GLOB_BRACE) ?: [] as $path) {
        $base = basename($path);
        if ($base[0] === '.') continue;
        $found[] = $base;
    }
    sort($found, SORT_NATURAL | SORT_FLAG_CASE);
    return $found;
}

/* ──────────────────────────────────────────────────────────────────────────
   Block-admin helpers
   Shared by admin/blocks.php (list + create + delete) and
   admin/block_edit.php (single-record edit). Living here means both entry
   points can pull them via includes/functions.php without either having to
   include the other.
   ────────────────────────────────────────────────────────────────────────── */

function admin_blocks_collect_post(array $post, array $positions, array $types): array
{
    // Normalise view_pages (comma-separated page_id list)
    $pageIds = $post['view_pages'] ?? [];
    if (!is_array($pageIds)) $pageIds = [];
    $pageIds = array_values(array_filter(array_map('intval', $pageIds), fn($v) => $v > 0));
    sort($pageIds);

    $position = $post['position'] ?? 'L';
    if (!isset($positions[$position])) $position = 'L';

    $type = $post['type'] ?? 'H';
    if (!isset($types[$type])) $type = 'H';

    // Normalise the block image field — allow empty ('' → 'none.gif' sentinel)
    $img = trim((string)($post['img_file_name'] ?? ''));
    if ($img === '') $img = 'none.gif';

    return [
        'title'          => trim((string)($post['title'] ?? '')),
        'position'       => $position,
        'type'           => $type,
        'active'         => !empty($post['active']) ? 1 : 0,
        'html_file_name' => trim((string)($post['html_file_name'] ?? '')),
        'img_file_name'  => $img,
        'view_all'       => !empty($post['view_all']) ? 1 : 0,
        'view_groups'    => trim((string)($post['view_groups'] ?? '')),
        'view_pages'     => implode(',', $pageIds),
    ];
}

function admin_blocks_validate(array $data, array $available, array $images = [], array $forumIcons = []): ?string
{
    if (strlen($data['title']) < 2) {
        return 'Title is required (min 2 characters).';
    }
    if ($data['html_file_name'] === '') {
        return 'A block implementation must be selected.';
    }
    if ($available && !isset($available[$data['html_file_name']])) {
        return 'Unknown block implementation: ' . htmlspecialchars($data['html_file_name']);
    }
    // 'none.gif' is the "no image" sentinel. Anything else must resolve to a
    // real file in one of the known folders — stops admins from poking in
    // arbitrary paths. Forum icons carry a 'forum_icons/' prefix so a single
    // column can reference either source without ambiguity.
    $img = $data['img_file_name'] ?? 'none.gif';
    $forumIconsPrefixed = array_map(fn($f) => 'forum_icons/' . $f, $forumIcons);
    $allowed = array_merge(['none.gif'], $images, $forumIconsPrefixed);
    if (!in_array($img, $allowed, true)) {
        return 'Unknown block image: ' . htmlspecialchars($img);
    }
    return null;
}

function admin_blocks_scan_image_dir(string $dir): array
{
    if (!is_dir($dir)) return [];
    $found = [];
    foreach (glob($dir . '/*.{png,gif,jpg,jpeg,webp,svg,PNG,GIF,JPG,JPEG,WEBP,SVG}', GLOB_BRACE) ?: [] as $path) {
        $base = basename($path);
        if ($base[0] === '.') continue;
        $found[] = $base;
    }
    sort($found, SORT_NATURAL | SORT_FLAG_CASE);
    return $found;
}

function admin_blocks_list_images(): array
{
    return admin_blocks_scan_image_dir(dirname(__DIR__) . '/assets/images/block_images/block');
}

function admin_blocks_list_forum_icons(): array
{
    return admin_blocks_scan_image_dir(dirname(__DIR__) . '/assets/images/forum_icons');
}

/**
 * Build the bootstrap JS that wires up the block-image picker. Feeds the
 * picker a sources[] array so the admin can flip between "block images" and
 * "forum icons" via tabs. The stored value carries the source's prefix, so
 * one DB column can reference either folder without ambiguity.
 */
function admin_blocks_picker_bootstrap(array $images, array $forumIcons = []): string
{
    $blockBase = FORUM_URL . '/assets/images/block_images/block/';
    $iconBase  = FORUM_URL . '/assets/images/forum_icons/';

    $sources = [
        [
            'key'     => 'block',
            'label'   => 'Block images',
            'baseUrl' => $blockBase,
            'prefix'  => '',
            'images'  => array_values($images),
        ],
    ];
    if ($forumIcons) {
        $sources[] = [
            'key'     => 'forum_icons',
            'label'   => 'Forum icons',
            'baseUrl' => $iconBase,
            'prefix'  => 'forum_icons/',
            'images'  => array_values($forumIcons),
        ];
    }

    $payload = json_encode(
        ['sources' => $sources],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    return <<<JS
document.addEventListener('DOMContentLoaded', function () {
  var opts = $payload;
  document.querySelectorAll('input[data-block-image-picker]').forEach(function (el) {
    if (!el.id) el.id = 'bip-' + Math.random().toString(36).slice(2, 8);
    attachBlockImagePicker(el.id, opts);
  });
});
JS;
}

function slugify(string $str): string {
    $str = mb_strtolower($str, 'UTF-8');
    $str = preg_replace('/[^a-z0-9\s-]/', '', $str);
    $str = preg_replace('/[\s-]+/', '-', $str);
    return trim($str, '-');
}

function unique_slug(string $table, string $base, int $excludeId = 0): string {
    $slug = slugify($base);
    $slug = substr($slug, 0, 100);
    $original = $slug;
    $i = 1;
    while (true) {
        $stmt = db()->prepare("SELECT id FROM {$table} WHERE slug = ? AND id != ?");
        $stmt->execute([$slug, $excludeId]);
        if (!$stmt->fetch()) break;
        $slug = $original . '-' . $i++;
    }
    return $slug;
}

function paginate(int $total, int $perPage, int $current): array {
    $pages = (int) ceil($total / $perPage);
    return [
        'total'   => $total,
        'pages'   => $pages,
        'current' => $current,
        'offset'  => ($current - 1) * $perPage,
        'limit'   => $perPage,
        'hasPrev' => $current > 1,
        'hasNext' => $current < $pages,
    ];
}

/**
 * Read a configuration value from k_vars, with a fallback default.
 * Results are cached for the life of the request.
 */
function k_var(string $name, mixed $default = null): mixed {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db()->query("SELECT config_name, config_value FROM k_vars")->fetchAll() as $row) {
                $cache[$row['config_name']] = $row['config_value'];
            }
        } catch (\Throwable $e) {
            // k_vars may not exist on a fresh install — fall back to defaults silently.
        }
    }
    return $cache[$name] ?? $default;
}

function format_date(string $datetime): string {
    $ts = strtotime($datetime);
    $diff = time() - $ts;
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff/60) . 'm ago';
    if ($diff < 86400)  return floor($diff/3600) . 'h ago';
    if ($diff < 604800) return floor($diff/86400) . 'd ago';
    return date('M j, Y', $ts);
}

function format_content(string $content): string {
    // Allowed HTML tags for safe output
    $allowed = '<p><br><strong><em><u><s><ul><ol><li><blockquote><code><pre><a><h3><h4><hr><img>';

    // ── Step 0: Resolve [attachment:N] tokens → lightbox HTML ────────────────
    // Each token references a row in the attachments table by id.
    // We batch-fetch all referenced ids in one query for efficiency.
    // Strip any whitespace/newlines around the token so they don't become <br>.
    if (preg_match_all('/\[attachment:(\d+)\]/', $content, $attMatches)) {
        $attIds  = array_unique(array_map('intval', $attMatches[1]));
        $placeholders = implode(',', array_fill(0, count($attIds), '?'));
        $attStmt = db()->prepare("SELECT id, filename FROM attachments WHERE id IN ($placeholders)");
        $attStmt->execute($attIds);
        $attRows = $attStmt->fetchAll(PDO::FETCH_KEY_PAIR); // id => filename

        $content = preg_replace_callback(
            '/\[attachment:(\d+)\]/',
            function ($m) use ($attRows) {
                $id  = (int)$m[1];
                if (!isset($attRows[$id])) return ''; // attachment deleted or invalid
                $url = htmlspecialchars(FORUM_URL . '/uploads/images/' . $attRows[$id], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                return '<a href="' . $url . '" class="post-img-link" title="Click to view full size">'
                     . '<img src="' . $url . '" alt="Attached image" class="post-img-thumb" loading="lazy">'
                     . '</a>';
            },
            $content
        );
    }

    // ── Step 1: Extract <pre>…</pre> blocks into a safe side-table ────────────
    // We do this BEFORE any other processing so that strip_tags(), htmlspecialchars(),
    // and nl2br() never touch code content. Each block is replaced by a unique token
    // made of non-printable bytes that cannot appear in normal user text.
    $codeBlocks = [];
    $tokenBase  = "\x02CODE";    // \x02 = STX  (never valid in HTML)
    $tokenEnd   = "\x03";        // \x03 = ETX

    $content = preg_replace_callback(
        '/<pre\b[^>]*>(.*?)<\/pre>/si',
        function ($m) use (&$codeBlocks, $tokenBase, $tokenEnd) {
            $inner = $m[1];

            // Unwrap a single <code>…</code> layer if present
            $inner = preg_replace('/^<code[^>]*>(.*)<\/code>$/si', '$1', trim($inner));

            // Decode any already-encoded HTML entities (avoid double-encoding)
            $inner = html_entity_decode($inner, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            // Fully escape: < > & " '  →  safe to survive strip_tags & friends
            $escaped = htmlspecialchars($inner, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            $token = $tokenBase . count($codeBlocks) . $tokenEnd;
            $codeBlocks[$token] = '<pre><code>' . $escaped . '</code></pre>';
            return $token;
        },
        $content
    );

    // ── Step 2: Detect whether the remaining content has any HTML markup ──────
    $hasHtml = (bool) preg_match('/<[a-zA-Z]+[\s>\/]/i', $content);

    if (!$hasHtml) {
        // Plain-text post: escape entities then convert newlines to <br>.
        // We must NOT run htmlspecialchars over the placeholder tokens, so we
        // split on tokens, process each text segment individually, then rejoin.
        $parts  = preg_split('/(\x02CODE\d+\x03)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        $output = '';
        foreach ($parts as $part) {
            if (isset($codeBlocks[$part])) {
                $output .= $codeBlocks[$part];   // restored code block, already escaped
            } else {
                $output .= nl2br(htmlspecialchars($part, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
        }
        // Plain-text posts still get acronym wrapping applied to the
        // escaped+nl2br output — same code path as the HTML branch via the
        // shared helper, so a "phpBB" in a plain-text post is wrapped in
        // <abbr> too. Code blocks already restored above are skipped because
        // the helper only operates on text between <…> tags and the
        // restored <pre><code> wrappers protect their contents.
        return apply_acronyms($output);
    }

    // ── Step 2b: HTML path — extract post-img-link anchors before strip_tags ──
    // strip_tags keeps allowed tags but strips ALL their attributes, which would
    // remove class="post-img-link" that the lightbox depends on. We protect them
    // with the same STX/ETX token approach used for code blocks above.
    // The regex also consumes any leading \n so it doesn't become a stray <br>.
    $imgLinks    = [];
    $ilTokenBase = "\x02IMGLINK";

    $content = preg_replace_callback(
        '/\n?<a\b[^>]*class=["\']post-img-link["\'][^>]*>.*?<\/a>/si',
        function ($m) use (&$imgLinks, $ilTokenBase, $tokenEnd) {
            $href = '';
            $src  = '';
            $alt  = '';
            if (preg_match('/href=["\']([^"\']+)["\']/', $m[0], $hm)) {
                $href = htmlspecialchars($hm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            if (preg_match('/src=["\']([^"\']+)["\']/', $m[0], $sm)) {
                $src = htmlspecialchars($sm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            if (preg_match('/alt=["\']([^"\']*)["\']/', $m[0], $am)) {
                $alt = htmlspecialchars($am[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            if (!$href || !$src) return $m[0]; // leave malformed tags for strip_tags to handle

            $safe  = '<a href="' . $href . '" class="post-img-link" title="Click to view full size">'
                   . '<img src="' . $src . '" alt="' . $alt . '" class="post-img-thumb" loading="lazy">'
                   . '</a>';
            $token = $ilTokenBase . count($imgLinks) . $tokenEnd;
            $imgLinks[$token] = $safe;
            return $token;
        },
        $content
    );

    // ── Step 3: HTML post — strip disallowed tags ─────────────────────────────
    $content = strip_tags($content, $allowed);

    // Strip newlines adjacent to block-level structural tags BEFORE the bare
    // newline-to-<br> conversion below. Without this, content like
    //     <ul>\n  <li>A</li>\n  <li>B</li>\n</ul>
    // becomes
    //     <ul><br>  <li>A</li><br>  <li>B</li><br></ul>
    // which is invalid HTML — <br> is only allowed in phrasing content.
    // Firefox silently drops the stray <br>s; Chromium renders them as
    // visible empty lines between list items / around blockquotes etc.
    // We collapse \s*\n adjacent to opening or closing block tags so the
    // newlines disappear instead of becoming <br>.
    $blockTags = 'ul|ol|li|blockquote|p|h3|h4|hr';
    // Newline (+ optional horizontal ws) immediately before a block tag
    $content = preg_replace('/[ \t]*\n[ \t]*(?=<\/?(?:' . $blockTags . ')\b)/i', '', $content);
    // Newline (+ optional horizontal ws) immediately after a block tag
    $content = preg_replace('/(<\/?(?:' . $blockTags . ')\b[^>]*>)[ \t]*\n[ \t]*/i', '$1', $content);

    // Convert bare newlines to <br>
    $content = str_replace("\n", '<br>', $content);

    // Remove any <br> tags immediately before an IMGLINK token — these come from
    // trailing newlines in the user's textarea that appear before attachment images.
    // Also collapse multiple consecutive <br> before a token into nothing.
    $content = preg_replace('/(<br\s*\/?>)+(\x02IMGLINK\d+\x03)/', '$2', $content);

    // Ensure consecutive IMGLINK tokens are separated by a <br> so images
    // stack vertically rather than running together inline.
    $content = preg_replace('/(\x03)(\x02IMGLINK)/', '$1<br>$2', $content);

    // ── Step 4: Sanitise <a> href attributes ─────────────────────────────────
    $content = preg_replace_callback(
        '/<a\b[^>]*>/i',
        function ($m) {
            if (preg_match('/href=["\']([^"\']*)["\']/', $m[0], $hm)) {
                $href = filter_var($hm[1], FILTER_VALIDATE_URL)
                    ? htmlspecialchars($hm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')
                    : '#';
            } else {
                $href = '#';
            }
            // Preserve class if it's a known safe value (post-img-link)
            $class = '';
            if (preg_match('/class=["\']([^"\']*)["\']/', $m[0], $cm)) {
                $safeClasses = ['post-img-link'];
                $cls = htmlspecialchars($cm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (in_array(trim($cls), $safeClasses)) {
                    $class = ' class="' . $cls . '"';
                }
            }
            // Post image links open in lightbox — others open in new tab
            if ($class) {
                return '<a href="' . $href . '"' . $class . ' title="Click to view full size">';
            }
            return '<a href="' . $href . '" rel="nofollow noopener" target="_blank">';
        },
        $content
    );

    // ── Step 5: Sanitise <img> src attributes (block javascript: URIs) ──────
    $content = preg_replace_callback(
        '/<img\b[^>]*>/i',
        function ($m) {
            $src   = '';
            $alt   = '';
            $class = '';
            if (preg_match('/src=["\']([^"\']*)["\']/', $m[0], $sm)) {
                $url = $sm[1];
                // Only allow http/https/relative URLs — block javascript: data: etc.
                if (preg_match('#^(https?://|/)#i', $url)) {
                    $src = htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            }
            if (preg_match('/alt=["\']([^"\']*)["\']/', $m[0], $am)) {
                $alt = htmlspecialchars($am[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            // Preserve known safe classes (post-img-thumb)
            if (preg_match('/class=["\']([^"\']*)["\']/', $m[0], $cm)) {
                $safeClasses = ['post-img-thumb'];
                $cls = htmlspecialchars($cm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (in_array(trim($cls), $safeClasses)) {
                    $class = ' class="' . $cls . '"';
                }
            }
            if (!$src) return ''; // drop img with no valid src
            return '<img src="' . $src . '" alt="' . $alt . '"' . $class . ' loading="lazy">';
        },
        $content
    );

    // ── Step 5b: Wrap admin-defined acronyms with <abbr title="…"> ──────────
    // Pulled from the `acronyms` table by the shared apply_acronyms() helper.
    // Runs BEFORE token restoration so the wrapping pass never sees the
    // body of any <pre>/<code> block (those are still placeholder tokens).
    $content = apply_acronyms($content);

    // ── Step 6: Restore code blocks and image links ────────────────────────────
    // Use the same split-and-rejoin approach so strip_tags cannot have eaten tokens
    $allTokens = array_merge($codeBlocks, $imgLinks);
    $parts  = preg_split('/(\x02(?:CODE|IMGLINK)\d+\x03)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
    $output = '';
    foreach ($parts as $part) {
        $output .= isset($allTokens[$part]) ? $allTokens[$part] : $part;
    }

    return $output;
}

/**
 * Wrap admin-defined acronyms in `<abbr title="...">` tags.
 *
 * Pulled from the `acronyms` table (admin-managed via `admin/acronyms.php`).
 * Match is case-sensitive and whole-word: "phpBB" matches "phpBB" but not
 * "PHPBB" or anything inside "phpBBeach". Word boundaries use
 * `[A-Za-z0-9]` lookarounds (rather than `\b`) so digit-bearing terms
 * behave correctly and `_` is NOT treated as part of a word.
 *
 * Terms are sorted longest-first so multi-word entries like
 * "Kiss Portal Extension" win over the single-word "Kiss".
 *
 * Within the input HTML we split on `<…>` so the replacement only runs
 * on text segments — attribute values like `alt="phpBB logo"` and href
 * URLs are protected from being wrapped. Existing `<abbr>` tags written
 * by an earlier pass would have their inner text re-matched here, but
 * `format_content()` calls this BEFORE re-emitting `<abbr>` and the
 * shipping `strip_tags` allow-list does not include `<abbr>`, so user
 * input never contains pre-existing `<abbr>` tags.
 *
 * The active acronym set is cached in a function-static for the
 * remainder of the request — rendering 20 posts on a thread page hits
 * the DB once. If the table doesn't exist yet (pre-migration) the
 * helper degrades to a no-op silently.
 */
function apply_acronyms(string $html): string {
    static $cache = null;
    if ($cache === null) {
        try {
            $cache = db()->query(
                'SELECT term, expansion FROM acronyms
                  WHERE is_active = 1
                  ORDER BY CHAR_LENGTH(term) DESC, term ASC'
            )->fetchAll();
        } catch (\PDOException $e) {
            $cache = []; // table doesn't exist yet
        }
    }
    if (empty($cache)) return $html;

    // Split on tags: even-index parts are text, odd-index are tags.
    $segments = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($segments as $i => $seg) {
        if ($i % 2 === 1) continue;   // skip tag segments
        if ($seg === '') continue;
        foreach ($cache as $a) {
            $term     = $a['term'];
            $titleEsc = htmlspecialchars($a['expansion'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $termEsc  = htmlspecialchars($term,           ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $pattern  = '/(?<![A-Za-z0-9])' . preg_quote($term, '/') . '(?![A-Za-z0-9])/';
            $segments[$i] = preg_replace(
                $pattern,
                '<abbr title="' . $titleEsc . '">' . $termEsc . '</abbr>',
                $segments[$i]
            );
        }
    }
    return implode('', $segments);
}

function avatar_url(?string $avatar, string $username): string {
    if ($avatar && file_exists(dirname(__DIR__) . '/uploads/avatars/' . $avatar)) {
        return FORUM_URL . '/uploads/avatars/' . $avatar;
    }
    // Gravatar-style color avatar using initials
    return FORUM_URL . '/avatar.php?u=' . urlencode($username);
}

function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Handle a post image upload.
 * Saves the original file at full size. The 300px width restriction is
 * applied via CSS in the post view; users can click to view the full image.
 *
 * @return array{ok:bool, filename:string|null, error:string|null}
 */
function upload_post_image(array $file): array {
    // No file uploaded — not an error, just skip
    if (empty($file['tmp_name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'filename' => null, 'error' => null];
    }

    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'Image exceeds server upload limit.',
        UPLOAD_ERR_FORM_SIZE  => 'Image exceeds form size limit.',
        UPLOAD_ERR_PARTIAL    => 'Image was only partially uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server temporary directory missing.',
        UPLOAD_ERR_CANT_WRITE => 'Server could not write the file.',
        UPLOAD_ERR_EXTENSION  => 'Upload blocked by server extension.',
    ];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'filename' => null, 'error' => $uploadErrors[$file['error']] ?? 'Upload error.'];
    }

    if ($file['size'] > MAX_POST_IMAGE_SIZE) {
        return ['ok' => false, 'filename' => null, 'error' => 'Image must be under 2 MB.'];
    }

    // Validate MIME type from actual file contents, not the extension
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);

    if (!in_array($mime, ALLOWED_POST_IMAGE_TYPES)) {
        return ['ok' => false, 'filename' => null, 'error' => 'Only JPEG, PNG, GIF and WebP images are allowed.'];
    }

    $ext = match($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        default      => 'jpg',
    };

    $dir = dirname(__DIR__) . '/uploads/images/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filename = 'img_' . bin2hex(random_bytes(10)) . '.' . $ext;
    $dest     = $dir . $filename;

    // Save the original file unchanged — full resolution preserved for lightbox
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'filename' => null, 'error' => 'Could not save the image. Please try again.'];
    }

    return ['ok' => true, 'filename' => $filename, 'error' => null];
}

/**
 * Save an uploaded image filename to the attachments table.
 * Must be called AFTER the post has been inserted so post_id exists.
 *
 * @return int  The new attachment_id
 */
function save_attachment(int $postId, string $filename): int {
    db()->prepare('INSERT INTO attachments (post_id, filename) VALUES (?, ?)')->execute([$postId, $filename]);
    return (int)db()->lastInsertId();
}

// Update user last seen
if (is_logged_in()) {
    static $lastSeenUpdated = false;
    if (!$lastSeenUpdated) {
        db()->prepare('UPDATE users SET last_seen = NOW() WHERE id = ?')->execute([$_SESSION['user_id']]);
        $lastSeenUpdated = true;
    }
}

// ── Load site identity from config table ──────────────────────────────────────
// Overrides the FORUM_NAME / FORUM_TAGLINE constants defined in config.php with
// values from the database config table, falling back gracefully if the table
// doesn't exist yet or the rows haven't been seeded.
(function () {
    try {
        $stmt = db()->query("SELECT config_name, config_value FROM config WHERE config_name IN ('sitename','site_desc','allow_attachments','main_logo_filename')");
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $GLOBALS['_site_name']         = $rows['sitename']            ?? FORUM_NAME;
        $GLOBALS['_site_desc']         = $rows['site_desc']           ?? FORUM_TAGLINE;
        $GLOBALS['_allow_attachments'] = ($rows['allow_attachments']  ?? '1') === '1';
        $GLOBALS['_main_logo']         = $rows['main_logo_filename']  ?: 'logo.png';

    } catch (PDOException $e) {
        // config table doesn't exist yet — silently use the constants
        $GLOBALS['_site_name']         = FORUM_NAME;
        $GLOBALS['_site_desc']         = FORUM_TAGLINE;
        $GLOBALS['_allow_attachments'] = true;
        $GLOBALS['_main_logo']         = 'logo.png';
    }
})();

if (!defined('SITE_NAME'))          define('SITE_NAME',          $GLOBALS['_site_name']);
if (!defined('SITE_DESC'))          define('SITE_DESC',          $GLOBALS['_site_desc']);
if (!defined('ALLOW_ATTACHMENTS'))  define('ALLOW_ATTACHMENTS',  $GLOBALS['_allow_attachments']);
if (!defined('MAIN_LOGO_FILENAME')) define('MAIN_LOGO_FILENAME', $GLOBALS['_main_logo']);

// Template engine
require_once __DIR__ . '/View.php';

// Portal block dispatcher (self-registers a block-class autoloader)
require_once __DIR__ . '/BlockEngine.php';

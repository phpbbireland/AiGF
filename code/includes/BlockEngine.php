<?php
// includes/BlockEngine.php
//
// Dispatcher that renders portal-style blocks for a given column on a given
// page. Blocks live in the k_blocks table and are implemented as small PHP
// classes under includes/blocks/.
//
// USAGE (in a controller):
//   $pageId    = BlockEngine::pageId('portal');
//   $leftCol   = BlockEngine::render('L', $pageId);
//   $centreCol = BlockEngine::render('C', $pageId);
//   $rightCol  = BlockEngine::render('R', $pageId);
//
// Block contract:
//   Each row's html_file_name (e.g. "block_recent_topics_wide.html") maps to
//   a class under App\Blocks — the extension and the "block_" prefix are
//   stripped, and the remainder is studly-cased, so:
//       "block_recent_topics_wide.html"  ->  App\Blocks\RecentTopicsWide
//   Each class must expose a single method:
//       public function render(array $context): string
//   which returns the fully rendered HTML for the block (normally by calling
//   View::make('blocks.something', [...])).
//
// Visibility rules (same columns Kiss Portal uses):
//   active      — must be 1
//   view_all    — if 0, guests never see the block
//   view_groups — comma-separated role names ("admin,moderator"); empty/"0"
//                 means "no group restriction"
//   view_pages  — comma-separated k_pages.page_id values; empty means "all pages"
//
// Page IDs match k_pages rows. A small set of named constants makes controller
// code more readable than bare integers.

class BlockEngine
{
    // Column positions — must match the ENUM-ish char(1) values in k_blocks.position
    public const POS_LEFT   = 'L';
    public const POS_CENTRE = 'C';
    public const POS_RIGHT  = 'R';

    // Page ids are looked up by name from k_pages — see pageId() / pageIdByName()
    // below. The previous static PAGE_* constants were retired on 2026-05-02
    // because they duplicated k_pages and had silently drifted from it
    // (PAGE_VIEWFORUM = 3 while k_pages id 3 was named "viewtopic", etc.).
    // Controllers now call BlockEngine::pageId('index'), 'portal', 'viewforum',
    // 'inprogress', 'viewforums' — k_pages is the single source of truth, the
    // admin UI in admin/k_pages.php is its editor.

    /** In-request cache of block class instances keyed by class name */
    private static array $instances = [];

    /** Per-request cache of page_name → page_id from k_pages (lazy-loaded). */
    private static ?array $pageNameCache = null;

    // ── Public API ──────────────────────────────────────────────────────────

    /**
     * Render all enabled blocks for a position on a given page.
     *
     * Missing block implementations are skipped silently (logged to error_log)
     * so a stray/outdated k_blocks row never takes the whole page down.
     *
     * @param string $position  self::POS_LEFT | POS_CENTRE | POS_RIGHT
     * @param int    $pageId    k_pages.page_id of the current page
     * @param array  $context   Extra data passed through to each block
     * @return string           Concatenated HTML for every visible block
     */
    public static function render(string $position, int $pageId, array $context = []): string
    {
        $user = current_user();

        $stmt = db()->prepare("
            SELECT id, ndx, title, position, type, html_file_name, img_file_name,
                   view_all, view_groups, view_pages
            FROM k_blocks
            WHERE active = 1 AND position = ?
            ORDER BY ndx ASC
        ");
        $stmt->execute([$position]);
        $rows = $stmt->fetchAll();

        $out = '';
        foreach ($rows as $row) {
            if (!self::visible($row, $pageId, $user)) {
                continue;
            }

            $class = self::resolveClass($row['html_file_name']);
            if ($class === null || !class_exists($class)) {
                error_log("BlockEngine: no class for html_file_name='{$row['html_file_name']}' (id={$row['id']})");
                continue;
            }

            $instance = self::$instances[$class] ??= new $class();
            if (!method_exists($instance, 'render')) {
                error_log("BlockEngine: {$class} has no render() method");
                continue;
            }

            try {
                $out .= $instance->render(array_merge($context, [
                    'block_id'    => (int)$row['id'],
                    'block_title' => $row['title'],
                    'block_image' => (string)($row['img_file_name'] ?? ''),
                    'block_type'  => $row['type'],
                    'page_id'     => $pageId,
                    'user'        => $user,
                ]));
            } catch (\Throwable $e) {
                error_log("BlockEngine: {$class}::render() threw: " . $e->getMessage());
                // Swallow — one bad block shouldn't blank the whole column.
            }
        }

        return $out;
    }

    // ── Visibility ──────────────────────────────────────────────────────────

    private static function visible(array $row, int $pageId, ?array $user): bool
    {
        // Guest gate: view_all = 0 hides the block from logged-out users.
        if ((int)$row['view_all'] === 0 && !$user) {
            return false;
        }

        // Page gate: empty view_pages means "every page". Otherwise look for $pageId.
        $pagesRaw = trim((string)$row['view_pages']);
        if ($pagesRaw !== '') {
            $allowed = array_map('intval', array_filter(explode(',', $pagesRaw), 'strlen'));
            if ($allowed && !in_array($pageId, $allowed, true)) {
                return false;
            }
        }

        // Group gate: "0" or empty means "no restriction".
        $groupsRaw = trim((string)$row['view_groups']);
        if ($groupsRaw !== '' && $groupsRaw !== '0') {
            $role = $user['role'] ?? 'guest';
            $allowedRoles = array_map('trim', explode(',', strtolower($groupsRaw)));
            if (!in_array(strtolower($role), $allowedRoles, true)) {
                return false;
            }
        }

        return true;
    }

    // ── k_pages helpers ─────────────────────────────────────────────────────

    /**
     * Look up a page id by its k_pages.page_name. Returns null if unknown.
     *
     * Loads the entire k_pages table once on first call and caches the map for
     * the rest of the request. Lets controllers ask for "viewforums" instead of
     * hardcoding 13, and lets admins add new pages without code changes — but
     * the PAGE_* constants are still the safer bet for the well-known set,
     * since a typo there blows up at parse time instead of silently rendering
     * nothing on every page.
     */
    public static function pageIdByName(string $name): ?int
    {
        if (self::$pageNameCache === null) {
            self::$pageNameCache = [];
            try {
                $rows = db()->query('SELECT page_id, page_name FROM k_pages')->fetchAll();
            } catch (\Throwable $e) {
                error_log('BlockEngine::pageIdByName db error: ' . $e->getMessage());
                $rows = [];
            }
            foreach ($rows as $r) {
                self::$pageNameCache[(string)$r['page_name']] = (int)$r['page_id'];
            }
        }
        return self::$pageNameCache[$name] ?? null;
    }

    /**
     * Strict variant of pageIdByName() — throws if the name isn't in k_pages.
     *
     * Controllers use this for the page they're rendering, so a typo
     * ('vieforum' instead of 'viewforum') or a missing k_pages row blows up at
     * request time with a clear "Unknown page" message instead of silently
     * returning 0 / null and rendering an empty block column. Equivalent to
     * the old static `PAGE_*` constants in fail-loud behaviour but reads
     * k_pages instead of duplicating it.
     */
    public static function pageId(string $name): int
    {
        $id = self::pageIdByName($name);
        if ($id === null) {
            throw new \RuntimeException("BlockEngine::pageId(): unknown k_pages.page_name '{$name}'");
        }
        return $id;
    }

    // ── html_file_name → class name ─────────────────────────────────────────

    /**
     * "block_recent_topics_wide.html"  ->  App\Blocks\RecentTopicsWide
     * Returns null if the name can't be parsed.
     */
    public static function resolveClass(string $htmlFileName): ?string
    {
        $base = preg_replace('/\.(html|php|tpl)$/i', '', $htmlFileName);
        if ($base === null || $base === '') return null;

        // Strip the conventional "block_" prefix — accept either with or without.
        if (str_starts_with($base, 'block_')) {
            $base = substr($base, 6);
        }

        $parts = preg_split('/[_\-]+/', $base);
        $studly = '';
        foreach ($parts as $p) {
            if ($p === '') continue;
            $studly .= ucfirst(strtolower($p));
        }
        if ($studly === '') return null;

        return 'App\\Blocks\\' . $studly;
    }

    /**
     * Reverse of resolveClass() — turn a StudlyCase leaf back into the
     * conventional "block_snake_case.html" name that would dispatch to it.
     * Used by the admin CP so new rows can pick from real wired-up classes.
     */
    public static function classToHtmlName(string $studly): string
    {
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $studly));
        return 'block_' . $snake . '.html';
    }

    /**
     * Scan includes/blocks/*.php and return a map keyed by the conventional
     * html_file_name the admin would store in k_blocks, e.g.
     *   ['block_top_posters.html' => 'TopPosters', ...]
     * Only files whose classes follow the App\Blocks\Xxx convention are listed.
     */
    public static function availableBlocks(): array
    {
        $dir = dirname(__DIR__) . '/includes/blocks';
        if (!is_dir($dir)) return [];

        $out = [];
        foreach (glob($dir . '/*.php') ?: [] as $path) {
            $leaf = basename($path, '.php');
            // Ignore files that don't follow the StudlyCase-single-class convention
            if (!preg_match('/^[A-Z][A-Za-z0-9]*$/', $leaf)) continue;
            $out[self::classToHtmlName($leaf)] = $leaf;
        }
        ksort($out);
        return $out;
    }

    // ── Autoloader for block classes ────────────────────────────────────────

    /**
     * Register a PSR-4-ish autoloader that resolves App\Blocks\Xxx to
     * includes/blocks/Xxx.php. Called once from functions.php bootstrap.
     */
    public static function registerAutoloader(): void
    {
        static $registered = false;
        if ($registered) return;
        $registered = true;

        spl_autoload_register(function (string $class): void {
            $prefix = 'App\\Blocks\\';
            if (!str_starts_with($class, $prefix)) return;

            $leaf = substr($class, strlen($prefix));
            $file = dirname(__DIR__) . '/includes/blocks/' . $leaf . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        });
    }
}

BlockEngine::registerAutoloader();

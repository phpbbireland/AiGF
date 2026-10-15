<?php
// includes/View.php
// Lightweight template engine for the forum.
//
// USAGE (in a controller):
//   View::render('home/index', ['categories' => $cats, 'pageTitle' => 'Forums']);
//
// TEMPLATE SYNTAX (in templates/views/*.php):
//   {{ $var }}          — echo, HTML-escaped
//   {{{ $var }}}        — echo, raw (unescaped)
//   @if / @elseif / @else / @endif
//   @foreach($arr as $k => $v) / @endforeach
//   @include('partial.name')
//   @url('path')        — outputs FORUM_URL . '/path'
//   @asset('path')      — outputs FORUM_URL . '/assets/path'
//
// Templates are plain PHP files; the directives above are compiled to PHP
// before execution, so you can also just write plain PHP if needed.

class View {

    /** Base directory for view templates */
    private static string $viewsDir;

    /** Global data available in every template (layout vars etc.) */
    private static array $shared = [];

    /** Simple compile-time cache (per request) */
    private static array $compiled = [];

    // ── Initialise ────────────────────────────────────────────────────────────
    public static function init(): void {
        self::$viewsDir = dirname(__DIR__) . '/templates/views';
    }

    // ── Share data with every template ────────────────────────────────────────
    public static function share(string $key, mixed $value): void {
        self::$shared[$key] = $value;
    }

    // ── Render a view and return the HTML string ──────────────────────────────
    public static function make(string $view, array $data = []): string {
        $file = self::$viewsDir . '/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($file)) {
            throw new RuntimeException("View not found: {$view} (looked for {$file})");
        }

        $vars = array_merge(self::$shared, $data);

        ob_start();
        self::includeTemplate($file, $vars);
        return ob_get_clean();
    }

    // ── Render and immediately echo ───────────────────────────────────────────
    public static function render(string $view, array $data = []): void {
        echo self::make($view, $data);
    }

    // ── Include a partial from within a template ──────────────────────────────
    public static function partial(string $view, array $extra = [], array $parentVars = []): string {
        $file = self::$viewsDir . '/' . str_replace('.', '/', $view) . '.php';
        if (!file_exists($file)) {
            throw new RuntimeException("Partial not found: {$view}");
        }
        $vars = array_merge(self::$shared, $parentVars, $extra);
        ob_start();
        self::includeTemplate($file, $vars);
        return ob_get_clean();
    }

    // ── Execute a template file with extracted variables ──────────────────────
    private static function includeTemplate(string $__file, array $__vars): void {
        // Make helper functions available as $tpl->method() or standalone
        extract($__vars, EXTR_SKIP);

        // Expose a $view helper object inside templates
        $view = new class($__vars) {
            public function __construct(private array $vars) {}

            /** Echo escaped */
            public function e(mixed $val): string {
                return htmlspecialchars((string)$val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            /** URL helper */
            public function url(string $path = ''): string {
                return FORUM_URL . ($path ? '/' . ltrim($path, '/') : '');
            }

            /** Asset URL helper */
            public function asset(string $path): string {
                return FORUM_URL . '/assets/' . ltrim($path, '/');
            }

            /**
             * Versioned asset URL — appends ?v=<mtime> so browsers refetch
             * whenever the underlying JS/CSS changes on disk. Falls back to
             * a plain URL if the file can't be stat'd.
             */
            public function assetV(string $path): string {
                $rel  = ltrim($path, '/');
                $full = dirname(__DIR__) . '/assets/' . $rel;
                $v    = @filemtime($full);
                $url  = FORUM_URL . '/assets/' . $rel;
                return $v ? $url . '?v=' . $v : $url;
            }

            /** Include a partial, passing current scope + extras */
            public function partial(string $name, array $extra = []): string {
                return View::partial($name, $extra, $this->vars);
            }

            /** Pagination helper: returns HTML string */
            public function paginate(array $pag, string $baseUrl): string {
                if ($pag['pages'] <= 1) return '';
                $sep = str_contains($baseUrl, '?') ? '&' : '?';
                $html = '<nav class="pagination">';
                if ($pag['hasPrev']) {
                    $html .= '<a class="page-link" href="' . $baseUrl . $sep . 'page=' . ($pag['current'] - 1) . '">‹</a>';
                }
                for ($p = 1; $p <= $pag['pages']; $p++) {
                    if ($p === $pag['current']) {
                        $html .= '<span class="page-link active">' . $p . '</span>';
                    } elseif ($p === 1 || $p === $pag['pages'] || abs($p - $pag['current']) <= 2) {
                        $html .= '<a class="page-link" href="' . $baseUrl . $sep . 'page=' . $p . '">' . $p . '</a>';
                    } elseif (abs($p - $pag['current']) === 3) {
                        $html .= '<span class="page-ellipsis">…</span>';
                    }
                }
                if ($pag['hasNext']) {
                    $html .= '<a class="page-link" href="' . $baseUrl . $sep . 'page=' . ($pag['current'] + 1) . '">›</a>';
                }
                $html .= '</nav>';
                return $html;
            }

            /** Format a datetime string as relative or absolute */
            public function date(string $datetime): string {
                return format_date($datetime);
            }

            /** Avatar URL */
            public function avatar(?string $file, string $username): string {
                return avatar_url($file, $username);
            }

            /** CSRF hidden field */
            public function csrf(): string {
                return csrf_field();
            }

            /** Badge HTML */
            public function badge(string $role): string {
                return '<span class="badge badge-' . $this->e($role) . '">' . ucfirst($this->e($role)) . '</span>';
            }

            /**
             * Username link with optional group-colour tinting.
             *
             * Pass a user-shaped row (must have `username` + an id key — accepts
             * `id` or `user_id` to match the various block SELECT alias styles).
             * If `group_colour` is set and is a valid 6-char hex (no leading `#`,
             * matching the storage format on `groups.colour`), it's emitted as an
             * inline `style="color:#…"`. Anything else (missing, empty, malformed)
             * falls through to a plain link with no inline style.
             *
             *   $opts['class']  CSS classes to set on the <a>
             *   $opts['style']  Extra inline style appended after the colour
             *
             * Used by every block view that renders a username.
             */
            public function username(array $u, array $opts = []): string {
                $id     = (int)($u['user_id'] ?? $u['id'] ?? 0);
                $name   = (string)($u['username'] ?? '');
                $colour = strtoupper(trim((string)($u['group_colour'] ?? '')));

                $href  = $this->url('profile.php?id=' . $id);
                $style = '';
                if (preg_match('/^[0-9A-F]{6}$/', $colour)) {
                    $style = 'color:#' . $colour;
                }
                if (!empty($opts['style'])) {
                    $style = $style === '' ? (string)$opts['style']
                                           : $style . ';' . (string)$opts['style'];
                }

                $attrs = 'href="' . $this->e($href) . '"';
                if (!empty($opts['class'])) {
                    $attrs .= ' class="' . $this->e((string)$opts['class']) . '"';
                }
                if ($style !== '') {
                    $attrs .= ' style="' . $this->e($style) . '"';
                }
                return '<a ' . $attrs . '>' . $this->e($name) . '</a>';
            }

            /**
             * Post-type chip for posts.type values 1/2/3. Returns empty string for
             * 0 (normal) or any unknown value, so the caller can drop it into the
             * post-meta strip unconditionally.
             *
             *   1 → 📌 Announcement   (paired with Announcements centre block)
             *   2 → 📰 News           (paired with News centre block)
             *   3 → ⭐ Special         (reserved for featured/highlighted posts)
             */
            public function postTypeBadge(int $type): string {
                static $map = [
                    1 => ['announcement', '📌', 'Announcement'],
                    2 => ['news',         '📰', 'News'],
                    3 => ['special',      '⭐', 'Special'],
                ];
                if (!isset($map[$type])) return '';
                [$cls, $icon, $label] = $map[$type];
                return '<span class="badge badge-' . $cls . '" title="' . $label . ' post">'
                     . $icon . ' ' . $label . '</span>';
            }

            /**
             * Resolve a k_menus.menu_icon filename to a full asset URL.
             * Falls back to default.png when the field is empty or set to the
             * legacy 'none.gif' placeholder (no longer shipped).
             */
            public function menuIcon(string $filename): string {
                $f = trim($filename);
                if ($f === '' || $f === 'none.gif') $f = 'default.png';
                return FORUM_URL . '/assets/images/block_images/menu/' . $f;
            }

            /** Country flag emoji */
            public function flag(string $code): string {
                return country_flag($code);
            }

            /** Number formatted */
            public function num(int|float $n): string {
                return number_format($n);
            }

            /**
             * Forum / category icon — stored field is either a single emoji
             * ("💬") or a prefixed image path ("forum_icons/news.png").
             *
             *   $textOnly  true → always return plain text (used inside
             *              <option> tags or other no-HTML contexts).
             *              An image path with $textOnly returns ''.
             *   $cssClass  class to put on the returned <img>, defaults to
             *              'forum-icon-img'.
             */
            public function forumIcon(string $icon, bool $textOnly = false, string $cssClass = 'forum-icon-img'): string {
                if ($icon === '') return '';
                if (strpos($icon, '/') !== false) {
                    if ($textOnly) return '';
                    $parts = array_map('rawurlencode', explode('/', $icon));
                    $src   = FORUM_URL . '/assets/images/' . implode('/', $parts);
                    return '<img class="' . $this->e($cssClass) . '" src="' . $src
                         . '" alt="" aria-hidden="true">';
                }
                return $this->e($icon);
            }

            /**
             * Block heading — renders title on the left with an optional icon
             * on the right. Used by every k_blocks partial so the header markup
             * stays consistent.
             *
             *   $image  Either a bare filename (legacy — resolved from
             *           assets/images/block_images/block/) or a prefixed path
             *           like "forum_icons/star.png" (resolved from
             *           assets/images/). Empty, "none.gif", or "_blank.gif"
             *           suppresses the icon.
             *   $tag    'h3' (default — sidebar blocks) or 'h2' (wide blocks).
             */
            public function blockTitle(string $title, string $image = '', string $tag = 'h3'): string {
                $tag    = in_array($tag, ['h1','h2','h3','h4','h5','h6'], true) ? $tag : 'h3';
                $noIcon = ['', 'none.gif', '_blank.gif', 'spacer.gif', 'bf_trans.gif'];
                $hasImg = !in_array($image, $noIcon, true);

                $out  = '<' . $tag . ' class="block-title">';
                $out .= '<span class="block-title-text">' . $this->e($title) . '</span>';
                if ($hasImg) {
                    // Prefixed paths (e.g. "forum_icons/star.png") are relative
                    // to /assets/images/. Bare filenames keep the legacy
                    // block_images/block/ default so existing rows still work.
                    if (strpos($image, '/') !== false) {
                        $parts = array_map('rawurlencode', explode('/', $image));
                        $src   = FORUM_URL . '/assets/images/' . implode('/', $parts);
                    } else {
                        $src = FORUM_URL . '/assets/images/block_images/block/' . rawurlencode($image);
                    }
                    $out .= '<img class="block-title-icon" src="' . $src
                         .  '" alt="" aria-hidden="true">';
                }
                $out .= '</' . $tag . '>';
                return $out;
            }
        };

        require $__file;
    }

    // ── Render a view wrapped in the main layout ─────────────────────────────
    public static function renderWithLayout(
        string $view,
        array  $data         = [],
        array  $scripts      = [],
        string $inlineScript = ''
    ): void {
        $data['currentUser']  = current_user();
        $data['scripts']      = $scripts;
        $data['inlineScript'] = $inlineScript;
        $data['content']      = self::make($view, $data);
        self::render('layout', $data);
    }
}

    // Auto-initialise when the class is loaded
View::init();

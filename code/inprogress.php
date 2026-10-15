<?php
// ============================================================================
// inprogress.php — scratch / test page with persistent textarea (admin-only)
// ============================================================================
// Bare working page. Same layout shell as index.php (so the site header,
// nav, and footer all render normally). Centre column is a card with a
// text input and a textarea for manual testing. The textarea content is
// persisted to disk for admins via the Save button — survives a reload
// and round-trips through the file system. The single-line input field
// is intentionally not persisted (it stays a quick scratch slot).
//
// Persistence:
//   File: uploads/scratch/inprogress.txt
//   Folder is denied to direct HTTP access via uploads/scratch/.htaccess —
//   only this controller reads/writes it.
//
// Sidebars are block-driven via BlockEngine, same as index.php / portal.php:
// any block whose `view_pages` is empty (= "all pages") will appear here, and
// blocks targeted at the inprogress page id (12) will too. Otherwise the
// sidebar collapses and the centre reclaims the space.
//
// This file is intentionally not linked from anywhere — visit it directly
// at /forum/inprogress.php.
// ============================================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

// ── Persistence paths ──────────────────────────────────────────────────────
const INPROGRESS_MAX_BYTES = 1024 * 1024;           // 1 MB cap on saved text
$scratchDir  = __DIR__ . '/uploads/scratch';
$scratchFile = $scratchDir . '/inprogress.txt';

// ── Save handler (admin-only, CSRF-checked) ────────────────────────────────
// Accepts POST { action=save_scratch, scratch_text=… }. Non-admins are
// silently ignored — the form isn't even rendered for them. Size is capped
// server-side so a runaway paste can't fill the disk.
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'save_scratch'
    && csrf_verify()) {

    if (!is_admin()) {
        set_flash('error', 'Admins only.');
    } else {
        // File format: first line = title, remainder = body.
        // Title is forced single-line by stripping any \r / \n the browser
        // might send (e.g. if someone pastes a multi-line value into the
        // <input>). Body is left untouched so the user's own newlines and
        // formatting survive the round-trip exactly.
        $title = (string)($_POST['scratch_title'] ?? '');
        $title = str_replace(["\r", "\n"], '', $title);
        $text  = (string)($_POST['scratch_text']  ?? '');

        $payload = $title . "\n" . $text;

        if (strlen($payload) > INPROGRESS_MAX_BYTES) {
            set_flash('error', 'Scratch text too large (1 MB max).');
        } elseif (!is_dir($scratchDir)) {
            set_flash('error', 'Scratch directory missing: uploads/scratch/.');
        } elseif (!is_writable($scratchDir)) {
            set_flash('error', 'Scratch directory not writable. Try `chmod 775 uploads/scratch`.');
        } else {
            $bytes = @file_put_contents($scratchFile, $payload, LOCK_EX);
            if ($bytes === false) {
                set_flash('error', 'Failed to write scratch file.');
            } else {
                set_flash('success', 'Saved (' . $bytes . ' bytes).');
            }
        }
    }
    redirect(FORUM_URL . '/inprogress.php');
}

// ── Load saved text on GET ─────────────────────────────────────────────────
// File format: first line is the title, everything after the first \n is the
// body. Three views of the saved content are passed to the template:
//   $savedTitle     — single-line title, used to pre-fill the title input.
//   $savedText      — body bytes, used to pre-fill the textarea (escaped by
//                     the view via $view->e() so tags appear as literal text
//                     inside the textarea, which is correct).
//   $savedRendered  — body run through format_content(), the same sanitiser
//                     the post pipeline uses (whitelist of allowed tags, href
//                     filtering, code-block protection). Displayed below the
//                     form as a live preview, matching how a real post
//                     renders in thread.php.
$savedTitle = '';
$savedText  = '';
if (is_readable($scratchFile)) {
    $raw   = (string)file_get_contents($scratchFile);
    $parts = explode("\n", $raw, 2);
    // Defensive \r strip — covers files written under \r\n line endings.
    $savedTitle = rtrim($parts[0] ?? '', "\r");
    $savedText  = $parts[1] ?? '';
}
$savedRendered = $savedText !== '' ? format_content($savedText) : '';

// ── Sidebars: rendered by BlockEngine ──────────────────────────────────────
$pageId   = BlockEngine::pageId('inprogress');
$leftCol  = BlockEngine::render(BlockEngine::POS_LEFT,  $pageId);
$rightCol = BlockEngine::render(BlockEngine::POS_RIGHT, $pageId);

View::renderWithLayout('forum.inprogress', [
    'pageTitle'     => 'In Progress',
    'leftCol'       => $leftCol,
    'rightCol'      => $rightCol,
    'savedTitle'    => $savedTitle,
    'savedText'     => $savedText,
    'savedRendered' => $savedRendered,
]);

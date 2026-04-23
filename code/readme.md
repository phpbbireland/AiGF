# Fóram / CommunityHub — PHP/MySQL Forum Package

I will add the source later, here is the up to date readme.. 
A complete, secure forum application built with PHP 8 and MySQL, now with a
block-driven portal page and admin-configurable sidebar layouts.

## Features

### Member features

- Secure registration & login — bcrypt password hashing, CSRF protection, session hardening
- Password strength meter on registration
- User profiles with bio, post count, join date, and recent activity
- Create threads (choose forum, write title + rich content)
- Paginated replies with an inline rich-text editor (bold, italic, code, quotes, links, lists)
- Members can edit their own posts; moderators can edit any
- Ajax heart reactions (likes)

### Forum structure

- Categories → Forums → Threads → Posts
- Pre-seeded with 5 categories, 16 forums, 8 threads, and 30+ sample posts
- Per-forum thread/post counts and last-post previews
- Thread view/reply counts, pinned/locked badges
- Pagination across threads and posts

### Moderation

- Moderators can pin/unpin threads, lock/unlock threads, and delete/edit any post
- Admins get all moderator powers plus the full admin panel

### Portal page & block system

The separate `/portal.php` landing page is entirely driven by rows in the
`k_blocks` table via `includes/BlockEngine.php`. The forum index (`/index.php`)
uses the same engine for its left and right sidebars while keeping the forum
listing inline in the centre. See **Portal & Block System** below for details.

### Admin panel (`/admin/`)

- Dashboard — live stats (users, threads, posts, new today)
- Categories — create / edit / delete; name, icon, description, sort order, visibility
- Forums — create / edit / delete; reassign category, change icon, slug, description
- Threads — search, pin, lock, hide/restore
- Users — search, change role (member / moderator / admin), ban/unban, delete
- **Portal → Blocks** — create / edit / reorder / toggle blocks; per-page visibility (`view_pages`), group restrictions (`view_groups`), guest visibility (`view_all`)
- **Portal → k_vars** — key/value settings editor for portal/block config (e.g. `k_recent_days`, `k_news_items_to_display`); add / edit / delete keys
- **Portal → k_resources** — word registry used by the portal engine; filter by type (`R` = reserved, `V` = variable), add / edit / delete entries
- Config — site-wide settings (site name, tagline, `main_logo_filename`, etc.)

### Security

- CSRF tokens on every form
- Prepared statements (PDO) — no SQL injection
- `htmlspecialchars()` on all output — no XSS
- `strip_tags()` with allowlist on post content
- bcrypt password hashing, cost 12
- `session_regenerate_id()` on login
- HttpOnly, `SameSite=Lax` session cookies
- IP address logging on posts

---

## File structure

```
forum/
├── config.php                  # DB config, constants, site-wide settings
├── index.php                   # Forum home — categories + forums list, block-driven sidebars
├── portal.php                  # Portal landing page — fully block-driven (L/C/R columns)
├── forum.php                   # Thread list for a forum
├── thread.php                  # Thread view + reply form
├── new-thread.php              # Create a new thread
├── edit-post.php               # Edit an existing post
├── delete-post.php             # Delete a post (moderator+)
├── profile.php                 # User profile page
├── search.php                  # Search posts / threads / users
├── recent-threads.php          # Standalone "latest threads" feed
├── register.php / login.php / logout.php
├── avatar.php                  # SVG avatar generator
├── like.php                    # Ajax like handler
├── install.php                 # Setup verification page (delete after install)
│
├── admin/
│   ├── index.php               # Admin dashboard
│   ├── categories.php          # Manage categories
│   ├── forums.php              # Manage forums
│   ├── threads.php             # Manage threads
│   ├── users.php               # Manage users
│   ├── edit-user.php
│   ├── settings.php            # Site config
│   ├── blocks.php              # Manage portal blocks
│   ├── k-vars.php              # Manage k_vars key/value settings
│   └── k-resources.php         # Manage k_resources word registry (R/V)
│
├── includes/
│   ├── functions.php           # Core helpers: auth, db(), k_var(), URL, etc.
│   ├── View.php                # View engine (templates/views, partials, helpers)
│   ├── BlockEngine.php         # Block dispatcher — maps k_blocks rows → block classes
│   ├── countries.php           # Country list helper
│   └── blocks/                 # One class per portal block
│       ├── Ads.php
│       ├── Announcements.php
│       ├── Clock.php
│       ├── ForumCategories.php
│       ├── ForumStats.php
│       ├── LastOnline.php
│       ├── Links.php
│       ├── MenusLinks.php      # extends MenusNav (menu_type = 5)
│       ├── MenusNav.php        # the base menu block
│       ├── MenusSub.php        # extends MenusNav (menu_type = 2)
│       ├── News.php            # Latest posts with excerpt
│       ├── OnlineUsers.php
│       ├── RecentThreads.php   # Compact sidebar thread list
│       ├── RecentTopicsWide.php # Wide centre-column thread list
│       ├── Search.php
│       ├── TheTeams.php        # Admin + moderator roster
│       ├── TopPosters.php
│       ├── TopTopics.php
│       ├── UserInformation.php
│       └── Welcome.php
│
├── templates/
│   ├── header.php              # (legacy inline template, kept for compatibility)
│   ├── footer.php              # (legacy inline template)
│   └── views/
│       ├── layout.php          # Master HTML layout used by View::renderWithLayout()
│       ├── partials/
│       │   ├── navbar.php      # Site header / top nav
│       │   ├── footer.php
│       │   ├── styles.php
│       │   └── admin_nav.php
│       ├── forum/              # Per-page views (index, portal, thread, forum, etc.)
│       ├── admin/              # Admin CP views
│       ├── auth/               # Login / register views
│       └── blocks/             # One partial per block class (e.g. welcome.php, clock.php)
│
├── assets/
│   ├── main.css                # Main stylesheet (.main-grid, sidebars, forum rows, etc.)
│   ├── theme-winter.css        # Alternate theme
│   └── images/                 # Icons, team badges, bbcode icons, links, slideshow
│
├── build/
│   ├── database.sql            # Full schema + seed data (import this)
│   ├── migrate_*.sql           # Individual feature migrations
│   └── ...
│
└── docs/                       # Dev notes, chat history backups (denied by .htaccess)
```

---

## Installation

### Requirements

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- PDO + pdo_mysql extension

### Steps

1. **Place files** in your web server directory (e.g. `/var/www/html/forum` or `htdocs/forum`).

2. **Create the database and import the schema:**

   ```bash
   mysql -u root -p -e "CREATE DATABASE forum_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p forum_db < build/database.sql
   ```

   Or use phpMyAdmin → Import → select `build/database.sql`.

3. **Edit `config.php`:**

   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'forum_db');
   define('DB_USER', 'your_user');
   define('DB_PASS', 'your_password');
   define('FORUM_URL', 'http://localhost/forum');
   ```

4. **Create the uploads directory:**

   ```bash
   mkdir -p uploads/avatars
   chmod 755 uploads/avatars
   ```

5. Visit `http://localhost/forum/install.php` to verify setup, then visit `/index.php` or `/portal.php`.

---

## Demo accounts

All demo passwords are: `password`

| Username   | Role       | Access                                      |
|------------|------------|---------------------------------------------|
| admin      | Admin      | Full admin panel + all moderation powers    |
| moderator  | Moderator  | Pin, lock, delete posts/threads             |
| alice      | Member     | Standard member                             |
| bob        | Member     | Standard member                             |
| charlie    | Member     | Standard member                             |

---

## Portal & Block System

The portal landing page and the forum-index sidebars are both driven by rows
in the `k_blocks` table. To change what appears on either page you edit
database rows via **Admin → Portal → Blocks**, not the page files.

### Key tables

- **`k_blocks`** — one row per sidebar/centre card. Key columns:
  - `position` — `L` (left), `C` (centre), `R` (right)
  - `ndx` — display order within the column (1..N)
  - `active` — toggle without deleting
  - `html_file_name` — e.g. `block_clock.html` → resolves to `App\Blocks\Clock`
  - `view_all` — 1 if guests may see it, 0 for logged-in only
  - `view_groups` — CSV of group IDs, or empty for all
  - `view_pages` — CSV of `k_pages.id`s where this block should appear (e.g. `1,2` for index + portal)
- **`k_menus` / `k_links`** — rows consumed by the menu-style and image-link blocks
- **`k_vars`** — simple key/value store read via `k_var('key', 'default')`, used for per-block config (e.g. `k_news_items_to_display`, `k_last_online_hours`)
- **`k_pages`** — page-id registry. Important IDs: `1` index, `2` portal, `3` viewforum, `4` viewtopic, `7` UCP, `8` search

### How dispatch works

`BlockEngine::render('L'|'C'|'R', $pageId)` runs one query that filters
`k_blocks` by position, active, guest/group visibility, and `view_pages`. For
each matching row it:

1. Strips `block_` / `.html` from `html_file_name` → e.g. `recent_threads`.
2. Converts to StudlyCase → `RecentThreads`.
3. Instantiates `App\Blocks\RecentThreads` and calls `render($context)`.
4. Concatenates the returned HTML strings in order.

Missing classes are skipped silently — a stray row can't take the page down.

### Layout collapse

Both index and portal views check whether their sidebars ended up empty. If
so, they drop the corresponding `<aside>` and add a modifier class to
`.main-grid`:

| Class              | Grid template                  |
|--------------------|--------------------------------|
| `.main-grid`       | `200px auto 200px`             |
| `.main-grid.no-left`  | `auto 200px`                |
| `.main-grid.no-right` | `200px auto`                |
| `.main-grid.no-sides` | `1fr`                       |

The centre column reclaims the empty track.

### Adding a new block

1. Create `includes/blocks/YourBlock.php`:

   ```php
   namespace App\Blocks;

   class YourBlock
   {
       public function render(array $context): string
       {
           $rows = db()->query("SELECT ...")->fetchAll();
           return \View::make('blocks.your_block', [
               'blockTitle' => $context['block_title'] ?? 'Your Block',
               'rows'       => $rows,
           ]);
       }
   }
   ```

2. Create `templates/views/blocks/your_block.php` (the partial).

3. Visit **Admin → Portal → Blocks → Add new block**. The dropdown auto-discovers
   your new class and offers `block_your_block.html` as an `html_file_name`
   option.

No registration step and no code changes outside those two files.

### Available config keys (`k_vars`)

Set these via **Admin → Portal → Settings** or by inserting `k_vars` rows:

- `k_news_items_to_display` (default 5)
- `k_news_item_max_length` (default 180, chars)
- `k_news_forum_slug` (empty = site-wide; set to a forum slug to restrict)
- `k_recent_topics_to_display` (default 10)
- `k_recent_threads_to_display` (default 10)
- `k_top_topics_to_display` (default 5)
- `k_top_posters_to_display` (default 5)
- `k_last_online_hours` (default 24)
- `k_recent_days` (default 7, capped at 365) — window for recent-posts / recent-threads blocks

---

## Customisation

### Site name / tagline / logo

`SITE_NAME`, `SITE_DESC`, and `MAIN_LOGO_FILENAME` are loaded from the `configs`
table at bootstrap (see `includes/functions.php`). Edit them via **Admin →
Config** — no code change required. The logo file itself lives at
`assets/images/{main_logo_filename}` and is displayed in the hero strip at
max 290×80px.

### Posts / threads per page

```php
define('POSTS_PER_PAGE', 15);
define('THREADS_PER_PAGE', 20);
```

### Theme / colours

Main stylesheet lives at `assets/main.css`. CSS variables (`:root { ... }`) near
the top define the palette — `--accent`, `--accent2`, `--accent3`, `--bg`,
`--bg2`, `--text`, `--border`. A second theme ships as `assets/theme-winter.css`.

### Navigation menu

The **Site Navigator**, **Sub Menu**, and **Links Menu** blocks in the left
sidebar are sourced from the `k_menus` table, one row per link. Edit those
rows to change what's in each menu; the block system handles the rendering.

### Page sidebars

Which blocks appear on which page is controlled by the `view_pages` CSV on
each `k_blocks` row. To add a block to a new page, add the `k_pages.id` to
its `view_pages`. You can do this entirely through **Admin → Portal → Blocks**.

### Adding new categories / forums

Either use **Admin Panel → Categories / Forums**, or add rows to
`build/database.sql` before import.

---

## Production hardening

Before going live:

- [ ] Change all demo account passwords
- [ ] Set `'secure' => true` in session cookie params in `config.php` (requires HTTPS)
- [ ] Move `config.php` above the web root, or protect with `.htaccess`
- [ ] Delete `install.php`
- [ ] Delete any `_*_probe.php` or `_migrate_*.php` scratch files left in the root
- [ ] Enable HTTPS with a valid SSL certificate
- [ ] Set up regular MySQL backups
- [ ] Consider rate-limiting registration / login endpoints (e.g. via nginx)
- [ ] Review file permissions (`644` for PHP files, `755` for directories)
- [ ] Confirm `docs/` is blocked from web access (the shipped `.htaccess` denies it)

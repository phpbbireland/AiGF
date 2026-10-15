<?php
// includes/blocks/MenusNav.php
//
// Left-column block: the site's main navigation menu (k_menus.menu_type = 1).
// Maps to k_blocks.html_file_name = 'block_menus_nav.html'.
//
// Sub-heading rows (sub_heading = 1) render as section labels; regular rows
// render as links. External URLs open in a new tab.

namespace App\Blocks;

class MenusNav
{
    /** Override in a subclass to reuse the same partial for a different menu_type */
    protected int $menuType = 1;

    public function render(array $context): string
    {
        $stmt = db()->prepare("
            SELECT m_id, ndx, name, link_to, extern, sub_heading, menu_icon, append_sid
            FROM k_menus
            WHERE menu_type = ?
              AND active = 1
              AND view_all = 1
            ORDER BY ndx ASC
        ");
        $stmt->execute([$this->menuType]);
        $menus = $stmt->fetchAll();

        return \View::make('blocks.portal_menus', [
            'menus'      => $menus,
            'title'      => $context['block_title'] ?? '',
            'blockImage' => $context['block_image'] ?? '',
        ]);
    }
}

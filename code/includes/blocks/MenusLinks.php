<?php
// includes/blocks/MenusLinks.php
//
// Left-column block: "partner sites" / external links menu (k_menus.menu_type = 5).
// Maps to k_blocks.html_file_name = 'block_menus_links.html'.
//
// Same rendering as MenusNav — it just pulls a different menu_type.

namespace App\Blocks;

class MenusLinks extends MenusNav
{
    protected int $menuType = 5;
}

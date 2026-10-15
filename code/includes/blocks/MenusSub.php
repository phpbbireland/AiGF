<?php
// includes/blocks/MenusSub.php
//
// Left-column block: secondary/sub navigation menu (k_menus.menu_type = 2).
// Maps to k_blocks.html_file_name = 'block_menus_sub.html'.

namespace App\Blocks;

class MenusSub extends MenusNav
{
    protected int $menuType = 2;
}

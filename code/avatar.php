<?php
// Generates a colored avatar with initials - no external deps
$username = $_GET['u'] ?? 'User';
$initial = mb_strtoupper(mb_substr($username, 0, 1, 'UTF-8'), 'UTF-8');

// Pick color based on username
$colors = ['#6e8efb','#a78bfa','#34d399','#f472b6','#fbbf24','#f87171','#38bdf8','#fb923c'];
$color = $colors[abs(crc32($username)) % count($colors)];

header('Content-Type: image/svg+xml');
header('Cache-Control: public, max-age=86400');
echo '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 80 80">';
echo '<rect width="80" height="80" rx="40" fill="' . $color . '" opacity="0.25"/>';
echo '<rect width="80" height="80" rx="40" fill="none" stroke="' . $color . '" stroke-width="2" opacity="0.4"/>';
echo '<text x="40" y="52" text-anchor="middle" font-family="system-ui,sans-serif" font-size="32" font-weight="700" fill="' . $color . '">' . htmlspecialchars($initial) . '</text>';
echo '</svg>';

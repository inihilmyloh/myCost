<?php
// Script to generate PWA PNG icons if GD is installed
if (!extension_loaded('gd')) {
    echo "GD library not available, will use SVG icons.\n";
    exit;
}

function createIcon($size, $filename) {
    $img = imagecreatetruecolor($size, $size);
    imagealphablending($img, false);
    imagesavealpha($img, true);

    // Gradient background simulation
    for ($y = 0; $y < $size; $y++) {
        $r = (int)(79 + ($y / $size) * (6 - 79));
        $g = (int)(70 + ($y / $size) * (182 - 70));
        $b = (int)(229 + ($y / $size) * (212 - 229));
        $color = imagecolorallocate($img, $r, $g, $b);
        imageline($img, 0, $y, $size, $y, $color);
    }

    // Border and text
    $white = imagecolorallocate($img, 255, 255, 255);
    $text = "myCost";
    
    // Draw simple wallet rectangle
    $cardWidth = (int)($size * 0.65);
    $cardHeight = (int)($size * 0.45);
    $cardX = (int)(($size - $cardWidth) / 2);
    $cardY = (int)(($size - $cardHeight) / 2);
    
    imagefilledrectangle($img, $cardX, $cardY, $cardX + $cardWidth, $cardY + $cardHeight, $white);
    
    // Header bar on card
    $dark = imagecolorallocate($img, 30, 27, 75);
    imagefilledrectangle($img, $cardX, $cardY, $cardX + $cardWidth, $cardY + (int)($cardHeight * 0.25), $dark);

    // Accent line
    $green = imagecolorallocate($img, 16, 185, 129);
    imagesetthickness($img, (int)($size * 0.02));
    imageline($img, $cardX + (int)($cardWidth * 0.15), $cardY + (int)($cardHeight * 0.75), $cardX + (int)($cardWidth * 0.85), $cardY + (int)($cardHeight * 0.45), $green);

    $dest = __DIR__ . '/icons/' . $filename;
    imagepng($img, $dest);
    imagedestroy($img);
    echo "Generated icons/{$filename}\n";
}

createIcon(192, 'icon-192.png');
createIcon(512, 'icon-512.png');
createIcon(180, 'apple-touch-icon.png');

<?php
/**
 * Helper to process an uploaded signature/stamp image:
 * - Detects white / near-white background (paper color)
 * - Converts the background to 100% transparent PNG
 * - Keeps ink strokes (blue, black, red, stamp colors) crisp and preserved
 *
 * @param string $sourcePath Path to uploaded temporary image file
 * @param string $destinationPath Path where transparent PNG will be saved
 * @param int $threshold Tolerance for white background (default 215-220 out of 255)
 * @return bool True on success, false on failure
 */
function makeSignatureTransparent($sourcePath, $destinationPath, $threshold = 215) {
    if (!file_exists($sourcePath) || !function_exists('imagecreatefromstring')) {
        return false;
    }

    $imageInfo = @getimagesize($sourcePath);
    if (!$imageInfo) {
        return false;
    }

    $mime = $imageInfo['mime'];
    $srcImg = null;

    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $srcImg = @imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $srcImg = @imagecreatefrompng($sourcePath);
            break;
        case 'image/webp':
            if (function_exists('imagecreatefromwebp')) {
                $srcImg = @imagecreatefromwebp($sourcePath);
            }
            break;
        default:
            $data = @file_get_contents($sourcePath);
            if ($data) {
                $srcImg = @imagecreatefromstring($data);
            }
            break;
    }

    if (!$srcImg) {
        return false;
    }

    $width = imagesx($srcImg);
    $height = imagesy($srcImg);

    // Create a truecolor transparent image
    $dstImg = imagecreatetruecolor($width, $height);
    imagealphablending($dstImg, false);
    imagesavealpha($dstImg, true);

    $transparentColor = imagecolorallocatealpha($dstImg, 255, 255, 255, 127);
    imagefilledrectangle($dstImg, 0, 0, $width, $height, $transparentColor);

    // Pixel-by-pixel processing
    for ($x = 0; $x < $width; $x++) {
        for ($y = 0; $y < $height; $y++) {
            $rgba = imagecolorat($srcImg, $x, $y);
            $r = ($rgba >> 16) & 0xFF;
            $g = ($rgba >> 8) & 0xFF;
            $b = $rgba & 0xFF;
            $a = ($rgba >> 24) & 0x7F;

            // If already transparent in PNG
            if ($a >= 120) {
                imagesetpixel($dstImg, $x, $y, $transparentColor);
                continue;
            }

            // Calculate luminance / lightness
            $luminance = ($r * 0.299) + ($g * 0.587) + ($b * 0.114);

            if ($r >= $threshold && $g >= $threshold && $b >= $threshold && $luminance >= $threshold) {
                // Pure white or off-white paper background -> fully transparent
                imagesetpixel($dstImg, $x, $y, $transparentColor);
            } elseif ($luminance > ($threshold - 40)) {
                // Edge antialiasing / feathered pixels: smooth transition
                $alphaRatio = ($luminance - ($threshold - 40)) / 40.0;
                $newAlpha = (int)round(min(127, max(0, $alphaRatio * 127)));
                $featherColor = imagecolorallocatealpha($dstImg, $r, $g, $b, $newAlpha);
                imagesetpixel($dstImg, $x, $y, $featherColor);
            } else {
                // Ink / Signature stroke (dark ink or stamp color) -> full opacity
                $inkColor = imagecolorallocatealpha($dstImg, $r, $g, $b, $a);
                imagesetpixel($dstImg, $x, $y, $inkColor);
            }
        }
    }

    // Save as compressed PNG
    $saved = imagepng($dstImg, $destinationPath, 8);

    imagedestroy($srcImg);
    imagedestroy($dstImg);

    return $saved;
}


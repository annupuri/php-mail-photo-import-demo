<?php

// Seed the database with a sample photo so the gallery has something to show
// without going through the mail import.
//
//   php tools/seed.php [user]
//
// Inserts one row owned by <user> (default "demo") and writes a small BMP into
// _IMAGE_DIR. Safe to run repeatedly; each run adds one more photo.

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}

chdir(__DIR__ . '/..');
require 'dao.php';

$user = $argv[1] ?? 'demo';

/**
 * Build an uncompressed 24-bit BMP of a solid colour. No image extension needed.
 */
function solid_bmp($width, $height, $r, $g, $b)
{
    $rowSize = intdiv($width * 3 + 3, 4) * 4;
    $padding = str_repeat("\x00", $rowSize - $width * 3);

    $pixels = '';
    for ($y = 0; $y < $height; $y++) {
        $pixels .= str_repeat(chr($b) . chr($g) . chr($r), $width) . $padding;
    }

    $header = 'BM'
        . pack('V', 54 + strlen($pixels))  // file size
        . pack('v2', 0, 0)                 // reserved
        . pack('V', 54)                    // pixel data offset
        . pack('V', 40)                    // DIB header size
        . pack('V', $width)
        . pack('V', $height)
        . pack('v', 1)                     // colour planes
        . pack('v', 24)                    // bits per pixel
        . pack('V', 0)                     // no compression
        . pack('V', strlen($pixels))       // image size
        . pack('V2', 0, 0)                 // pixels per metre (x, y)
        . pack('V2', 0, 0);                // palette (unused)

    return $header . $pixels;
}

if (!is_dir(_IMAGE_DIR) && !mkdir(_IMAGE_DIR, 0777, true)) {
    exit('Cannot create ' . _IMAGE_DIR . "\n");
}

$fileName = 'seed-' . date('YmdHis') . '.bmp';
if (file_put_contents(_IMAGE_DIR . $fileName, solid_bmp(64, 64, 0xCC, 0x33, 0x33)) === false) {
    exit('Cannot write ' . _IMAGE_DIR . $fileName . "\n");
}

$dao = new ImagesDAO();
$dao->insert($user, $fileName);
$dao->update($dao->insert_id, $user, 'Seed photo', 'https://example.com/', 'Inserted by tools/seed.php');

echo "Inserted a sample photo for user '{$user}' ({$fileName}).\n";

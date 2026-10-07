<?php
/**
 * Generates the fake-camera wall clips for the harness:
 *   wall-lock.y4m — ceiling/floor lines at 0.16 / 0.84 of the frame (locks)
 *   wall-miss.y4m — lines at 0.06 / 0.94 (must NOT lock)
 * 640x480 (4:3), 2 s, 15 fps, yuv420p, via /usr/bin/ffmpeg.
 *
 *   php tools/tow-harness/make-walls.php
 */
$HERE = __DIR__;
$W = 640; $H = 480;

function tow_wall_png($path, $W, $H, $topFrac, $botFrac) {
    $im = imagecreatetruecolor($W, $H);
    mt_srand(4242);                                  // deterministic noise
    for ($y = 0; $y < $H; $y++) {
        for ($x = 0; $x < $W; $x++) {
            $n = mt_rand(-2, 2);                     // subtle grain: ±2 levels
            imagesetpixel($im, $x, $y, imagecolorallocate($im, 0xd9 + $n, 0xd4 + $n, 0xc8 + $n));
        }
    }
    $black = imagecolorallocate($im, 0, 0, 0);
    $top = (int) round($topFrac * $H);
    $bot = (int) round($botFrac * $H);
    // 6px lines centred on the target rows, full width
    imagefilledrectangle($im, 0, $top - 3, $W - 1, $top + 2, $black);
    imagefilledrectangle($im, 0, $bot - 3, $W - 1, $bot + 2, $black);
    imagepng($im, $path);
    imagedestroy($im);
    return array($top, $bot);
}

$specs = array(
    'wall-lock' => array(0.16, 0.84),
    'wall-miss' => array(0.06, 0.94),
);
foreach ($specs as $name => $fr) {
    $png = "$HERE/$name.png"; $y4m = "$HERE/$name.y4m";
    list($t, $b) = tow_wall_png($png, $W, $H, $fr[0], $fr[1]);
    $cmd = sprintf('/usr/bin/ffmpeg -y -loglevel error -loop 1 -i %s -t 2 -r 15 -pix_fmt yuv420p -f yuv4mpegpipe %s 2>&1',
        escapeshellarg($png), escapeshellarg($y4m));
    exec($cmd, $out, $rc);
    if ($rc !== 0) { fwrite(STDERR, "ffmpeg failed for $name:\n" . implode("\n", $out) . "\n"); exit(1); }
    fwrite(STDERR, sprintf("%s: lines at rows %d and %d -> %s (%d bytes)\n", $name, $t, $b, $y4m, filesize($y4m)));
}
echo "ok\n";

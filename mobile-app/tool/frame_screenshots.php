<?php

/*
 * Turns the raw 1080×2340 app captures (store/screenshots/raw/) into Play Store phone
 * screenshots: 1080×1920 (9:16 — Play rejects anything taller than 2:1), GlobalSpace teal
 * gradient, a caption, and the screen inside a rounded phone outline.
 *
 *   php tool/frame_screenshots.php        (run from mobile-app/)
 */

$root = dirname(__DIR__);
$raw = "$root/store/screenshots/raw";
$out = "$root/store/screenshots";
$bold = 'C:/Windows/Fonts/segoeuib.ttf';
$regular = 'C:/Windows/Fonts/segoeui.ttf';

$captions = [
    '01-home' => ['Your HR day at a glance', 'Attendance, leave balance & approvals'],
    '02-attendance' => ['Attendance, day by day', 'In/out times, hours & monthly totals'],
    '03-requests' => ['Leave, WFH & regularization', 'Track status and reapply in one tap'],
    '04-apply-leave' => ['Apply leave in seconds', 'Live balance & working-day count'],
    '05-approvals' => ['Approve on the go', 'Leave, WFH, expenses & more'],
    '06-team' => ['Know where your team is', 'Who is in, on WFH or on leave today'],
    '07-payslip' => ['Salary slips in your pocket', 'Earnings, deductions & PDF download'],
    '08-sign-in' => ['Sign in once', 'Secure, and stays signed in on your phone'],
];

const W = 1080;
const H = 1920;

foreach ($captions as $name => [$title, $subtitle]) {
    $src = @imagecreatefrompng("$raw/$name.png");
    if (! $src) {
        fwrite(STDERR, "missing $raw/$name.png — run the screenshot test first\n");

        continue;
    }

    $img = imagecreatetruecolor(W, H);
    imagealphablending($img, true);

    // Background: dark teal → logo teal → blue, top to bottom.
    $stops = [[6, 58, 57], [12, 132, 129], [26, 154, 174]];
    for ($y = 0; $y < H; $y++) {
        $t = $y / (H - 1) * 2;
        $i = min((int) floor($t), 1);
        $f = $t - $i;
        $c = array_map(fn ($a, $b) => (int) round($a + ($b - $a) * $f), $stops[$i], $stops[$i + 1]);
        imageline($img, 0, $y, W, $y, imagecolorallocate($img, ...$c));
    }

    // Logo-gradient strip along the top.
    $mark = [[246, 50, 42], [241, 137, 44], [238, 174, 45], [167, 210, 83], [42, 164, 241]];
    for ($x = 0; $x < W; $x++) {
        $p = $x / (W - 1) * (count($mark) - 1);
        $i = min((int) floor($p), count($mark) - 2);
        $f = $p - $i;
        $c = array_map(fn ($a, $b) => (int) round($a + ($b - $a) * $f), $mark[$i], $mark[$i + 1]);
        imageline($img, $x, 0, $x, 12, imagecolorallocate($img, ...$c));
    }

    // Caption.
    $white = imagecolorallocate($img, 255, 255, 255);
    $soft = imagecolorallocatealpha($img, 255, 255, 255, 35);
    centerText($img, 64, $bold, $title, 175, $white);
    centerText($img, 34, $regular, $subtitle, 250, $soft);

    // Phone: screenshot scaled to fit, inside a dark bezel with rounded corners.
    $screenH = 1500;
    $screenW = (int) round(imagesx($src) * $screenH / imagesy($src)); // ≈ 720
    $bezel = 22;
    $phoneW = $screenW + $bezel * 2;
    $phoneH = $screenH + $bezel * 2;
    $phoneX = (int) ((W - $phoneW) / 2);
    $phoneY = 320;

    roundedRect($img, $phoneX + 10, $phoneY + 18, $phoneW, $phoneH, 70, imagecolorallocatealpha($img, 0, 0, 0, 90)); // shadow
    roundedRect($img, $phoneX, $phoneY, $phoneW, $phoneH, 70, imagecolorallocate($img, 22, 28, 30));               // bezel

    $screen = imagecreatetruecolor($screenW, $screenH);
    imagecopyresampled($screen, $src, 0, 0, 0, 0, $screenW, $screenH, imagesx($src), imagesy($src));
    copyRounded($img, $screen, $phoneX + $bezel, $phoneY + $bezel, 50);

    imagepng($img, "$out/$name.png", 6);
    echo "$out/$name.png\n";
}

function centerText($img, int $size, string $font, string $text, int $baseline, int $color): void
{
    $box = imagettfbbox($size, 0, $font, $text);
    $x = (int) ((W - ($box[2] - $box[0])) / 2);
    imagettftext($img, $size, 0, $x, $baseline, $color, $font, $text);
}

function roundedRect($img, int $x, int $y, int $w, int $h, int $r, int $color): void
{
    imagefilledrectangle($img, $x + $r, $y, $x + $w - $r, $y + $h, $color);
    imagefilledrectangle($img, $x, $y + $r, $x + $w, $y + $h - $r, $color);
    foreach ([[$x + $r, $y + $r], [$x + $w - $r, $y + $r], [$x + $r, $y + $h - $r], [$x + $w - $r, $y + $h - $r]] as [$cx, $cy]) {
        imagefilledellipse($img, $cx, $cy, $r * 2, $r * 2, $color);
    }
}

/** Copy $src onto $dst at ($dx,$dy), clipping the corners to radius $r. */
function copyRounded($dst, $src, int $dx, int $dy, int $r): void
{
    $w = imagesx($src);
    $h = imagesy($src);
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $cx = $x < $r ? $r : ($x >= $w - $r ? $w - $r - 1 : null);
            $cy = $y < $r ? $r : ($y >= $h - $r ? $h - $r - 1 : null);
            if ($cx !== null && $cy !== null && (($x - $cx) ** 2 + ($y - $cy) ** 2) > $r * $r) {
                continue;
            }
            imagesetpixel($dst, $dx + $x, $dy + $y, imagecolorat($src, $x, $y));
        }
    }
}

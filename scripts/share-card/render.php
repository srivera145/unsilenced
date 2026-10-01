<?php

declare(strict_types=1);

/**
 * Renders public_html/share-card.png, the 1200x630 link-preview image (Open
 * Graph and Twitter cards): the logo from views/partials/logo.php and
 * "Enough." in the self-hosted Anton, on navy.
 *
 *   php scripts/share-card/render.php
 *   CHROME="/path/to/chrome" php scripts/share-card/render.php
 *
 * Needs Google Chrome or Chromium (headless screenshot). ImageMagick's
 * `magick`, if installed, strips metadata and recompresses the PNG.
 * Re-run it if the logo, the brand colours or the font change.
 */

$root = dirname(__DIR__, 2);
$output = $root . '/public_html/share-card.png';
$width = 1200;
$height = 630;

// Brand colours (public_html/css/keel.css): navy background, the teal mark,
// a white wordmark, and "Enough." in the teal used for text on dark
// backgrounds (4.4:1 on navy; it is display-size text).
$navy = '#0B4F7C';
$teal = '#14B8B0';
$tealOnDark = '#3ACCC4';

$font = base64_encode((string) file_get_contents($root . '/public_html/fonts/anton/anton-latin.woff2'));

ob_start();
$logoVariant = 'horizontal';
$logoLabel = 'Unsilenced';
require $root . '/views/partials/logo.php';
$logo = (string) ob_get_clean();

$html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  @font-face { font-family: "Anton"; src: url(data:font/woff2;base64,{$font}) format("woff2"); }
  html, body { margin: 0; inline-size: {$width}px; block-size: {$height}px; overflow: hidden; background: {$navy}; }
  body { box-sizing: border-box; display: flex; flex-direction: column; justify-content: center; align-items: flex-start; gap: 36px; padding: 0 104px; }
  .logo { display: block; block-size: 104px; inline-size: auto; color: #FFFFFF; overflow: visible; }
  .logo-mark { fill: {$teal}; stroke: {$teal}; }
  .logo-wordmark { font-family: "Anton"; }
  .enough { margin: 0; font-family: "Anton"; font-size: 236px; font-weight: 400; line-height: .9; letter-spacing: 0; color: {$tealOnDark}; }
</style>
</head>
<body>
{$logo}
<p class="enough">Enough.</p>
</body>
</html>
HTML;

$chrome = getenv('CHROME') ?: null;
foreach ([
    'C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    '/usr/bin/google-chrome',
    '/usr/bin/chromium',
    '/usr/bin/chromium-browser',
] as $candidate) {
    if ($chrome === null && is_file($candidate)) {
        $chrome = $candidate;
    }
}

if ($chrome === null) {
    fwrite(STDERR, "Chrome or Chromium not found. Set CHROME=/path/to/chrome.\n");
    exit(1);
}

$work = sys_get_temp_dir() . '/unsilenced-share-card-' . bin2hex(random_bytes(4));
mkdir($work);
file_put_contents($work . '/card.html', $html);
$shot = $work . '/card.png';

$command = sprintf(
    '%s --headless=new --disable-gpu --hide-scrollbars --force-device-scale-factor=1 --window-size=%d,%d --virtual-time-budget=5000 --user-data-dir=%s --screenshot=%s %s 2>&1',
    escapeshellarg($chrome),
    $width,
    $height,
    escapeshellarg($work . '/profile'),
    escapeshellarg($shot),
    escapeshellarg('file:///' . ltrim(str_replace('\\', '/', $work . '/card.html'), '/'))
);
exec($command, $log, $status);

$size = is_file($shot) ? getimagesize($shot) : false;
if ($status !== 0 || $size === false || $size[0] !== $width || $size[1] !== $height) {
    fwrite(STDERR, "Chrome did not produce a {$width}x{$height} screenshot.\n" . implode("\n", $log) . "\n");
    exit(1);
}

copy($shot, $output);

$magick = trim((string) shell_exec(PHP_OS_FAMILY === 'Windows' ? 'where magick 2>NUL' : 'command -v magick 2>/dev/null'));
if ($magick !== '') {
    exec(sprintf('magick %s -strip -define png:compression-level=9 %s', escapeshellarg($output), escapeshellarg($output)));
}

fwrite(STDOUT, sprintf("Wrote %s (%dx%d, %s KB).\n", $output, $width, $height, number_format(filesize($output) / 1024, 1)));

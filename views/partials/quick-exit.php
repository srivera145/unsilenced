<?php
/**
 * Quick exit. Required as the first thing in <body> on every page, so it is the
 * first tab stop after the skip link.
 *
 * Click it, or press Esc twice within a second, and the page blanks at once and
 * is replaced by weather.com with location.replace(), which swaps this page's
 * history entry for the new one: Back does not return here. It cannot remove
 * earlier pages of this site from history if the visitor opened several; the
 * Get help page says so and suggests a private window.
 *
 * Without JavaScript it is still an ordinary link to the same place.
 *
 * The behaviour is public_html/js/quick-exit.js, loaded without defer straight
 * after the link (the Content-Security-Policy allows no inline script). It
 * reads the destination from the link's href.
 */
$quickExitUrl = (string) \Keel\App\Support\Config::get('quick_exit_url', 'https://weather.com/');
?>
<a class="quick-exit" id="quick-exit" href="<?= htmlspecialchars($quickExitUrl, ENT_QUOTES, 'UTF-8') ?>" rel="noreferrer">
    <span>Quick exit</span>
    <span class="quick-exit-hint" aria-hidden="true">or press Esc twice</span>
</a>
<script src="<?= htmlspecialchars(\Keel\App\Support\Asset::url('/js/quick-exit.js'), ENT_QUOTES, 'UTF-8') ?>"></script>

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
 */
$quickExitUrl = (string) \Keel\App\Support\Config::get('quick_exit_url', 'https://weather.com/');
?>
<a class="quick-exit" id="quick-exit" href="<?= htmlspecialchars($quickExitUrl, ENT_QUOTES, 'UTF-8') ?>" rel="noreferrer">
    <span>Quick exit</span>
    <span class="quick-exit-hint" aria-hidden="true">or press Esc twice</span>
</a>
<script>
(function () {
    var exitUrl = <?= json_encode($quickExitUrl, JSON_UNESCAPED_SLASHES) ?>;
    var lastEscape = 0;

    function leave(event) {
        if (event) {
            event.preventDefault();
        }
        try {
            document.documentElement.style.visibility = 'hidden';
            document.title = '';
        } catch (error) {}
        window.location.replace(exitUrl);
    }

    document.getElementById('quick-exit').addEventListener('click', leave);

    // Capture phase, so nothing on the page can swallow the key first.
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape' && event.key !== 'Esc') {
            return;
        }
        var now = Date.now();
        if (now - lastEscape < 1000) {
            leave(event);
        }
        lastEscape = now;
    }, true);
})();
</script>

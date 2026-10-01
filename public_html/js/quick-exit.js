/* Quick exit (views/partials/quick-exit.php). Loaded without defer straight
 * after the link, so it is wired before the rest of the page arrives.
 *
 * Click the link, or press Esc twice within a second, and the page blanks at
 * once and is replaced by the link's own destination with location.replace(),
 * which swaps this page's history entry for the new one: Back does not return
 * here. Without JavaScript the link still goes to the same place.
 *
 * A static file rather than an inline script: the Content-Security-Policy
 * allows scripts from this origin only (script-src 'self').
 */
(function () {
    var link = document.getElementById('quick-exit');
    if (!link) {
        return;
    }

    var exitUrl = link.href;
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

    link.addEventListener('click', leave);

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

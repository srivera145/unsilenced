/* One Back-history entry per visit (config single_history_entry; loaded by
 * views/partials/public-header.php only when it is on). Links and searches
 * between this site's pages replace the current entry instead of adding one,
 * so quick exit's location.replace() leaves nothing of this site to go Back
 * to. Off-site links, new-tab clicks and in-page anchors behave normally.
 * Without JavaScript, navigation is ordinary.
 */
(function () {
    function sameSite(url) {
        return url.origin === window.location.origin;
    }

    document.addEventListener('click', function (event) {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }
        var link = event.target.closest ? event.target.closest('a[href]') : null;
        if (!link || link.id === 'quick-exit' || (link.target && link.target !== '_self') || link.hasAttribute('download')) {
            return;
        }
        var url = new URL(link.href, window.location.href);
        if (!sameSite(url) || /^(tel|mailto):/.test(link.getAttribute('href'))) {
            return;
        }
        if (url.hash && url.pathname === window.location.pathname && url.search === window.location.search) {
            return;
        }
        event.preventDefault();
        window.location.replace(url.href);
    });

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (event.defaultPrevented || (form.getAttribute('method') || 'get').toLowerCase() !== 'get') {
            return;
        }
        var url = new URL(form.getAttribute('action') || window.location.href, window.location.href);
        if (!sameSite(url)) {
            return;
        }
        event.preventDefault();
        url.search = new URLSearchParams(new FormData(form)).toString();
        window.location.replace(url.href);
    });
})();

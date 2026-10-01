/* /share (views/survivor/share-open.php).
 *
 * A share link is /share#token. The part after # never reaches any server,
 * so it is in no log. This reads it, removes it from the address bar and from
 * this page's history entry, and posts it to /share/open.
 */
(function () {
    var form = document.getElementById('share-open-form');
    var input = document.getElementById('token');
    var token = window.location.hash.replace(/^#/, '');

    if (!form || !input || !/^[A-Za-z0-9_-]{40,60}$/.test(token)) {
        return;
    }

    try {
        window.history.replaceState(null, '', window.location.pathname);
    } catch (error) {}

    input.value = token;
    document.getElementById('share-paste').hidden = true;
    document.getElementById('share-opening').hidden = false;
    HTMLFormElement.prototype.submit.call(form);
})();

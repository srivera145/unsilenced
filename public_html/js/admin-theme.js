/* Admin and sign-in pages only (views/partials/head.php): apply a signed-in
 * admin's saved theme before first paint. Loaded without defer in <head>,
 * after the keel-theme meta tag it reads. Public pages follow the OS setting
 * and write nothing to storage.
 */
(function () {
    var meta = document.querySelector('meta[name="keel-theme"]');
    var serverTheme = meta ? meta.getAttribute('content') : '';

    try {
        if (serverTheme) {
            localStorage.setItem('deck-theme', serverTheme);
        }
        var theme = serverTheme || localStorage.getItem('deck-theme');
        if (theme === 'light' || theme === 'dark') {
            document.documentElement.setAttribute('data-theme', theme);
        }
    } catch (error) {}
})();

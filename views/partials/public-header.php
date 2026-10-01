<?php
/**
 * Top of every public page: skip link, quick exit, the hotline strip and the
 * site navigation. Set $navCurrent to 'schools', 'help', 'options', 'states'
 * or 'methodology' to mark the current section.
 */
use Keel\App\Support\Config;

$help = (array) Config::get('help', []);
$navItems = [
    'schools' => ['/schools', 'Schools'],
    'help' => ['/resources/get-help', 'Get help'],
    'options' => ['/resources/your-options', 'Your options'],
    'states' => ['/states', 'States'],
    'methodology' => ['/methodology', 'Our data'],
];
?>
<a class="skip-link" href="#main-content">Skip to content</a>
<?php require __DIR__ . '/quick-exit.php'; ?>
<?php if (Config::get('single_history_entry', true)): ?>
<script>
/* One Back-history entry per visit (config single_history_entry). Links and
   searches between this site's pages replace the current entry instead of
   adding one, so quick exit's location.replace() leaves nothing of this site
   to go Back to. Off-site links, new-tab clicks and in-page anchors behave
   normally. Without JavaScript, navigation is ordinary. */
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
</script>
<?php endif; ?>

<aside class="help-bar" aria-label="Get help now">
    <p>
        <strong>Need help now?</strong>
        Call <a href="tel:<?= htmlspecialchars((string) $help['hotline_tel'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $help['hotline_display']) ?></a>
        or <a href="<?= htmlspecialchars((string) $help['chat_url'], ENT_QUOTES, 'UTF-8') ?>" rel="noopener noreferrer">chat online</a>,
        free and confidential, 24/7. In danger: <a href="tel:<?= htmlspecialchars((string) $help['emergency_number'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $help['emergency_number']) ?></a>.
    </p>
</aside>

<header class="container site-header">
    <div class="cluster cluster-between">
        <a class="site-logo" href="/" aria-label="<?= htmlspecialchars((string) Config::get('site.name', 'Unsilenced'), ENT_QUOTES, 'UTF-8') ?> home">
            <?php $logoClass = 'logo-wide'; $logoLabel = null; require __DIR__ . '/logo.php'; ?>
            <?php $logoVariant = 'icon'; $logoClass = 'logo-compact'; $logoLabel = null; require __DIR__ . '/logo.php'; ?>
        </a>
        <nav class="site-nav" aria-label="Main">
            <ul class="cluster">
                <?php foreach ($navItems as $key => [$href, $label]): ?>
                <li><a href="<?= $href ?>"<?= ($navCurrent ?? '') === $key ? ' aria-current="page"' : '' ?>><?= htmlspecialchars($label) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </div>
</header>

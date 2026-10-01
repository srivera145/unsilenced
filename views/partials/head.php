<meta charset="UTF-8">
<?php
/**
 * Every view's <head>. Views set, before requiring it:
 *   $title            Neutral page name, e.g. "Schools". Never a graphic word:
 *                     the title shows in tabs, history and screen-sharing.
 *                     Omit it on the home page to get just "Unsilenced".
 *   $metaDescription  For search results. Can be descriptive.
 *   $noindex          true on admin and sign-in pages.
 *
 * On a public page there is no session (see SessionRoutes), so nothing here may
 * start one: no CSRF meta, no theme sync, no keel.js.
 */
use Keel\App\Support\Config;

$sessionActive = \Keel\Core\Session::isActive();
$siteName = (string) Config::get('site.name', 'Unsilenced');

$appUrl = rtrim((string) \Keel\Core\Env::get('APP_URL', ''), '/');
$requestPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
$canonicalUrl = $appUrl . ($requestPath === '' ? '/' : $requestPath);

$pageTitle = trim((string) ($title ?? ''));
$resolvedTitle = ($pageTitle === '' || $pageTitle === $siteName) ? $siteName : $pageTitle . ' · ' . $siteName;
$resolvedDescription = trim((string) ($metaDescription ?? Config::get('site.description', '')));

\EchoDial\Deck\Deck::configure([
	'base' => '/deck',
	'root' => dirname(__DIR__, 2) . '/public_html',
	'extras' => false,
]);
$publicAsset = static fn (string $path): string => $path . '?v=' . (int) @filemtime(dirname(__DIR__, 2) . '/public_html' . $path);
?>
<?php if ($sessionActive): ?>
<script>
	/* Admin only: apply a signed-in admin's saved theme before first paint. Public
	   pages follow the OS setting and write nothing to storage. */
	(function () {
		var serverTheme = <?= json_encode(\Keel\Core\Theme::serverPreference(), JSON_UNESCAPED_SLASHES) ?>;
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
</script>
<meta name="csrf-token" content="<?= htmlspecialchars(\Keel\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
<meta name="keel-authenticated" content="<?= \Keel\Core\Theme::isAuthenticated() ? '1' : '0' ?>">
<?php endif; ?>
<meta name="description" content="<?= htmlspecialchars($resolvedDescription, ENT_QUOTES, 'UTF-8') ?>">
<?php if (!empty($noindex)): ?>
<meta name="robots" content="noindex, nofollow">
<?php else: ?>
<link rel="canonical" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:title" content="<?= htmlspecialchars($resolvedTitle, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:description" content="<?= htmlspecialchars($resolvedDescription, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:type" content="website">
<meta property="og:url" content="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:site_name" content="<?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?>">
<meta name="twitter:card" content="summary">
<?php endif; ?>
<meta name="color-scheme" content="light dark">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" href="/favicon.ico" sizes="32x32">
<title><?= htmlspecialchars($resolvedTitle, ENT_QUOTES, 'UTF-8') ?></title>
<?= \EchoDial\Deck\Deck::head() ?>
<link rel="stylesheet" href="<?= htmlspecialchars($publicAsset('/css/keel.css'), ENT_QUOTES, 'UTF-8') ?>">
<?php if ($sessionActive): ?>
<script src="<?= htmlspecialchars($publicAsset('/js/keel.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
<?php endif; ?>

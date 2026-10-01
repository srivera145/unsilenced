<meta charset="UTF-8">
<?php
/**
 * Every view's <head>. Views set, before requiring it:
 *   $title            Neutral page name, e.g. "Schools". Never a graphic word:
 *                     the title shows in tabs, history and screen-sharing.
 *                     Omit it on the home page to get just "Unsilenced".
 *   $metaDescription  For search results. Can be descriptive.
 *   $noindex          true on admin, sign-in and error pages, and on school
 *                     pages without Clery data.
 *   $shareTitle       og:title for link previews, when it should differ from
 *                     the page title (school pages: the school's name).
 *   $share            false to leave out the Open Graph and Twitter tags
 *                     (error pages). Admin and sign-in pages never have them.
 *
 * On a public page there is no session (see SessionRoutes), so nothing here may
 * start one: no CSRF meta, no theme sync, no keel.js.
 *
 * No inline <script> or style="" anywhere: the Content-Security-Policy allows
 * scripts and styles from this origin only (Keel\Core\SecurityHeaders). The
 * JSON-LD blocks some views add are data, not script, and CSP does not apply
 * to them.
 */
use EchoDial\Deck\Deck;
use Keel\App\Support\Asset;
use Keel\App\Support\Config;

$sessionActive = \Keel\Core\Session::isActive();
$siteName = (string) Config::get('site.name', 'Unsilenced');
$headEscape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$appUrl = rtrim((string) \Keel\Core\Env::get('APP_URL', ''), '/');
$requestPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
$canonicalUrl = $appUrl . ($requestPath === '' ? '/' : $requestPath);

$pageTitle = trim((string) ($title ?? ''));
$resolvedTitle = ($pageTitle === '' || $pageTitle === $siteName) ? $siteName : $pageTitle . ' · ' . $siteName;
$resolvedDescription = trim((string) ($metaDescription ?? Config::get('site.description', '')));

$shareCard = (array) Config::get('share_card', []);
$shareImageUrl = $appUrl . (string) ($shareCard['path'] ?? '/share-card.png');
$resolvedShareTitle = trim((string) ($shareTitle ?? '')) !== '' ? trim((string) $shareTitle) . ' · ' . $siteName : $resolvedTitle;

Deck::configure([
	'base' => '/deck',
	'root' => dirname(__DIR__, 2) . '/public_html',
	'extras' => false,
]);
?>
<?php if ($sessionActive): ?>
<meta name="csrf-token" content="<?= $headEscape(\Keel\Core\Csrf::token()) ?>">
<meta name="keel-authenticated" content="<?= \Keel\Core\Theme::isAuthenticated() ? '1' : '0' ?>">
<meta name="keel-theme" content="<?= $headEscape((string) \Keel\Core\Theme::serverPreference()) ?>">
<?php /* Applies a signed-in admin's saved theme before first paint, so no defer. */ ?>
<script src="<?= $headEscape(Asset::url('/js/admin-theme.js')) ?>"></script>
<?php endif; ?>
<meta name="description" content="<?= $headEscape($resolvedDescription) ?>">
<?php if (!empty($noindex)): ?>
<meta name="robots" content="noindex, nofollow">
<?php else: ?>
<link rel="canonical" href="<?= $headEscape($canonicalUrl) ?>">
<?php endif; ?>
<?php if (!\Keel\App\Support\SessionRoutes::requiresSession($requestPath) && ($share ?? true) !== false): ?>
<?php /* Link previews. The image is this site's own file at an absolute URL built from APP_URL. */ ?>
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= $headEscape($siteName) ?>">
<meta property="og:title" content="<?= $headEscape($resolvedShareTitle) ?>">
<meta property="og:description" content="<?= $headEscape($resolvedDescription) ?>">
<meta property="og:url" content="<?= $headEscape($canonicalUrl) ?>">
<meta property="og:image" content="<?= $headEscape($shareImageUrl) ?>">
<meta property="og:image:type" content="image/png">
<meta property="og:image:width" content="<?= (int) ($shareCard['width'] ?? 1200) ?>">
<meta property="og:image:height" content="<?= (int) ($shareCard['height'] ?? 630) ?>">
<meta property="og:image:alt" content="<?= $headEscape((string) ($shareCard['alt'] ?? $siteName)) ?>">
<meta property="og:locale" content="en_US">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= $headEscape($resolvedShareTitle) ?>">
<meta name="twitter:description" content="<?= $headEscape($resolvedDescription) ?>">
<meta name="twitter:image" content="<?= $headEscape($shareImageUrl) ?>">
<meta name="twitter:image:alt" content="<?= $headEscape((string) ($shareCard['alt'] ?? $siteName)) ?>">
<?php endif; ?>
<meta name="color-scheme" content="light dark">
<link rel="icon" href="<?= $headEscape(Asset::url('/favicon.svg')) ?>" type="image/svg+xml">
<link rel="icon" href="<?= $headEscape(Asset::url('/favicon-32x32.png')) ?>" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="<?= $headEscape(Asset::url('/apple-touch-icon.png')) ?>" sizes="180x180">
<title><?= $headEscape($resolvedTitle) ?></title>
<?php /* What Deck::head() prints, minus its inline script: deck.js reads the sprite URL from data-deck-icons instead. */ ?>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?= Deck::css() ?>
<script src="<?= $headEscape(Deck::asset('deck.js')) ?>" data-deck-icons="<?= $headEscape(Deck::asset('deck-icons.svg')) ?>" defer></script>
<?php /* The wordmark font is in the header of every page; fetching it with the HTML keeps the swap short. crossorigin is required for font preloads, even same-origin. */ ?>
<link rel="preload" href="/fonts/anton/anton-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= $headEscape(Asset::url('/css/keel.css')) ?>">
<?php if ($sessionActive): ?>
<script src="<?= $headEscape(Asset::url('/js/keel.js')) ?>" defer></script>
<?php endif; ?>
<?php unset($headEscape); ?>

<?php
/**
 * Top of every public page: skip link, quick exit, the hotline strip and the
 * site navigation. Set $navCurrent to 'schools', 'help', 'options', 'states'
 * or 'methodology' to mark the current section.
 *
 * The survivor pages set $hideNav (the logo stays; fewer ways to leave a form
 * by accident) and $quickExitSignOut (see quick-exit.php).
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
<?php /* One Back-history entry per visit: public_html/js/single-history.js. */ ?>
<script src="<?= htmlspecialchars(\Keel\App\Support\Asset::url('/js/single-history.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
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
        <?php if (empty($hideNav)): ?>
        <nav class="site-nav" aria-label="Main">
            <ul class="cluster">
                <?php foreach ($navItems as $key => [$href, $label]): ?>
                <li><a href="<?= $href ?>"<?= ($navCurrent ?? '') === $key ? ' aria-current="page"' : '' ?>><?= htmlspecialchars($label) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</header>

<?php
/**
 * Opening of every admin page: document head, quick exit, admin navigation and
 * the status message. Close with admin/_bottom.php. Set $title and
 * $adminSection ('dashboard', 'schools', 'accountability', 'resources',
 * 'states', 'imports') before requiring it.
 */
use EchoDial\Deck\Deck;
use Keel\Core\Csrf;
use Keel\Core\Theme;

$noindex = true;
$adminNav = [
    'dashboard' => ['/admin', 'Overview'],
    'schools' => ['/admin/schools', 'Schools'],
    'accountability' => ['/admin/accountability', 'Accountability'],
    'resources' => ['/admin/resources', 'Resource pages'],
    'states' => ['/admin/states', 'State pages'],
    'imports' => ['/admin/imports', 'Imports'],
];
$statusMessages = [
    'created' => 'Created.',
    'saved' => 'Saved.',
    'deleted' => 'Deleted.',
];
$statusMessage = $statusMessages[(string) ($_GET['status'] ?? '')] ?? null;
?>
<!DOCTYPE html>
<html <?= Deck::htmlAttributes(lang: 'en') ?> <?= Deck::theme(mode: Theme::serverPreference()) ?>>
<head>
<?php require __DIR__ . '/../partials/head.php'; ?>
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to content</a>
    <?php require __DIR__ . '/../partials/quick-exit.php'; ?>

    <header class="container site-header admin-header">
        <div class="cluster cluster-between">
            <a class="site-logo" href="/admin" aria-label="Unsilenced admin">
                <?php $logoClass = 'logo-wide'; $logoLabel = null; require __DIR__ . '/../partials/logo.php'; ?>
                <?php $logoVariant = 'icon'; $logoClass = 'logo-compact'; $logoLabel = null; require __DIR__ . '/../partials/logo.php'; ?>
                <span class="badge badge-brand">Admin</span>
            </a>
            <div class="cluster cluster-tight">
                <a class="btn btn-ghost btn-sm" href="/">View site</a>
                <?php $themeToggleClass = ''; require __DIR__ . '/../partials/theme-toggle.php'; ?>
                <form method="POST" action="/logout">
                    <?= Csrf::field() ?>
                    <button class="btn btn-sm" type="submit">Sign out</button>
                </form>
            </div>
        </div>
        <nav class="site-nav" aria-label="Admin">
            <ul class="cluster">
                <?php foreach ($adminNav as $key => [$href, $label]): ?>
                <li><a href="<?= $href ?>"<?= ($adminSection ?? '') === $key ? ' aria-current="page"' : '' ?>><?= htmlspecialchars($label) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </header>

    <main id="main-content" tabindex="-1" class="container settings-page admin-main stack stack-6">
        <?php if ($statusMessage !== null): ?>
        <div class="alert alert-good" role="status">
            <?= Deck::icon('check-circle') ?>
            <p><?= htmlspecialchars($statusMessage) ?></p>
        </div>
        <?php endif; ?>

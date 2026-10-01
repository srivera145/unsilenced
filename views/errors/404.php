<?php
use EchoDial\Deck\Deck;

$title = 'Page not found';
$noindex = true;
?>
<!DOCTYPE html>
<html <?= Deck::htmlAttributes(lang: 'en') ?>>
<head>
<?php require __DIR__ . '/../partials/head.php'; ?>
</head>
<body>
    <?php require __DIR__ . '/../partials/public-header.php'; ?>

    <main id="main-content" tabindex="-1" class="container public-page stack stack-6">
        <section class="card">
            <div class="empty">
                <span class="empty-art"><?= Deck::icon('help', 'icon icon-lg') ?></span>
                <h1 class="empty-title">Page not found</h1>
                <p>The page you asked for does not exist or has moved.</p>

                <?php if (!empty($exception)): ?>
                <div class="alert alert-warn text-start w-full">
                    <?= Deck::icon('alert-triangle') ?>
                    <div>
                        <p class="alert-title"><?= htmlspecialchars($exception->getMessage()) ?></p>
                        <p class="alert-body mono"><?= htmlspecialchars($exception->getFile()) ?>:<?= htmlspecialchars((string) $exception->getLine()) ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <div class="cluster cluster-center">
                    <a href="/" class="btn btn-primary">Home</a>
                    <a href="/schools" class="btn">Find a school</a>
                </div>
            </div>
        </section>

        <?php require __DIR__ . '/../partials/help-panel.php'; ?>
    </main>

    <?php require __DIR__ . '/../partials/public-footer.php'; ?>
</body>
</html>

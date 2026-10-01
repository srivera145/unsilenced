<?php
use EchoDial\Deck\Deck;
use Keel\App\Support\Format;

$navCurrent = 'help';
?>
<!DOCTYPE html>
<html <?= Deck::htmlAttributes(lang: 'en') ?>>
<head>
<?php require __DIR__ . '/../partials/head.php'; ?>
</head>
<body>
    <?php require __DIR__ . '/../partials/public-header.php'; ?>

    <main id="main-content" tabindex="-1" class="container public-page stack stack-6">
        <div class="stack stack-2">
            <h1 class="h2">Resources</h1>
            <p class="lede">Help is free and confidential, and you do not have to decide anything right now.</p>
        </div>

        <div class="split" style="--rail: 22rem">
            <ul class="stack stack-4">
                <?php foreach ($pages as $page): ?>
                <li class="card">
                    <div class="card-body stack stack-2">
                        <h2 class="h5"><a href="/resources/<?= Format::e($page['slug']) ?>"><?= Format::e($page['title']) ?></a></h2>
                        <p class="text-sm text-muted"><?= Format::e($page['summary']) ?></p>
                    </div>
                </li>
                <?php endforeach; ?>
                <li class="card">
                    <div class="card-body stack stack-2">
                        <h2 class="h5"><a href="/states">Your state</a></h2>
                        <p class="text-sm text-muted">Statutes of limitations and local resources, added as each state completes legal review.</p>
                    </div>
                </li>
            </ul>
            <?php require __DIR__ . '/../partials/help-panel.php'; ?>
        </div>
    </main>

    <?php require __DIR__ . '/../partials/public-footer.php'; ?>
</body>
</html>

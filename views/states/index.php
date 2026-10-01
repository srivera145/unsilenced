<?php
use EchoDial\Deck\Deck;
use Keel\App\Models\StatePage;
use Keel\App\Support\Format;

$navCurrent = 'states';
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
            <h1 class="h2">States</h1>
            <p class="lede">Time limits for reporting and suing, and local resources, differ by state. We publish each state's information only after legal review.</p>
        </div>

        <ul class="grid min-15">
            <?php foreach ($pages as $page): ?>
            <li class="card">
                <div class="card-body stack stack-1">
                    <a class="fw-semi" href="/states/<?= strtolower(Format::e($page['code'])) ?>"><?= Format::e($page['name']) ?></a>
                    <span class="text-xs text-muted"><?= StatePage::isPublished($page) ? 'Legal information available' : 'Legal information coming soon' ?> · <?= Format::plural((int) ($schoolCounts[$page['code']] ?? 0), 'school') ?></span>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>

        <?php require __DIR__ . '/../partials/help-panel.php'; ?>
    </main>

    <?php require __DIR__ . '/../partials/public-footer.php'; ?>
</body>
</html>

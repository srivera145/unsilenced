<?php
use EchoDial\Deck\Deck;
use Keel\App\Support\Format;
use Keel\App\Support\Markdown;
?>
<!DOCTYPE html>
<html <?= Deck::htmlAttributes(lang: 'en') ?>>
<head>
<?php require __DIR__ . '/../partials/head.php'; ?>
</head>
<body>
    <?php require __DIR__ . '/../partials/public-header.php'; ?>

    <main id="main-content" tabindex="-1" class="container public-page stack stack-6">
        <nav aria-label="Breadcrumb">
            <ol class="breadcrumb">
                <li><a href="/resources">Resources</a></li>
                <li><span aria-current="page"><?= Format::e($page['title']) ?></span></li>
            </ol>
        </nav>

        <div class="split rail-22">
            <article class="stack stack-6">
                <h1 class="h2"><?= Format::e($page['title']) ?></h1>
                <div class="prose">
                    <?= Markdown::toHtml((string) $page['body']) ?>
                </div>
                <p class="text-xs text-muted">Last updated <?= Format::e(Format::date((string) $page['updated_at'])) ?>. This is general information, not legal or medical advice.</p>
            </article>
            <?php require __DIR__ . '/../partials/help-panel.php'; ?>
        </div>
    </main>

    <?php require __DIR__ . '/../partials/public-footer.php'; ?>
</body>
</html>

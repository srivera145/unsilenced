<?php
use EchoDial\Deck\Deck;
use Keel\App\Models\StatePage;
use Keel\App\Support\Format;
use Keel\App\Support\Markdown;

// Legal text is rendered ONLY for a published page. A page awaiting legal
// review shows "coming soon" and the national hotline, never a draft, whatever
// its legal fields happen to contain.
$published = StatePage::isPublished($page);
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
                <li><a href="/states">States</a></li>
                <li><span aria-current="page"><?= Format::e($page['name']) ?></span></li>
            </ol>
        </nav>

        <div class="split rail-22">
            <article class="stack stack-6">
                <h1 class="h2"><?= Format::e($page['name']) ?></h1>

                <?php if ($published): ?>
                <section class="stack stack-3" aria-labelledby="sol-title">
                    <h2 id="sol-title" class="h4">Statute of limitations</h2>
                    <div class="prose"><?= Markdown::toHtml((string) ($page['statute_of_limitations'] ?? '')) ?></div>
                </section>
                <section class="stack stack-3" aria-labelledby="resources-title">
                    <h2 id="resources-title" class="h4">Resources in <?= Format::e($page['name']) ?></h2>
                    <div class="prose"><?= Markdown::toHtml((string) ($page['resources'] ?? '')) ?></div>
                </section>
                <p class="text-xs text-muted">
                    Reviewed<?= !empty($page['legal_reviewed_on']) ? ' ' . Format::e(Format::date((string) $page['legal_reviewed_on'])) : '' ?>.
                    General information, not legal advice. Laws change; an attorney or advocate can tell you what applies to you.
                </p>
                <?php else: ?>
                <div class="card">
                    <div class="empty">
                        <span class="empty-art"><?= Deck::icon('clock') ?></span>
                        <p class="empty-title">Information coming soon</p>
                        <p>We publish each state's statute of limitations and local resources only after legal review. Until then, the national hotline can connect you with help in <?= Format::e($page['name']) ?> and answer questions about time limits.</p>
                    </div>
                </div>
                <?php endif; ?>

                <p><a href="/schools/<?= strtolower(Format::e($page['code'])) ?>">Schools in <?= Format::e($page['name']) ?></a> <span class="text-muted">(<?= Format::plural((int) $schoolCount, 'school') ?>)</span></p>
            </article>
            <?php require __DIR__ . '/../partials/help-panel.php'; ?>
        </div>
    </main>

    <?php require __DIR__ . '/../partials/public-footer.php'; ?>
</body>
</html>

<?php
use EchoDial\Deck\Deck;
use Keel\App\Support\Config;
use Keel\App\Support\Format;

$navCurrent = 'methodology';
$clery = (array) Config::get('sources.clery', []);
$correctionsEmail = $correctionsEmail ?? null;
?>
<!DOCTYPE html>
<html <?= Deck::htmlAttributes(lang: 'en') ?>>
<head>
<?php require __DIR__ . '/partials/head.php'; ?>
</head>
<body>
    <?php require __DIR__ . '/partials/public-header.php'; ?>

    <main id="main-content" tabindex="-1" class="container public-page stack stack-8">
        <article class="prose">
            <h1 class="h2">Corrections</h1>
            <p class="lede">If you think something on Unsilenced is wrong, please tell us. Schools, journalists, researchers and members of the public can all report an error in our figures or in an accountability record.</p>

            <h2>How to tell us</h2>
            <?php if ($correctionsEmail !== null): ?>
            <p>Email <a href="mailto:<?= Format::e($correctionsEmail) ?>"><?= Format::e($correctionsEmail) ?></a> and include:</p>
            <?php else: ?>
            <div class="alert alert-warn">
                <?= Deck::icon('alert-triangle') ?>
                <p>The corrections email address has not been set up yet.</p>
            </div>
            <p>When you write, include:</p>
            <?php endif; ?>
            <ul>
                <li>the address of the page, copied from your browser;</li>
                <li>what you believe is wrong;</li>
                <li>where the correct information can be found: a published data file, an official document, a court record or a public statement.</li>
            </ul>
            <p>If you are writing for a school, please say what your role is.</p>
            <p>This page has no form and keeps nothing. Your message goes from your own email account to ours. Email is not anonymous: the people who run this site will see your address and what you write.</p>

            <h2>Clery Act figures</h2>
            <p>Our Clery figures are the figures each school submitted to the U.S. Department of Education, as the department publishes them in the <a href="<?= Format::e($clery['url'] ?? '') ?>" rel="noopener noreferrer"><?= Format::e($clery['name'] ?? 'Campus Safety and Security Survey') ?></a>. Before you write, please compare our figure with the department's data and with the school's own Annual Security Report.</p>
            <ul>
                <li><strong>Our figure does not match the department's data:</strong> that is our error. Tell us and we will fix it.</li>
                <li><strong>Our figure matches the department's data, but the school says it is wrong:</strong> the school needs to correct its submission with the department. The correction appears in the department's next published file, and our figures change when we import it.</li>
            </ul>
            <p><a href="/methodology">How we get our data</a> explains how we combine campuses and the department's yearly files.</p>

            <h2>Accountability records</h2>
            <p>Each record links to its public source. If a date, status or outcome is wrong, or the public record has changed since we wrote it (for example, an investigation has closed or a lawsuit has been settled or dismissed), send us the newer source and we will update the record.</p>

            <h2>What happens next</h2>
            <p>We check what you send against the original source. If we made an error, we correct it.</p>

            <h2>Please do not send</h2>
            <p>Personal stories, the names of people involved in a case, or documents about an individual. Unsilenced does not publish personal accounts or name individuals, and email is not a safe way to share them.</p>
        </article>

        <aside class="prose" aria-label="Get help">
            <?php require __DIR__ . '/partials/help-panel.php'; ?>
        </aside>
    </main>

    <?php require __DIR__ . '/partials/public-footer.php'; ?>
</body>
</html>

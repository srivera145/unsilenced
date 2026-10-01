<?php
use EchoDial\Deck\Deck;
use Keel\App\Support\Config;
use Keel\App\Support\Format;

$help = (array) Config::get('help', []);
$cleryYears = $cleryYears ?? [];
$states = $states ?? [];

$siteSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => Config::get('site.name'),
    'description' => Config::get('site.description'),
    'url' => rtrim((string) \Keel\Core\Env::get('APP_URL', ''), '/') . '/',
    'potentialAction' => [
        '@type' => 'SearchAction',
        'target' => rtrim((string) \Keel\Core\Env::get('APP_URL', ''), '/') . '/schools?q={search_term_string}',
        'query-input' => 'required name=search_term_string',
    ],
];
?>
<!DOCTYPE html>
<html <?= Deck::htmlAttributes(lang: 'en') ?>>
<head>
<?php require __DIR__ . '/partials/head.php'; ?>
<script type="application/ld+json"><?= json_encode($siteSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
</head>
<body>
    <?php require __DIR__ . '/partials/public-header.php'; ?>

    <main id="main-content" tabindex="-1">
        <section class="container home-hero stack stack-6" aria-labelledby="home-title">
            <h1 id="home-title" class="enough">Enough.</h1>
            <p class="lede">Unsilenced tracks how U.S. colleges handle sexual assault, using public federal data on what each school reports and the public record of how it has responded.</p>

            <form class="stack stack-2" method="get" action="/schools" role="search" aria-label="Find a school">
                <label class="label" for="home-search">Find a school by name or city</label>
                <div class="cluster cluster-tight">
                    <div class="search grow" style="min-inline-size: min(100%, 18rem)">
                        <?= Deck::icon('search') ?>
                        <input class="input" type="search" id="home-search" name="q" autocomplete="off" placeholder="e.g. State University" required>
                    </div>
                    <button class="btn btn-primary" type="submit">Search</button>
                </div>
                <?php if ($schoolCount > 0): ?>
                <p class="help"><?= Format::plural((int) $schoolCount, 'school') ?> with Clery Act figures<?= $cleryYears !== [] ? ' for ' . (min($cleryYears) === max($cleryYears) ? min($cleryYears) : min($cleryYears) . '–' . max($cleryYears)) : '' ?>, from U.S. Department of Education data.</p>
                <?php endif; ?>
            </form>
        </section>

        <section class="container section stack stack-6" aria-labelledby="help-title">
            <div class="card">
                <div class="card-body stack stack-3">
                    <h2 id="help-title" class="h4">Need help now?</h2>
                    <p>The <?= htmlspecialchars((string) $help['hotline_name']) ?> is free and confidential, 24 hours a day.</p>
                    <p><a class="hotline-number" href="tel:<?= htmlspecialchars((string) $help['hotline_tel'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $help['hotline_display']) ?></a></p>
                    <p>Or chat at <a href="<?= htmlspecialchars((string) $help['chat_url'], ENT_QUOTES, 'UTF-8') ?>" rel="noopener noreferrer"><?= htmlspecialchars((string) $help['chat_label']) ?></a>. If you are in immediate danger, call <a href="tel:<?= htmlspecialchars((string) $help['emergency_number'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $help['emergency_number']) ?></a>.</p>
                    <p><a href="/resources/your-options">Your options, in plain language</a> · <a href="/resources/save-evidence">Save your evidence</a></p>
                </div>
            </div>
        </section>

        <section class="container section stack stack-6" aria-labelledby="data-title">
            <div class="stack stack-2">
                <h2 id="data-title">How we get our data</h2>
                <p class="lede">Every number on Unsilenced comes from a public source and is shown with that source and its year.</p>
            </div>
            <div class="grid" style="--min: 16rem">
                <article class="card">
                    <div class="card-body stack stack-2">
                        <h3 class="h5">What schools report</h3>
                        <p class="text-sm">Under the Clery Act, colleges that take federal student aid report certain crimes to the U.S. Department of Education each year, including rape, fondling, dating violence, domestic violence and stalking.</p>
                    </div>
                </article>
                <article class="card">
                    <div class="card-body stack stack-2">
                        <h3 class="h5">What the numbers can't show</h3>
                        <p class="text-sm">Most sexual assaults are never reported to a school or to police, so these figures count reports, not every assault. A low number is not the same as a safe campus.</p>
                    </div>
                </article>
                <article class="card">
                    <div class="card-body stack stack-2">
                        <h3 class="h5">The public record</h3>
                        <p class="text-sm">We add federal Title IX investigations, lawsuits, state reviews and news coverage, summarized in our own words with a link to the source. We never name individuals.</p>
                    </div>
                </article>
            </div>
            <p><a class="btn" href="/methodology">Read our methodology</a></p>
        </section>

        <section class="container section stack stack-4" aria-labelledby="browse-title">
            <h2 id="browse-title" class="h4">Browse by state</h2>
            <ul class="cluster cluster-tight text-sm">
                <?php foreach ($states as $code => $name): ?>
                <li><a href="/schools/<?= strtolower($code) ?>"><?= htmlspecialchars($name) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </section>
    </main>

    <?php require __DIR__ . '/partials/public-footer.php'; ?>
</body>
</html>

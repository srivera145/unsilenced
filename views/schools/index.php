<?php
use EchoDial\Deck\Deck;
use Keel\App\Models\School;
use Keel\App\Support\Config;
use Keel\App\Support\Format;

$rows = $results['rows'] ?? [];
$total = (int) ($results['total'] ?? 0);
$pages = max(1, (int) ceil($total / $perPage));
$controls = (array) Config::get('controls', []);
$searched = $query !== '' || $state !== null;
$navCurrent = 'schools';

$pageUrl = static function (int $page) use ($query, $state): string {
    $params = array_filter(['q' => $query, 'page' => $page > 1 ? $page : null], static fn ($v) => $v !== null && $v !== '');
    $base = $state !== null ? '/schools/' . strtolower($state) : '/schools';

    return $base . ($params === [] ? '' : '?' . http_build_query($params));
};
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
            <?php if ($stateName !== null): ?>
            <nav aria-label="Breadcrumb">
                <ol class="breadcrumb">
                    <li><a href="/schools">Schools</a></li>
                    <li><span aria-current="page"><?= htmlspecialchars($stateName) ?></span></li>
                </ol>
            </nav>
            <h1 class="h2">Schools in <?= htmlspecialchars($stateName) ?></h1>
            <?php else: ?>
            <h1 class="h2">Find a school</h1>
            <?php endif; ?>
            <p class="text-muted">Search by school name or city. Every college in federal IPEDS data is listed, including schools with no Clery figures imported yet.</p>
        </div>

        <form class="card" method="get" action="/schools" role="search" aria-label="Search schools">
            <div class="card-body stack stack-4">
                <div class="field-row">
                    <div class="field">
                        <label class="label" for="school-q">School name or city</label>
                        <input class="input" type="search" id="school-q" name="q" value="<?= Format::e($query) ?>" autocomplete="off">
                    </div>
                    <div class="field">
                        <label class="label" for="school-state">State</label>
                        <select class="select" id="school-state" name="state">
                            <option value="">All states</option>
                            <?php foreach ($states as $code => $name): ?>
                            <option value="<?= Format::e($code) ?>"<?= $state === $code ? ' selected' : '' ?>><?= Format::e($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div><button class="btn btn-primary" type="submit">Search</button></div>
            </div>
        </form>

        <?php if ($searched): ?>
        <section class="stack stack-4" aria-labelledby="results-title">
            <h2 id="results-title" class="h5" aria-live="polite">
                <?= Format::plural($total, 'school') ?><?= $query !== '' ? ' matching “' . Format::e($query) . '”' : '' ?><?= $stateName !== null && $query !== '' ? ' in ' . Format::e($stateName) : '' ?>
            </h2>

            <?php if ($rows === []): ?>
            <div class="card">
                <div class="empty">
                    <span class="empty-art"><?= Deck::icon('search') ?></span>
                    <p class="empty-title">No schools found</p>
                    <p>Try a shorter name, the city, or another state.</p>
                </div>
            </div>
            <?php else: ?>
            <ul class="card list">
                <?php foreach ($rows as $row): ?>
                <li class="list-row">
                    <div class="list-main">
                        <a class="list-title" href="<?= Format::e(School::path($row)) ?>"><?= Format::e($row['name']) ?></a>
                        <span class="list-sub"><?= Format::e(trim(($row['city'] ?? '') . ', ' . $row['state'], ', ')) ?><?= isset($controls[$row['control'] ?? '']) ? ' · ' . Format::e($controls[$row['control']]) : '' ?></span>
                    </div>
                    <?php if ($row['enrollment'] !== null): ?>
                    <span class="list-trail nums"><?= Format::number((int) $row['enrollment']) ?> students</span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <p class="source-note">Enrollment: <?= Format::e(Config::get('sources.ipeds.short')) ?>, total enrollment for the year shown on each school's page.</p>

            <?php if ($pages > 1): ?>
            <nav class="pagination" aria-label="Result pages">
                <?php if ($page > 1): ?><a href="<?= Format::e($pageUrl($page - 1)) ?>" rel="prev">Previous</a><?php endif; ?>
                <span aria-current="page">Page <?= (int) $page ?> of <?= (int) $pages ?></span>
                <?php if ($page < $pages): ?><a href="<?= Format::e($pageUrl($page + 1)) ?>" rel="next">Next</a><?php endif; ?>
            </nav>
            <?php endif; ?>
            <?php endif; ?>
        </section>
        <?php else: ?>
        <section class="stack stack-4" aria-labelledby="browse-title">
            <h2 id="browse-title" class="h5">Browse by state</h2>
            <ul class="grid text-sm" style="--min: 12rem">
                <?php foreach ($states as $code => $name): ?>
                <li><a href="/schools/<?= strtolower(Format::e($code)) ?>"><?= Format::e($name) ?></a> <span class="text-muted nums">(<?= number_format($stateCounts[$code] ?? 0) ?>)</span></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>
    </main>

    <?php require __DIR__ . '/../partials/public-footer.php'; ?>
</body>
</html>

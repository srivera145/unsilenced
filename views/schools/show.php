<?php
use EchoDial\Deck\Deck;
use Keel\App\Services\SchoolProfileService;
use Keel\App\Support\Config;
use Keel\App\Support\Format;

$navCurrent = 'schools';
$offenses = (array) Config::get('offenses', []);
$locations = (array) Config::get('locations', []);
$groups = (array) Config::get('offense_groups', []);
$controls = (array) Config::get('controls', []);
$types = (array) Config::get('accountability.types', []);
$statuses = (array) Config::get('accountability.statuses', []);
$clerySource = (array) Config::get('sources.clery', []);
$ipedsSource = (array) Config::get('sources.ipeds', []);

$years = $profile['years'];
$latestYear = $profile['latest_year'];
$latest = $latestYear !== null ? $profile['by_year'][$latestYear] : null;
$enrollment = $school['enrollment'] !== null ? (int) $school['enrollment'] : null;
$enrollmentYear = $school['enrollment_year'] !== null ? (int) $school['enrollment_year'] : null;
$comparison = $profile['comparison'];
$threshold = (int) Config::get('context_note_min_enrollment', 5000);
$yearRange = $years === [] ? '' : (min($years) === max($years) ? (string) min($years) : min($years) . '–' . max($years));

// The chart partial gets its own scope so its locals cannot overwrite this page's.
$renderChart = static function (string $chartId, string $chartLabel, array $chartSeries): void {
    require __DIR__ . '/../partials/trend-chart.php';
};

$cleryCite = static fn (string $years): string => 'Source: ' . Format::e($clerySource['name'] ?? '') . ', calendar year ' . Format::e($years) . '.';
$enrollmentCite = $enrollmentYear !== null
    ? 'Enrollment: ' . Format::e($ipedsSource['name'] ?? '') . ', ' . $enrollmentYear . ' total enrollment.'
    : '';

$schoolSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'CollegeOrUniversity',
    'name' => $school['name'],
    'address' => array_filter([
        '@type' => 'PostalAddress',
        'addressLocality' => $school['city'],
        'addressRegion' => $school['state'],
        'addressCountry' => 'US',
    ]),
    'identifier' => [
        '@type' => 'PropertyValue',
        'propertyID' => 'IPEDS UNITID',
        'value' => (string) $school['unitid'],
    ],
];
if ($enrollment !== null) {
    $schoolSchema['numberOfStudents'] = $enrollment;
}
?>
<!DOCTYPE html>
<html <?= Deck::htmlAttributes(lang: 'en') ?>>
<head>
<?php require __DIR__ . '/../partials/head.php'; ?>
<script type="application/ld+json"><?= json_encode($schoolSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
</head>
<body>
    <?php require __DIR__ . '/../partials/public-header.php'; ?>

    <main id="main-content" tabindex="-1" class="container public-page stack stack-8">
        <header class="stack stack-3">
            <nav aria-label="Breadcrumb">
                <ol class="breadcrumb">
                    <li><a href="/schools">Schools</a></li>
                    <li><a href="/schools/<?= strtolower(Format::e($school['state'])) ?>"><?= Format::e($stateName) ?></a></li>
                    <li><span aria-current="page"><?= Format::e($school['name']) ?></span></li>
                </ol>
            </nav>
            <h1 class="h2"><?= Format::e($school['name']) ?></h1>
            <dl class="cluster text-sm">
                <div class="cluster cluster-tight"><dt class="text-muted">Location</dt><dd><?= Format::e(trim(($school['city'] ?? '') . ', ' . $stateName, ', ')) ?></dd></div>
                <div class="cluster cluster-tight"><dt class="text-muted">Type</dt><dd><?= Format::e($controls[$school['control'] ?? ''] ?? 'Not reported') ?></dd></div>
                <div class="cluster cluster-tight"><dt class="text-muted">Enrollment</dt><dd><?= $enrollment !== null ? Format::number($enrollment) . ' students' : 'Not reported' ?></dd></div>
            </dl>
            <p class="source-note">School details: <?= Format::e($ipedsSource['name'] ?? '') ?><?= $enrollmentYear !== null ? ', ' . $enrollmentYear : '' ?>. IPEDS UNITID <?= Format::e($school['unitid']) ?>.</p>
        </header>

        <section class="stack stack-6" aria-labelledby="clery-title">
            <div class="stack stack-2">
                <h2 id="clery-title" class="h3">Reported sexual violence (Clery Act)</h2>
                <p class="text-muted">What this school reported to the U.S. Department of Education. These are reports, not every incident: most sexual assaults are never reported to a school. <a href="/methodology">About this data</a>.</p>
            </div>

            <?php if ($latest === null): ?>
            <div class="card">
                <div class="empty">
                    <span class="empty-art"><?= Deck::icon('chart') ?></span>
                    <p class="empty-title">No Clery data imported for this school yet</p>
                    <p>We have not imported Clery Act figures for this school. Some institutions are not required to report, and some report under a different institution's ID.</p>
                </div>
            </div>
            <?php else: ?>

            <?php if ($profile['context_years'] !== []): ?>
            <div class="alert alert-info" role="note">
                <?= Deck::icon('info') ?>
                <div>
                    <p class="alert-title">About the zero in <?= Format::e(Format::list($profile['context_years'])) ?></p>
                    <p class="alert-body">This school reported zero rapes for <?= Format::e(Format::list($profile['context_years'])) ?>. National surveys of college students have found that most sexual assaults are never reported to police or to the school, so a low number in this data can reflect underreporting rather than an absence of assaults. We show this note for every school with <?= Format::number($threshold) ?> or more students that reports zero rapes in a year; it is not a finding about this school. <a href="/methodology#underreporting">Sources</a>.</p>
                </div>
            </div>
            <?php endif; ?>

            <section class="stack stack-3" aria-labelledby="glance-title">
                <h3 id="glance-title" class="h5">At a glance: <?= (int) $latestYear ?>, all Clery locations</h3>
                <dl class="grid" style="--min: 9.5rem">
                    <?php foreach ($offenses as $key => $label): ?>
                    <div class="count-tile stat">
                        <dt class="stat-label"><?= Format::e($label) ?></dt>
                        <dd class="stat-value"><?= Format::number($latest['totals'][$key] ?? null, 'Not reported') ?></dd>
                    </div>
                    <?php endforeach; ?>
                </dl>
                <p class="source-note"><?= $cleryCite((string) $latestYear) ?> Totals add on-campus, noncampus and public-property reports; on-campus student housing is already part of on campus.</p>
            </section>

            <section class="stack stack-3" aria-labelledby="rate-title">
                <h3 id="rate-title" class="h5">Rate per 1,000 students: <?= (int) $latestYear ?></h3>
                <?php if ($comparison === null): ?>
                <p class="text-muted">No rate is shown because this school has no enrollment figure in the IPEDS data we imported.</p>
                <?php else: ?>
                <div class="card table-wrap" role="region" aria-labelledby="rate-title" tabindex="0">
                    <table class="table table-stack">
                        <thead>
                            <tr>
                                <th scope="col">Reports per 1,000 students</th>
                                <th scope="col" class="text-end">This school</th>
                                <th scope="col" class="text-end"><?= Format::e($stateName) ?> schools</th>
                                <th scope="col" class="text-end">Schools with <?= Format::e($comparison['band']['label'] ?? 'similar enrollment') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($groups as $key => $group): $row = $comparison['groups'][$key]; ?>
                            <tr>
                                <th scope="row" data-label="Offenses"><?= Format::e($group['label']) ?></th>
                                <td data-label="This school" class="text-end nums fw-semi"><?= Format::rate($row['school'], 'Not reported') ?></td>
                                <?php foreach (['state' => $stateName . ' schools', 'peer' => 'Similar size'] as $groupKey => $groupLabel): ?>
                                <td data-label="<?= Format::e($groupLabel) ?>" class="text-end nums">
                                    <?php if ($row[$groupKey] === null): ?>
                                    <span class="text-muted text-sm">Too few schools to compare</span>
                                    <?php else: ?>
                                    <?= Format::rate($row[$groupKey]) ?> <span class="text-muted text-xs">(<?= Format::plural((int) $row[$groupKey . '_schools'], 'school') ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php
                $sexRow = $comparison['groups']['sex_offenses'] ?? null;
                $comparisonPhrases = [];
                if ($sexRow !== null && ($words = SchoolProfileService::compareWords($sexRow['school'], $sexRow['state'])) !== null) {
                    $comparisonPhrases[] = $words . ' the combined rate for ' . $stateName . ' schools';
                }
                if ($sexRow !== null && ($words = SchoolProfileService::compareWords($sexRow['school'], $sexRow['peer'])) !== null) {
                    $comparisonPhrases[] = $words . ' the combined rate for schools with ' . $comparison['band']['label'];
                }
                ?>
                <?php if ($comparisonPhrases !== []): ?>
                <p>In <?= (int) $latestYear ?>, this school's reported sex-offense rate was <?= Format::e(Format::list($comparisonPhrases)) ?>. Higher or lower reported numbers do not on their own show whether a campus is more or less safe: they also reflect how many students come forward and how the school counts reports.</p>
                <?php endif; ?>
                <p class="source-note"><?= $cleryCite((string) $latestYear) ?> <?= $enrollmentCite ?> Calculated by Unsilenced. State and size-group figures add every report and every enrolled student across the schools listed that reported for <?= (int) $latestYear ?>, including this one.</p>
                <?php endif; ?>
            </section>

            <section class="stack stack-3" aria-labelledby="trend-title">
                <h3 id="trend-title" class="h5">Trend<?= count($years) > 1 ? ', ' . Format::e($yearRange) : '' ?></h3>
                <?php if (count($years) < 2): ?>
                <p class="text-muted">A trend appears once more than one year of Clery data is imported for this school.</p>
                <?php else: ?>
                <p class="text-sm text-muted">Reports per year at all Clery locations. Each chart has its own scale.</p>
                <div class="trend-grid chart">
                    <?php foreach ($offenses as $key => $label): ?>
                    <figure class="trend-cell stack stack-2">
                        <figcaption class="chart-title"><?= Format::e($label) ?></figcaption>
                        <?php $renderChart('trend-' . $key, $label, $profile['series'][$key]); ?>
                    </figure>
                    <?php endforeach; ?>
                </div>
                <p class="source-note"><?= $cleryCite($yearRange) ?></p>
                <?php endif; ?>
            </section>

            <section class="stack stack-3" aria-labelledby="location-title">
                <h3 id="location-title" class="h5">By location and year</h3>
                <p class="text-sm text-muted">Clery locations: <strong>on campus</strong> (which includes on-campus student housing), <strong>noncampus</strong> buildings the school or its student groups own or control, and <strong>public property</strong> next to campus. “Not reported” means the figure was not in the data.</p>
                <?php foreach (array_reverse($years) as $index => $year): $data = $profile['by_year'][$year]; ?>
                <details class="card"<?= $index === 0 ? ' open' : '' ?>>
                    <summary class="card-header"><span class="card-title"><?= (int) $year ?></span></summary>
                    <div class="table-wrap" role="region" aria-label="Reports by location, <?= (int) $year ?>" tabindex="0">
                        <table class="table table-compact location-table">
                            <thead>
                                <tr>
                                    <th scope="col">Offense</th>
                                    <?php foreach ($locations as $location => $label): ?>
                                    <th scope="col" class="text-end"><?= Format::e($label) ?><?= $location === 'on_campus_housing' ? ' <span class="text-xs text-muted">(part of on campus)</span>' : '' ?></th>
                                    <?php endforeach; ?>
                                    <th scope="col" class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($offenses as $key => $label): ?>
                                <tr>
                                    <th scope="row" data-label="Offense"><?= Format::e($label) ?></th>
                                    <?php foreach ($locations as $location => $locationLabel): ?>
                                    <td data-label="<?= Format::e($locationLabel) ?>" class="text-end nums"><?= Format::number($data['locations'][$location][$key] ?? null, 'Not reported') ?></td>
                                    <?php endforeach; ?>
                                    <td data-label="Total" class="text-end nums fw-semi"><?= Format::number($data['totals'][$key] ?? null, 'Not reported') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="card-footer source-note"><?= $cleryCite((string) $year) ?></p>
                </details>
                <?php endforeach; ?>
            </section>
            <?php endif; ?>
        </section>

        <section class="stack stack-4" aria-labelledby="record-title">
            <div class="stack stack-2">
                <h2 id="record-title" class="h3">Accountability record</h2>
                <p class="text-muted">Public records about how this school has handled sexual violence: federal Title IX investigations, lawsuits, state reviews and news coverage. Each is summarized in our own words, with a link to the source. We never name individuals.</p>
            </div>

            <?php if ($items === []): ?>
            <div class="card">
                <div class="empty">
                    <span class="empty-art"><?= Deck::icon('file') ?></span>
                    <p class="empty-title">No records added yet</p>
                    <p>We have not added any accountability records for this school. That does not mean none exist.</p>
                </div>
            </div>
            <?php else: ?>
            <ol class="accountability-list">
                <?php foreach ($items as $item): ?>
                <li class="accountability-item stack stack-2">
                    <div class="cluster cluster-tight">
                        <span class="badge"><?= Format::e($types[$item['type']] ?? $item['type']) ?></span>
                        <span class="badge badge-brand"><?= Format::e($statuses[$item['status']] ?? $item['status']) ?></span>
                        <time class="text-sm text-muted" datetime="<?= Format::e($item['item_date']) ?>"><?= Format::e(Format::date($item['item_date'])) ?></time>
                    </div>
                    <p><?= nl2br(Format::e($item['summary'])) ?></p>
                    <p class="source-note">Source: <a href="<?= Format::e($item['source_url']) ?>" rel="noopener noreferrer nofollow"><?= Format::e($item['source_name']) ?></a></p>
                </li>
                <?php endforeach; ?>
            </ol>
            <?php endif; ?>
        </section>

        <?php require __DIR__ . '/../partials/help-panel.php'; ?>
    </main>

    <?php require __DIR__ . '/../partials/public-footer.php'; ?>
</body>
</html>

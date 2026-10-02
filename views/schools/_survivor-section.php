<?php
/**
 * "What survivors have told us" on a school page, below the Clery data.
 * Only while SUBMISSIONS_ENABLED; $survivorStats from ReportStatsService.
 *
 * Figures appear once the school has stats_min_reports approved reports
 * whose authors agreed to be counted, and each figure only when that many
 * responses contribute to it (Phase 2.1): no count under the threshold is
 * ever printed. Published accounts show the year, the
 * kind of place and the kind of person only. The wording is neutral: these
 * are accounts, not findings, and nothing here says a school failed to report
 * a particular crime.
 */
use EchoDial\Deck\Deck;
use Keel\App\Support\Format;

$stats = $survivorStats;
$threshold = (int) $stats['threshold'];
$notEnough = 'Not enough responses yet.';
?>
<section class="stack stack-6" aria-labelledby="survivors-title">
    <div class="stack stack-2">
        <h2 id="survivors-title" class="h3">What survivors have told us</h2>
        <p class="text-muted">Anonymous reports sent to Unsilenced by people who say they experienced sexual violence while connected to this school. Our team reviews each one before it is counted or shown, and removes anything that could identify anyone. We do not independently verify them.</p>
    </div>

    <div class="alert alert-info" role="note">
        <?= Deck::icon('info') ?>
        <div>
            <p class="alert-title">How this compares with the Clery figures above</p>
            <p class="alert-body">Clery figures count reports made to the school. Survivor reports here include assaults that were never reported to the school.</p>
        </div>
    </div>

    <?php if (!$stats['show_stats']): ?>
    <div class="card">
        <div class="card-body">
            <p>Fewer than <?= $threshold ?> survivor reports so far.</p>
        </div>
    </div>
    <?php else: ?>
    <section class="stack stack-3" aria-labelledby="survivor-figures-title">
        <h3 id="survivor-figures-title" class="h5">From <?= Format::plural($stats['count'], 'survivor report') ?></h3>
        <?php /* Every figure needs $threshold responses of its own; below that it is not shown at all (ReportStatsService). */ ?>
        <dl class="grid min-15">
            <div class="count-tile stat">
                <dt class="stat-label">Reported it to the school</dt>
                <dd class="stat-value"><?= $stats['reported_pct'] !== null ? (int) $stats['reported_pct'] . '%' : '<span class="not-enough">' . $notEnough . '</span>' ?></dd>
            </div>
            <div class="count-tile stat">
                <dt class="stat-label">Say the school discouraged them or pressured them to stay quiet</dt>
                <dd class="stat-value"><?= $stats['discouraged_pct'] !== null ? (int) $stats['discouraged_pct'] . '%' : '<span class="not-enough">' . $notEnough . '</span>' ?></dd>
            </div>
            <div class="count-tile stat">
                <dt class="stat-label">Average rating of the school's response</dt>
                <?php if ($stats['average_rating'] !== null): ?>
                <dd class="stat-value"><?= Format::e(number_format((float) $stats['average_rating'], 1)) ?><span class="stat-unit"> of 5</span></dd>
                <dd class="text-xs text-muted">From <?= Format::plural((int) $stats['rating_count'], 'rating') ?>, 1 (very poorly) to 5 (very well)</dd>
                <?php else: ?>
                <dd class="stat-value"><span class="not-enough"><?= $notEnough ?></span></dd>
                <?php endif; ?>
            </div>
        </dl>

        <div class="grid min-16">
            <div class="stack stack-2">
                <h4 class="h6">Reports by the year it happened</h4>
                <?php if ($stats['by_year'] === null): ?>
                <p class="text-sm"><?= $notEnough ?> No single year has <?= $threshold ?> or more reports.</p>
                <?php else: ?>
                <div class="table-wrap" role="region" aria-label="Survivor reports by year" tabindex="0">
                    <table class="table table-compact">
                        <thead><tr><th scope="col">Year</th><th scope="col" class="text-end">Reports</th></tr></thead>
                        <tbody>
                            <?php foreach ($stats['by_year'] as $row): ?>
                            <tr><th scope="row"><?= Format::e($row['label']) ?></th><td class="text-end nums"><?= $row['count'] !== null ? (int) $row['count'] : 'Fewer than ' . $threshold ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($stats['any_not_reported']): ?>
            <div class="stack stack-2">
                <h4 class="h6">Why some did not report it to the school</h4>
                <?php if ($stats['top_reasons'] === []): ?>
                <p class="text-sm"><?= $notEnough ?></p>
                <?php else: ?>
                <p class="text-sm text-muted">The most common reasons given by <?= $stats['not_reported'] !== null ? 'the ' . Format::plural((int) $stats['not_reported'], 'person', 'people') . ' who did not' : 'people who did not report it to the school' ?>. Each could give more than one.</p>
                <ol class="reason-list">
                    <?php foreach ($stats['top_reasons'] as $reason): ?>
                    <li><?= Format::e($reason['label']) ?> <span class="text-muted nums">(<?= (int) $reason['count'] ?>)</span></li>
                    <?php endforeach; ?>
                </ol>
                <?php endif; ?>
                <?php if ($stats['reasons_left_out']): ?>
                <p class="text-xs text-muted">Reasons given by fewer than <?= $threshold ?> people are not shown.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <p class="source-note">Source: anonymous reports to Unsilenced, reviewed by our team, not independently verified. Only reports whose authors agreed to be counted. Percentages are of all <?= Format::plural($stats['count'], 'report') ?> shown. A figure is shown only when at least <?= $threshold ?> responses contribute to it.</p>
    </section>
    <?php endif; ?>

    <?php if ($stats['accounts'] !== []): ?>
    <section class="stack stack-3" aria-labelledby="accounts-title">
        <h3 id="accounts-title" class="h5">In their words</h3>
        <p class="text-sm text-muted">Shared by survivors who chose to publish their account, edited only to remove anything that could identify anyone. Newest first.</p>
        <ol class="account-list">
            <?php foreach ($stats['accounts'] as $entry): ?>
            <li class="account-item stack stack-2">
                <div class="cluster cluster-tight">
                    <span class="badge"><?= (int) $entry['year'] ?></span>
                    <?php if ($entry['setting'] !== null): ?><span class="badge"><?= Format::e($entry['setting']) ?></span><?php endif; ?>
                    <span class="badge"><?= Format::e($entry['category']) ?></span>
                    <?php if ($entry['evidence_badge']): ?><span class="badge badge-brand"><?= Deck::icon('file-sm') ?> Evidence on file</span><?php endif; ?>
                </div>
                <blockquote class="published-account pre-line"><?= Format::e($entry['text']) ?></blockquote>
            </li>
            <?php endforeach; ?>
        </ol>
        <?php if (in_array(true, array_column($stats['accounts'], 'evidence_badge'), true)): ?>
        <p class="source-note">"Evidence on file" means the author gave us evidence and our team has reviewed it. Evidence is never published.</p>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <div class="card card-brand-soft">
        <div class="card-body stack stack-2">
            <p class="fw-semi">Did something happen to you at this school?</p>
            <p class="text-sm">You can tell us, anonymously and at your own pace. Nothing is published unless you choose, and you can withdraw it at any time.</p>
            <p><a class="btn btn-primary" href="/submit?school=<?= (int) $schoolId ?>">Tell us what happened</a></p>
        </div>
    </div>
</section>

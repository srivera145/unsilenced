<?php
use EchoDial\Deck\Deck;
use Keel\App\Support\Config;
use Keel\App\Support\Format;
use Keel\App\Support\Submissions;

$adminSection = 'reports';
require __DIR__ . '/../_top.php';
$config = (array) Config::get('survivor_reports', []);
$tabs = [
    '' => ['Queue', $counts['submitted'] + $counts['in_review'] + $counts['changes_requested']],
    'submitted' => ['Waiting', $counts['submitted']],
    'in_review' => ['In review', $counts['in_review']],
    'changes_requested' => ['Changes requested', $counts['changes_requested']],
    'approved' => ['Approved', $counts['approved']],
    'rejected' => ['Rejected', $counts['rejected']],
];
$statusLabels = [
    'submitted' => 'Waiting for review',
    'in_review' => 'In review',
    'changes_requested' => 'Changes requested',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
];
?>
        <div class="stack stack-2">
            <h1 class="h2">Survivor reports</h1>
            <p class="text-muted">Nothing a survivor sends is public until it is approved here, and only what they agreed to. <?= Format::plural($counts['private'], 'report is', 'reports are') ?> kept private by their authors: no one here can open those.</p>
            <?php if (!Submissions::enabled()): ?>
            <div class="alert alert-info">
                <?= Deck::icon('info') ?>
                <p>Submissions are switched off (SUBMISSIONS_ENABLED=false): the public pages show "coming soon". This queue still works, for testing.</p>
            </div>
            <?php endif; ?>
        </div>

        <nav class="tabs" aria-label="Filter by status">
            <?php foreach ($tabs as $key => [$label, $count]): ?>
            <a class="tab<?= ($status ?? '') === $key ? ' is-active' : '' ?>" href="/admin/reports<?= $key === '' ? '' : '?status=' . Format::e($key) ?>"<?= ($status ?? '') === $key ? ' aria-current="page"' : '' ?>><?= Format::e($label) ?> <span class="badge"><?= (int) $count ?></span></a>
            <?php endforeach; ?>
        </nav>

        <section class="card" aria-labelledby="queue-title">
            <div class="card-header"><h2 id="queue-title" class="card-title"><?= Format::plural(count($reports), 'report') ?></h2></div>
            <div class="table-wrap" tabindex="0" role="region" aria-labelledby="queue-title">
                <table class="table table-stack">
                    <thead>
                        <tr>
                            <th scope="col">Report</th>
                            <th scope="col">School</th>
                            <th scope="col">Year</th>
                            <th scope="col">Publishing</th>
                            <th scope="col">Evidence</th>
                            <th scope="col">Sent (UTC)</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($reports === []): ?>
                        <tr>
                            <td colspan="7" class="table-empty">
                                <div class="empty">
                                    <span class="empty-art"><?= Deck::icon('check-circle') ?></span>
                                    <p class="empty-title">Nothing here</p>
                                    <p>No reports with this status.</p>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($reports as $report): ?>
                        <tr>
                            <td data-label="Report"><a href="/admin/reports/<?= (int) $report['id'] ?>">#<?= (int) $report['id'] ?></a></td>
                            <td data-label="School"><?= Format::e($report['school_name']) ?>, <?= Format::e($report['school_state']) ?></td>
                            <td data-label="Year" class="nums"><?= (int) $report['incident_year'] ?></td>
                            <td data-label="Publishing"><?= $report['consent'] === 'stats_and_account' ? 'Statistics and account' : 'Statistics only' ?></td>
                            <td data-label="Evidence" class="nums"><?= (int) $report['evidence_count'] ?></td>
                            <td data-label="Sent (UTC)" class="nums"><?= Format::e($report['submitted_at']) ?></td>
                            <td data-label="Status"><span class="badge <?= $report['status'] === 'approved' ? 'badge-good' : ($report['status'] === 'rejected' ? 'badge-bad' : 'badge-brand') ?>"><?= Format::e($statusLabels[$report['status']] ?? $report['status']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
<?php require __DIR__ . '/../_bottom.php'; ?>

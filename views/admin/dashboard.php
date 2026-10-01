<?php
use Keel\App\Support\Format;

$adminSection = 'dashboard';
require __DIR__ . '/_top.php';
$years = $counts['clery_years'];
?>
        <h1 class="h2">Overview</h1>

        <dl class="grid" style="--min: 12rem">
            <div class="count-tile stat"><dt class="stat-label">Schools</dt><dd class="stat-value"><?= Format::number($counts['schools']) ?></dd></div>
            <div class="count-tile stat"><dt class="stat-label">Listed publicly (have Clery data)</dt><dd class="stat-value"><?= Format::number($counts['schools_with_clery']) ?></dd></div>
            <div class="count-tile stat"><dt class="stat-label">Clery rows</dt><dd class="stat-value"><?= Format::number($counts['clery_rows']) ?></dd></div>
            <div class="count-tile stat"><dt class="stat-label">Clery years</dt><dd class="stat-value"><?= $years === [] ? '—' : Format::e(min($years) === max($years) ? (string) min($years) : min($years) . '–' . max($years)) ?></dd></div>
            <div class="count-tile stat"><dt class="stat-label">Accountability records</dt><dd class="stat-value"><?= Format::number($counts['items']) ?></dd></div>
            <div class="count-tile stat"><dt class="stat-label">State pages published</dt><dd class="stat-value"><?= (int) $counts['states_published'] ?> / <?= (int) $counts['states_total'] ?></dd></div>
        </dl>

        <section class="card" aria-labelledby="runs-title">
            <div class="card-header">
                <h2 id="runs-title" class="card-title">Recent imports</h2>
                <a class="push text-sm" href="/admin/imports">All imports</a>
            </div>
            <?php if ($runs === []): ?>
            <div class="card-body">
                <p class="text-muted">No imports yet. Imports run from the command line:</p>
                <pre><code>php database/console.php import:schools storage/imports/hd2025.csv
php database/queue-work.php --once</code></pre>
            </div>
            <?php else: ?>
            <ul class="list">
                <?php foreach ($runs as $run): ?>
                <li class="list-row">
                    <div class="list-main">
                        <a class="list-title" href="/admin/imports/<?= (int) $run['id'] ?>">#<?= (int) $run['id'] ?> <?= Format::e($run['file_name']) ?></a>
                        <span class="list-sub"><?= Format::e($run['kind']) ?> · <?= Format::e(\Keel\App\Models\ImportRun::yearsLabel($run)) ?> · <?= Format::e($run['status']) ?></span>
                    </div>
                    <span class="list-trail nums">+<?= (int) $run['rows_added'] ?> / ~<?= (int) $run['rows_updated'] ?> / <?= (int) $run['error_count'] ?> errors</span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>
<?php require __DIR__ . '/_bottom.php'; ?>

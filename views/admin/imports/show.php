<?php
use EchoDial\Deck\Deck;
use Keel\App\Support\Config;
use Keel\App\Support\Format;

$adminSection = 'imports';
require __DIR__ . '/../_top.php';
$offenseLabels = (array) Config::get('offenses', []);
?>
        <header class="stack stack-2">
            <nav aria-label="Breadcrumb">
                <ol class="breadcrumb">
                    <li><a href="/admin/imports">Imports</a></li>
                    <li><span aria-current="page">Run #<?= (int) $run['id'] ?></span></li>
                </ol>
            </nav>
            <h1 class="h2">Run #<?= (int) $run['id'] ?>: <?= Format::e($run['file_name']) ?></h1>
        </header>

        <?php if ($run['status'] === 'failed'): ?>
        <div class="alert alert-bad" role="alert">
            <?= Deck::icon('alert-circle') ?>
            <div>
                <p class="alert-title">Import failed</p>
                <p class="alert-body pre-line"><?= Format::e($run['message'] ?? '') ?></p>
            </div>
        </div>
        <?php elseif ($run['message']): ?>
        <div class="alert alert-good"><?= Deck::icon('check-circle') ?><p><?= Format::e($run['message']) ?></p></div>
        <?php endif; ?>

        <dl class="grid min-10">
            <?php foreach (['rows_read' => 'Rows read', 'rows_added' => 'Added', 'rows_updated' => 'Updated', 'rows_unchanged' => 'Unchanged', 'rows_skipped' => 'Skipped', 'error_count' => 'Errors'] as $key => $label): ?>
            <div class="count-tile stat"><dt class="stat-label"><?= $label ?></dt><dd class="stat-value nums"><?= Format::number((int) $run[$key]) ?></dd></div>
            <?php endforeach; ?>
        </dl>

        <section class="card" aria-labelledby="details-title">
            <div class="card-header"><h2 id="details-title" class="card-title">Details</h2></div>
            <div class="card-body">
                <dl class="stack stack-2 text-sm">
                    <div class="cluster"><dt class="text-muted">Kind</dt><dd><?= Format::e($run['kind']) ?></dd></div>
                    <div class="cluster"><dt class="text-muted"><?= $run['kind'] === 'clery' ? 'Calendar years' : 'Enrollment year' ?></dt><dd><?= Format::e(\Keel\App\Models\ImportRun::yearsLabel($run)) ?><?= isset($columns['vintage']) ? ' <span class="text-muted text-xs">(newest year in the file: ' . (int) $columns['vintage'] . '; it takes precedence over older files)</span>' : '' ?></dd></div>
                    <?php if ($run['location']): ?><div class="cluster"><dt class="text-muted">Location</dt><dd><?= Format::e(Config::get('locations.' . $run['location'], $run['location'])) ?></dd></div><?php endif; ?>
                    <div class="cluster"><dt class="text-muted">File</dt><dd class="mono"><?= Format::e($run['file_path']) ?></dd></div>
                    <div class="cluster"><dt class="text-muted">SHA-256</dt><dd class="mono text-xs"><?= Format::e($run['file_sha256']) ?></dd></div>
                    <div class="cluster"><dt class="text-muted">Queued / started / finished</dt><dd class="nums"><?= Format::e($run['queued_at']) ?> / <?= Format::e($run['started_at'] ?? '—') ?> / <?= Format::e($run['finished_at'] ?? '—') ?></dd></div>
                    <?php if (!empty($columns['offenses'])): ?>
                    <div class="cluster"><dt class="text-muted">Offense columns</dt><dd><?= Format::e(implode(', ', array_map(static fn ($k, $h) => ($offenseLabels[$k] ?? $k) . ' ← ' . $h, array_keys($columns['offenses']), $columns['offenses']))) ?></dd></div>
                    <?php endif; ?>
                    <?php if (!empty($columns['missing_offenses'])): ?>
                    <div class="cluster"><dt class="text-muted">Not in this file</dt><dd><?= Format::e(implode(', ', array_map(static fn ($k) => $offenseLabels[$k] ?? $k, $columns['missing_offenses']))) ?> (left as previously imported)</dd></div>
                    <?php endif; ?>
                    <?php if (!empty($columns['fields'])): ?>
                    <div class="cluster"><dt class="text-muted">Columns</dt><dd><?= Format::e(implode(', ', array_map(static fn ($k, $h) => $k . ' ← ' . $h, array_keys($columns['fields']), $columns['fields']))) ?></dd></div>
                    <?php endif; ?>
                </dl>
            </div>
        </section>

        <section class="card" aria-labelledby="errors-title">
            <div class="card-header"><h2 id="errors-title" class="card-title">Row errors<?= (int) $run['error_count'] > count($errors) ? ' (first ' . count($errors) . ' of ' . number_format((int) $run['error_count']) . ')' : '' ?></h2></div>
            <div class="card-body">
                <?php if ($errors === []): ?>
                <p class="text-muted">None.</p>
                <?php else: ?>
                <ol class="stack stack-1 text-sm mono">
                    <?php foreach ($errors as $error): ?>
                    <li><?= Format::e($error) ?></li>
                    <?php endforeach; ?>
                </ol>
                <?php endif; ?>
            </div>
        </section>
<?php require __DIR__ . '/../_bottom.php'; ?>

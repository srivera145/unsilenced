<?php
use EchoDial\Deck\Deck;
use Keel\App\Support\Format;

$adminSection = 'imports';
require __DIR__ . '/../_top.php';
$statusBadge = ['complete' => 'badge-good', 'failed' => 'badge-bad', 'running' => 'badge-brand', 'queued' => ''];
?>
        <div class="stack stack-2">
            <h1 class="h2">Imports</h1>
            <p class="text-muted">Every <code>import:schools</code> and <code>import:clery</code> run. Imports start from the command line and are processed by the queue worker.</p>
        </div>

        <section class="card" aria-labelledby="runs-title">
            <div class="card-header"><h2 id="runs-title" class="card-title"><?= Format::plural(count($runs), 'run') ?></h2></div>
            <div class="table-wrap" tabindex="0" role="region" aria-labelledby="runs-title">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">Run</th>
                            <th scope="col">File</th>
                            <th scope="col">Years</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-end">Read</th>
                            <th scope="col" class="text-end">Added</th>
                            <th scope="col" class="text-end">Updated</th>
                            <th scope="col" class="text-end">Unchanged</th>
                            <th scope="col" class="text-end">Errors</th>
                            <th scope="col">Finished</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($runs === []): ?>
                        <tr>
                            <td colspan="10" class="table-empty">
                                <div class="empty">
                                    <span class="empty-art"><?= Deck::icon('upload') ?></span>
                                    <p class="empty-title">No imports yet</p>
                                    <p><code>php database/console.php import:schools storage/imports/hd2025.csv</code></p>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($runs as $run): ?>
                        <tr>
                            <td class="nums"><a href="/admin/imports/<?= (int) $run['id'] ?>">#<?= (int) $run['id'] ?></a> <span class="text-muted text-xs"><?= Format::e($run['kind']) ?></span></td>
                            <td><?= Format::e($run['file_name']) ?><?= $run['location'] ? ' <span class="text-muted text-xs">' . Format::e($run['location']) . '</span>' : '' ?></td>
                            <td class="nums"><?= Format::e(\Keel\App\Models\ImportRun::yearsLabel($run)) ?></td>
                            <td><span class="badge <?= $statusBadge[$run['status']] ?? '' ?>"><?= Format::e($run['status']) ?></span></td>
                            <td class="text-end nums"><?= Format::number((int) $run['rows_read']) ?></td>
                            <td class="text-end nums"><?= Format::number((int) $run['rows_added']) ?></td>
                            <td class="text-end nums"><?= Format::number((int) $run['rows_updated']) ?></td>
                            <td class="text-end nums"><?= Format::number((int) $run['rows_unchanged']) ?></td>
                            <td class="text-end nums"><?= Format::number((int) $run['error_count']) ?></td>
                            <td class="nums text-sm"><?= Format::e($run['finished_at'] ?? '') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
<?php require __DIR__ . '/../_bottom.php'; ?>

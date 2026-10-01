<?php
use EchoDial\Deck\Deck;
use Keel\App\Support\Config;
use Keel\App\Support\Format;

$adminSection = 'accountability';
require __DIR__ . '/../_top.php';
$types = (array) Config::get('accountability.types', []);
$statuses = (array) Config::get('accountability.statuses', []);
?>
        <div class="bar wrap">
            <div class="stack stack-1">
                <h1 class="h2">Accountability records</h1>
                <?php if ($school !== null): ?>
                <p class="text-muted">For <a href="/admin/schools/<?= (int) $school['id'] ?>/edit"><?= Format::e($school['name']) ?></a> · <a href="/admin/accountability">Show all</a></p>
                <?php endif; ?>
            </div>
            <a class="btn btn-primary push" href="/admin/accountability/new<?= $school !== null ? '?school_id=' . (int) $school['id'] : '' ?>"><?= Deck::icon('plus') ?> New record</a>
        </div>

        <section class="card" aria-labelledby="items-title">
            <div class="card-header"><h2 id="items-title" class="card-title"><?= Format::plural(count($items), 'record') ?></h2></div>
            <div class="table-wrap" tabindex="0" role="region" aria-labelledby="items-title">
                <table class="table table-stack">
                    <thead>
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">School</th>
                            <th scope="col">Type</th>
                            <th scope="col">Status</th>
                            <th scope="col">Visibility</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($items === []): ?>
                        <tr>
                            <td colspan="5" class="table-empty">
                                <div class="empty">
                                    <span class="empty-art"><?= Deck::icon('file') ?></span>
                                    <p class="empty-title">No records yet</p>
                                    <p>Add OCR investigations, lawsuits, state reviews and news coverage from a school's page or with New record.</p>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td data-label="Date" class="nums"><a href="/admin/accountability/<?= (int) $item['id'] ?>/edit"><?= Format::e($item['item_date']) ?></a></td>
                            <td data-label="School"><?= Format::e($item['school_name']) ?></td>
                            <td data-label="Type"><?= Format::e($types[$item['type']] ?? $item['type']) ?></td>
                            <td data-label="Status"><?= Format::e($statuses[$item['status']] ?? $item['status']) ?></td>
                            <td data-label="Visibility">
                                <span class="badge <?= (int) $item['is_published'] === 1 ? 'badge-good' : '' ?>"><?= (int) $item['is_published'] === 1 ? 'Published' : 'Draft' ?></span>
                                <?php if (!empty($item['name_check_confirmed_at'])): ?>
                                <span class="badge badge-warn" title="An admin confirmed the flagged phrases are not names">Name check confirmed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
<?php require __DIR__ . '/../_bottom.php'; ?>

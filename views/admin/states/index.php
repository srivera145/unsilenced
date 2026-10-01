<?php
use EchoDial\Deck\Deck;
use Keel\App\Models\StatePage;
use Keel\App\Support\Format;

$adminSection = 'states';
require __DIR__ . '/../_top.php';
$published = count(array_filter($pages, [StatePage::class, 'isPublished']));
?>
        <div class="bar wrap">
            <div class="stack stack-1">
                <h1 class="h2">State pages</h1>
                <p class="text-muted"><?= (int) $published ?> of <?= count($pages) ?> published. A page shows legal information only once it is published, and it can only be published after legal review is recorded.</p>
            </div>
            <a class="btn push" href="/admin/states/new"><?= Deck::icon('plus') ?> New state page</a>
        </div>

        <section class="card" aria-label="State pages">
            <div class="table-wrap" tabindex="0" role="region" aria-label="State pages">
                <table class="table table-stack">
                    <thead>
                        <tr>
                            <th scope="col">State</th>
                            <th scope="col">Code</th>
                            <th scope="col">Status</th>
                            <th scope="col">Legal review</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pages as $page): ?>
                        <tr>
                            <td data-label="State" class="fw-medium"><a href="/admin/states/<?= (int) $page['id'] ?>/edit"><?= Format::e($page['name']) ?></a></td>
                            <td data-label="Code"><?= Format::e($page['code']) ?></td>
                            <td data-label="Status"><span class="badge <?= StatePage::isPublished($page) ? 'badge-good' : 'badge-warn' ?>"><?= Format::e(StatePage::STATUSES[$page['status']] ?? $page['status']) ?></span></td>
                            <td data-label="Legal review" class="text-sm"><?= $page['legal_reviewed_on'] ? Format::e($page['legal_reviewed_by'] . ', ' . Format::date($page['legal_reviewed_on'])) : '<span class="text-muted">Not recorded</span>' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
<?php require __DIR__ . '/../_bottom.php'; ?>

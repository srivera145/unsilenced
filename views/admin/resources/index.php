<?php
use EchoDial\Deck\Deck;
use Keel\App\Support\Format;

$adminSection = 'resources';
require __DIR__ . '/../_top.php';
?>
        <div class="bar wrap">
            <h1 class="h2">Resource pages</h1>
            <a class="btn btn-primary push" href="/admin/resources/new"><?= Deck::icon('plus') ?> New page</a>
        </div>

        <section class="card" aria-labelledby="pages-title">
            <div class="card-header"><h2 id="pages-title" class="card-title"><?= Format::plural(count($pages), 'page') ?></h2></div>
            <div class="table-wrap" tabindex="0" role="region" aria-labelledby="pages-title">
                <table class="table table-stack">
                    <thead>
                        <tr>
                            <th scope="col">Title</th>
                            <th scope="col">URL</th>
                            <th scope="col" class="text-end">Order</th>
                            <th scope="col">Visibility</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pages as $page): ?>
                        <tr>
                            <td data-label="Title" class="fw-medium"><a href="/admin/resources/<?= (int) $page['id'] ?>/edit"><?= Format::e($page['title']) ?></a></td>
                            <td data-label="URL"><code>/resources/<?= Format::e($page['slug']) ?></code></td>
                            <td data-label="Order" class="text-end nums"><?= (int) $page['sort_order'] ?></td>
                            <td data-label="Visibility"><span class="badge <?= (int) $page['is_published'] === 1 ? 'badge-good' : '' ?>"><?= (int) $page['is_published'] === 1 ? 'Published' : 'Draft' ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
<?php require __DIR__ . '/../_bottom.php'; ?>

<?php
use EchoDial\Deck\Deck;
use Keel\App\Models\School;
use Keel\App\Support\Format;

$adminSection = 'schools';
require __DIR__ . '/../_top.php';
$rows = $results['rows'];
$total = (int) $results['total'];
$pages = max(1, (int) ceil($total / $perPage));
$pageUrl = static fn (int $p): string => '/admin/schools?' . http_build_query(array_filter(['q' => $query, 'page' => $p > 1 ? $p : null]));
?>
        <div class="bar wrap">
            <h1 class="h2">Schools</h1>
            <a class="btn btn-primary push" href="/admin/schools/new"><?= Deck::icon('plus') ?> New school</a>
        </div>

        <form class="cluster" method="get" action="/admin/schools" role="search">
            <label class="sr-only" for="admin-school-q">Search schools</label>
            <input class="input" style="max-inline-size: 24rem" type="search" id="admin-school-q" name="q" value="<?= Format::e($query) ?>" placeholder="Name or city">
            <button class="btn" type="submit">Search</button>
        </form>

        <section class="card" aria-labelledby="schools-title">
            <div class="card-header"><h2 id="schools-title" class="card-title"><?= Format::plural($total, 'school') ?></h2></div>
            <div class="table-wrap" tabindex="0" role="region" aria-labelledby="schools-title">
                <table class="table table-stack">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Location</th>
                            <th scope="col">UNITID</th>
                            <th scope="col" class="text-end">Enrollment</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="5" class="table-empty">
                                <div class="empty">
                                    <span class="empty-art"><?= Deck::icon('search') ?></span>
                                    <p class="empty-title">No schools<?= $query !== '' ? ' match' : ' yet' ?></p>
                                    <p>Import the IPEDS directory file, or add a school by hand.</p>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $row): ?>
                        <tr>
                            <td data-label="Name" class="fw-medium"><a href="/admin/schools/<?= (int) $row['id'] ?>/edit"><?= Format::e($row['name']) ?></a></td>
                            <td data-label="Location"><?= Format::e(trim(($row['city'] ?? '') . ', ' . $row['state'], ', ')) ?></td>
                            <td data-label="UNITID" class="nums"><?= (int) $row['unitid'] ?></td>
                            <td data-label="Enrollment" class="text-end nums"><?= Format::number($row['enrollment'] !== null ? (int) $row['enrollment'] : null) ?></td>
                            <td data-label="Actions"><a href="<?= Format::e(School::path($row)) ?>">Public page</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php if ($pages > 1): ?>
        <nav class="pagination" aria-label="Pages">
            <?php if ($page > 1): ?><a href="<?= Format::e($pageUrl($page - 1)) ?>">Previous</a><?php endif; ?>
            <span aria-current="page">Page <?= (int) $page ?> of <?= (int) $pages ?></span>
            <?php if ($page < $pages): ?><a href="<?= Format::e($pageUrl($page + 1)) ?>">Next</a><?php endif; ?>
        </nav>
        <?php endif; ?>
<?php require __DIR__ . '/../_bottom.php'; ?>

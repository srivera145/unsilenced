<?php
/**
 * /my-report/edit: her answers and account, all on one page. Saving puts the
 * report back in the queue (or keeps it private) and clears any published
 * version an admin had prepared, since it no longer matches.
 */
use EchoDial\Deck\Deck;
use Keel\App\Support\Asset;
use Keel\App\Support\Format;
use Keel\Core\Csrf;

$quickExitSignOut = '/my-report/sign-out';
$sections = [
    'school' => 'The school',
    'when' => 'When and where',
    'who' => 'Who',
    'reporting' => 'Reporting',
    'account' => 'What happened',
    'consent' => 'Publishing',
];

require __DIR__ . '/_top.php';
?>
        <header class="stack stack-2">
            <nav aria-label="Breadcrumb">
                <ol class="breadcrumb">
                    <li><a href="/my-report">Your report</a></li>
                    <li><span aria-current="page">Edit</span></li>
                </ol>
            </nav>
            <h1 class="h2">Edit your report</h1>
            <?php if (in_array($report['status'], ['submitted', 'in_review', 'changes_requested'], true)): ?>
            <p class="text-muted">When you save, your report goes back for review.</p>
            <?php endif; ?>
        </header>

        <?php if ($errors !== []): ?>
        <div class="alert alert-bad" role="alert">
            <?= Deck::icon('alert-circle') ?>
            <p>Some answers need another look. They are marked below.</p>
        </div>
        <?php endif; ?>

        <form id="report-form" class="stack stack-6" method="POST" action="/my-report/edit" autocomplete="off" novalidate data-schools-url="/my-report/schools">
            <?= Csrf::field() ?>
            <?php foreach ($sections as $partial => $heading): ?>
            <section class="card" aria-labelledby="edit-<?= $partial ?>">
                <div class="card-body stack stack-4">
                    <h2 class="h5" id="edit-<?= $partial ?>"><?= Format::e($heading) ?></h2>
                    <?php require __DIR__ . '/fields/' . $partial . '.php'; ?>
                </div>
            </section>
            <?php endforeach; ?>

            <div class="form-actions">
                <a class="btn" href="/my-report">Cancel</a>
                <button class="btn btn-primary push" type="submit">Save changes</button>
            </div>
        </form>

        <script src="<?= Format::e(Asset::url('/js/report-form.js')) ?>" defer></script>
<?php require __DIR__ . '/_bottom.php'; ?>

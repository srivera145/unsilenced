<?php
/**
 * One evidence file, from its metadata-free copy. Opening this page (text)
 * or loading the file itself (everything else) is logged as an evidence view
 * and marks the file viewed.
 */
use EchoDial\Deck\Deck;
use Keel\App\Support\Config;
use Keel\App\Support\Format;
use Keel\Core\Csrf;

$adminSection = 'reports';
require __DIR__ . '/../_top.php';

$types = (array) Config::get('evidence.types', []);
$fileUrl = '/admin/reports/' . (int) $report['id'] . '/evidence/' . (int) $evidence['id'] . '/file';
$orientation = (int) ($evidence['orientation'] ?? 1);
?>
        <header class="stack stack-2">
            <nav aria-label="Breadcrumb">
                <ol class="breadcrumb">
                    <li><a href="/admin/reports">Survivor reports</a></li>
                    <li><a href="/admin/reports/<?= (int) $report['id'] ?>#evidence">#<?= (int) $report['id'] ?></a></li>
                    <li><span aria-current="page">Evidence</span></li>
                </ol>
            </nav>
            <h1 class="h3"><?= Format::e($types[$evidence['kind']]['label'] ?? $evidence['kind']) ?>, added <?= Format::e($evidence['uploaded_at']) ?> UTC</h1>
            <p class="text-sm text-muted">SHA-256 of the original <code class="hash"><?= Format::e($evidence['sha256']) ?></code></p>
        </header>

        <?php if ($evidence['admin_copy'] === 'partial'): ?>
        <div class="alert alert-warn">
            <?= Deck::icon('alert-triangle') ?>
            <p>Some metadata may remain in this copy: the file stores part of it in a way that cannot be removed without rewriting it. Treat any author or creation details you see as private.</p>
        </div>
        <?php endif; ?>

        <section class="card evidence-view">
            <div class="card-body stack stack-3">
                <?php if ($evidence['kind'] === 'jpeg' || $evidence['kind'] === 'png'): ?>
                <div class="evidence-frame">
                    <img class="evidence-image orientation-<?= $orientation ?>" src="<?= Format::e($fileUrl) ?>" alt="Evidence file from report <?= (int) $report['id'] ?>">
                </div>
                <?php elseif ($evidence['kind'] === 'm4a' || $evidence['kind'] === 'mp3'): ?>
                <audio controls preload="metadata" src="<?= Format::e($fileUrl) ?>">Your browser cannot play this audio. <a href="<?= Format::e($fileUrl) ?>">Open it</a>.</audio>
                <?php elseif ($evidence['kind'] === 'text'): ?>
                <pre class="evidence-text"><?= Format::e((string) $text) ?></pre>
                <?php elseif ($evidence['kind'] === 'pdf'): ?>
                <p><a class="btn btn-primary" href="<?= Format::e($fileUrl) ?>"><?= Deck::icon('file') ?> Open the PDF</a></p>
                <p class="text-sm text-muted">It opens in this tab, from the copy with author and creation details removed.</p>
                <?php else: ?>
                <p><a class="btn btn-primary" href="<?= Format::e($fileUrl) ?>"><?= Deck::icon('download') ?> Download the copy</a></p>
                <p class="text-sm text-muted">Most browsers cannot show HEIC photos. The copy has its location and camera details removed. Delete it from your device when you are done.</p>
                <?php endif; ?>
            </div>
        </section>

        <div class="cluster">
            <a class="btn" href="/admin/reports/<?= (int) $report['id'] ?>#evidence"><?= Deck::icon('arrow-left') ?> Back to the report</a>
            <form class="push" method="POST" action="/admin/reports/<?= (int) $report['id'] ?>/evidence/<?= (int) $evidence['id'] ?>/quarantine" data-confirm="confirm-quarantine">
                <?= Csrf::field() ?>
                <button class="btn btn-danger" type="submit"><?= Deck::icon('shield') ?> Report illegal content</button>
            </form>
        </div>
        <dialog class="modal" id="confirm-quarantine" aria-labelledby="confirm-quarantine-title">
            <div class="modal-header"><h2 class="modal-title" id="confirm-quarantine-title">Quarantine this file?</h2></div>
            <div class="modal-body"><p>No one will be able to view or download it again: not you, not her, not anyone with a share link. It stays encrypted and is kept. The procedure to follow opens next.</p></div>
            <div class="modal-footer">
                <button class="btn" type="button" data-modal-close>Cancel</button>
                <button class="btn btn-danger" type="button" data-confirm-submit="">Quarantine</button>
            </div>
        </dialog>
<?php require __DIR__ . '/../_bottom.php'; ?>

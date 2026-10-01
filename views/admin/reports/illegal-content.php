<?php
/** docs/ILLEGAL-CONTENT.md, for the admin who has just quarantined a file. */
$adminSection = 'reports';
require __DIR__ . '/../_top.php';
?>
        <article class="prose">
            <?php if ($html === ''): ?>
            <h1 class="h2">Illegal content</h1>
            <p>docs/ILLEGAL-CONTENT.md is missing. Do not open, download, copy or forward the file. Contact the site's lawyer.</p>
            <?php else: ?>
            <?= $html ?>
            <?php endif; ?>
        </article>
        <p><a class="btn" href="/admin/reports">Back to the queue</a></p>
<?php require __DIR__ . '/../_bottom.php'; ?>

<?php
use EchoDial\Deck\Deck;

require __DIR__ . '/_top.php';
?>
        <section class="card">
            <div class="empty">
                <span class="empty-art"><?= Deck::icon('alert-triangle', 'icon icon-lg') ?></span>
                <h1 class="empty-title">That was too much to send at once</h1>
                <p>The files together were larger than our server accepts in one go, so nothing was received and nothing was saved. Please start again and add fewer files. After you send your report, you can add the rest from your page.</p>
                <div class="cluster cluster-center">
                    <a href="/submit" class="btn btn-primary">Start again</a>
                </div>
            </div>
        </section>

        <?php require __DIR__ . '/../partials/help-panel.php'; ?>
<?php require __DIR__ . '/_bottom.php'; ?>

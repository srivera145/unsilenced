<?php
use EchoDial\Deck\Deck;

require __DIR__ . '/_top.php';
?>
        <section class="card">
            <div class="empty">
                <span class="empty-art"><?= Deck::icon('check-circle', 'icon icon-lg') ?></span>
                <h1 class="empty-title">Everything has been deleted</h1>
                <p>Your report, your account, your files and your share links are gone, and your report is no longer counted anywhere on the site. Your key no longer opens anything.</p>
                <p>If you want to, you can send a new report at any time.</p>
                <div class="cluster cluster-center">
                    <a href="/" class="btn btn-primary">Home</a>
                    <a href="/resources/get-help" class="btn">Get help</a>
                </div>
            </div>
        </section>

        <?php require __DIR__ . '/../partials/help-panel.php'; ?>
<?php require __DIR__ . '/_bottom.php'; ?>

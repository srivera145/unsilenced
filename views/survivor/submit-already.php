<?php
use EchoDial\Deck\Deck;

require __DIR__ . '/_top.php';
?>
        <section class="card">
            <div class="empty">
                <span class="empty-art"><?= Deck::icon('check-circle', 'icon icon-lg') ?></span>
                <h1 class="empty-title">Your report was already sent</h1>
                <p>This form was sent before, so we did not send it again. Your report is safe. To see it, go to your page and enter your six-word key.</p>
                <div class="cluster cluster-center">
                    <a href="/my-report" class="btn btn-primary">Go to your page</a>
                    <a href="/" class="btn">Home</a>
                </div>
            </div>
        </section>

        <?php require __DIR__ . '/../partials/help-panel.php'; ?>
<?php require __DIR__ . '/_bottom.php'; ?>

<?php
use EchoDial\Deck\Deck;

require __DIR__ . '/_top.php';
?>
        <section class="card">
            <div class="empty">
                <span class="empty-art"><?= Deck::icon('clock', 'icon icon-lg') ?></span>
                <h1 class="empty-title">Coming soon</h1>
                <p>This part of Unsilenced is not open yet. When it opens, you will be able to tell us, anonymously, what happened at your school and how the school responded.</p>
                <div class="cluster cluster-center">
                    <a href="/" class="btn btn-primary">Home</a>
                    <a href="/resources/get-help" class="btn">Get help now</a>
                </div>
            </div>
        </section>

        <?php require __DIR__ . '/../partials/help-panel.php'; ?>
<?php require __DIR__ . '/_bottom.php'; ?>

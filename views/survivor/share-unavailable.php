<?php
use EchoDial\Deck\Deck;

require __DIR__ . '/_top.php';
?>
        <section class="card">
            <div class="empty">
                <span class="empty-art"><?= Deck::icon('lock', 'icon icon-lg') ?></span>
                <h1 class="empty-title">This link is not available</h1>
                <p>It may have expired or been turned off, or it was not copied in full. Ask the person who sent it for a new one.</p>
                <div class="cluster cluster-center">
                    <a href="/share" class="btn">Try another link</a>
                </div>
            </div>
        </section>
<?php require __DIR__ . '/_bottom.php'; ?>

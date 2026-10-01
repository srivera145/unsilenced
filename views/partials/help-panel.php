<?php
/**
 * The hotline card. Its content comes from config, not the database, so no
 * admin edit can remove or break it.
 */
use EchoDial\Deck\Deck;
use Keel\App\Support\Config;

$help = (array) Config::get('help', []);
?>
<section class="card" aria-labelledby="help-panel-title">
    <div class="card-body stack stack-3">
        <h2 id="help-panel-title" class="h5">Talk to someone now</h2>
        <p class="text-sm"><?= htmlspecialchars((string) $help['hotline_name']) ?>. Free, confidential, 24 hours a day.</p>
        <p><a class="hotline-number" href="tel:<?= htmlspecialchars((string) $help['hotline_tel'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $help['hotline_display']) ?></a></p>
        <p class="text-sm">Or chat online at <a href="<?= htmlspecialchars((string) $help['chat_url'], ENT_QUOTES, 'UTF-8') ?>" rel="noopener noreferrer"><?= htmlspecialchars((string) $help['chat_label']) ?></a>.</p>
        <div class="alert alert-warn">
            <?= Deck::icon('alert-triangle') ?>
            <p>If you are in immediate danger, call <a href="tel:<?= htmlspecialchars((string) $help['emergency_number'], ENT_QUOTES, 'UTF-8') ?>"><strong><?= htmlspecialchars((string) $help['emergency_number']) ?></strong></a>.</p>
        </div>
    </div>
</section>

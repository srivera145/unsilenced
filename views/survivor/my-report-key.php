<?php
use EchoDial\Deck\Deck;
use Keel\App\Support\Format;
use Keel\Core\Csrf;

require __DIR__ . '/_top.php';
?>
        <section class="card narrow-card" aria-labelledby="open-title">
            <div class="card-body stack stack-4">
                <h1 class="h3" id="open-title">Open your report</h1>
                <p>Enter the six-word key you were given when you sent your report.</p>

                <?php if ($notice !== null): ?>
                <div class="alert alert-info" role="status">
                    <?= Deck::icon('info') ?>
                    <p><?= Format::e($notice) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($error !== null): ?>
                <div class="alert alert-bad" role="alert">
                    <?= Deck::icon('alert-circle') ?>
                    <div class="stack stack-1">
                        <p><?= Format::e($error) ?></p>
                        <?php if ($unknownWords !== []): ?>
                        <p class="alert-body">Our keys never use <?= Format::e(Format::list(array_map(static fn (string $w): string => '"' . $w . '"', $unknownWords))) ?>. Check the spelling of <?= count($unknownWords) === 1 ? 'that word' : 'those words' ?>.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <form class="stack stack-3" method="POST" action="/my-report" autocomplete="off">
                    <?= Csrf::field() ?>
                    <div class="field">
                        <label class="label" for="case_key">Your key</label>
                        <input class="input case-key-input" id="case_key" name="case_key" required autocomplete="off" autocapitalize="none" spellcheck="false" aria-describedby="case_key-help"<?= $error !== null ? ' aria-invalid="true"' : '' ?>>
                        <p class="help" id="case_key-help">Six words, with spaces between them. Capital letters do not matter.</p>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">Open</button>
                    </div>
                </form>

                <p class="text-sm text-muted">Lost your key? We cannot recover it, because we never stored it. You can send a new report; nothing connects the two.</p>
            </div>
        </section>

        <?php require __DIR__ . '/../partials/help-panel.php'; ?>
<?php require __DIR__ . '/_bottom.php'; ?>

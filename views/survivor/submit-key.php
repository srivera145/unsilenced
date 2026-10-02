<?php
/**
 * The case key, shown once, straight in the response to the submit (never
 * stored, never in a redirect or the session). public_html/js/case-key.js
 * asks them to type the last two words back, then takes the key off the page.
 * The page is no-store, and reloading it re-sends a spent form, which only
 * says the report was already sent: the key cannot be shown again.
 */
use EchoDial\Deck\Deck;
use Keel\App\Support\Asset;
use Keel\App\Support\Format;

$words = explode(' ', $caseKey);

require __DIR__ . '/_top.php';
?>
        <div class="alert alert-good" role="status">
            <?= Deck::icon('check-circle') ?>
            <p><strong>Your report has been sent.</strong> Thank you for trusting us with it.</p>
        </div>

        <section class="card" id="key-panel" aria-labelledby="key-title">
            <div class="card-body stack stack-4">
                <h1 class="h3" id="key-title" tabindex="-1">Your key</h1>
                <p>These six words are the only way back to your report: to see its status, read a note from us, add evidence, share files or withdraw it.</p>
                <div class="alert alert-warn">
                    <?= Deck::icon('lock') ?>
                    <p><strong>Save this somewhere safe. We cannot recover it.</strong> We never stored it, so no one at Unsilenced can look it up or reset it.</p>
                </div>
                <ol class="case-key" id="case-key" aria-label="Your six-word key">
                    <?php foreach ($words as $word): ?>
                    <li><?= Format::e($word) ?></li>
                    <?php endforeach; ?>
                </ol>
                <p class="text-sm">Write it on paper, or keep it in a password manager. Think about who else can see where you keep it, such as the notes or photos on a shared phone.</p>

                <div class="stack stack-3 key-check" id="key-check" hidden>
                    <h2 class="h5">Check you have it</h2>
                    <p>Type the last two words of your key, without looking back at it if you can.</p>
                    <div class="field-row">
                        <div class="field">
                            <label class="label" for="key-word-5">Word 5</label>
                            <input class="input" id="key-word-5" autocomplete="off" autocapitalize="none" spellcheck="false">
                        </div>
                        <div class="field">
                            <label class="label" for="key-word-6">Word 6</label>
                            <input class="input" id="key-word-6" autocomplete="off" autocapitalize="none" spellcheck="false">
                        </div>
                    </div>
                    <p class="error" id="key-check-error" role="alert" hidden></p>
                    <div class="form-actions">
                        <button type="button" class="btn btn-primary" id="key-check-button">I have saved my key</button>
                    </div>
                </div>
                <noscript><p class="fw-semi">Make sure you have saved your key before you leave this page.</p></noscript>
            </div>
        </section>

        <section class="card" id="key-done" aria-labelledby="done-title">
            <div class="card-body stack stack-3">
                <h2 class="h4" id="done-title" tabindex="-1">What happens next</h2>
                <?php if ($consent === 'private'): ?>
                <p>Your report is kept private. No one at Unsilenced reads it, and it is not counted or published. It is there for your own record, and for sharing evidence with people you choose.</p>
                <?php else: ?>
                <p>A person on our team will read your report. Nothing is published until they approve it, and only what you agreed to. If we need you to change something, we will leave a note on your page.</p>
                <?php endif; ?>
                <?php if ($fileCount > 0): ?>
                <p>Your <?= $fileCount === 1 ? 'file is' : Format::plural($fileCount, 'file') . ' are' ?> encrypted and stored. You can add more, share them with someone you choose, or delete them from your page.</p>
                <?php endif; ?>
                <p>You can come back to your page any time with your key, and withdraw your report there whenever you want. Withdrawing deletes everything.</p>
                <div class="cluster">
                    <a class="btn btn-primary" href="/my-report">Go to your page</a>
                    <a class="btn" href="/">Home</a>
                </div>
            </div>
        </section>

        <?php require __DIR__ . '/../partials/help-panel.php'; ?>

        <script src="<?= Format::e(Asset::url('/js/case-key.js')) ?>" defer></script>
<?php require __DIR__ . '/_bottom.php'; ?>

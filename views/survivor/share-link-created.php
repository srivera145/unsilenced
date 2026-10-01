<?php
/**
 * A new share link, shown once. Only a hash of its token is stored, so this
 * page is the only place the full link ever appears.
 */
use EchoDial\Deck\Deck;
use Keel\App\Support\Asset;
use Keel\App\Support\Format;

$quickExitSignOut = '/my-report/sign-out';

require __DIR__ . '/_top.php';
?>
        <section class="card" aria-labelledby="link-title">
            <div class="card-body stack stack-4">
                <h1 class="h3" id="link-title">Your share link is ready</h1>
                <div class="alert alert-warn">
                    <?= Deck::icon('alert-triangle') ?>
                    <p><strong>Copy it now.</strong> This is the only time we show it. If you lose it, turn it off and make a new one.</p>
                </div>
                <div class="field">
                    <label class="label" for="share-url">The link</label>
                    <input class="input mono share-url" id="share-url" type="text" readonly value="<?= Format::e($url) ?>" data-select-on-focus>
                </div>
                <dl class="review-list">
                    <?php if ($label !== ''): ?><dt>For</dt><dd><?= Format::e($label) ?></dd><?php endif; ?>
                    <dt>Files</dt><dd><?= Format::plural($fileCount, 'file') ?></dd>
                    <dt>Works for</dt><dd><?= Format::e($expiry) ?></dd>
                    <dt>Passcode</dt><dd><?= $hasPasscode ? 'Yes. Tell it to them separately, for example by phone.' : 'No. Anyone with the link can open it until it expires.' ?></dd>
                </dl>
                <p class="text-sm text-muted">The person you send it to sees the files, each one's fingerprint and the time it was added, and can download them, together with a list of fingerprints for checking. You will see when the link was last opened, and you can turn it off at any time.</p>
                <p><a class="btn btn-primary" href="/my-report#share-links">Back to your report</a></p>
            </div>
        </section>
        <script src="<?= Format::e(Asset::url('/js/survivor.js')) ?>" defer></script>
<?php require __DIR__ . '/_bottom.php'; ?>

<?php
/**
 * /share. With JavaScript, public_html/js/share.js reads the token from the
 * URL fragment, takes it out of the address bar and history, and sends this
 * form. Without it, the person pastes the link into the box. When the link
 * has a passcode, the form is shown again asking for it.
 */
use EchoDial\Deck\Deck;
use Keel\App\Support\Asset;
use Keel\App\Support\Format;
use Keel\Core\Csrf;

$quickExitSignOut = '/share/close';

require __DIR__ . '/_top.php';
?>
        <section class="card narrow-card" aria-labelledby="share-title">
            <div class="card-body stack stack-4">
                <h1 class="h3" id="share-title">Shared files</h1>
                <p>Someone shared files with you through Unsilenced. They are encrypted on our server and only open with the link you were sent<?= $needsPasscode ? ' and its passcode' : '' ?>.</p>

                <?php if ($error !== null): ?>
                <div class="alert alert-bad" role="alert">
                    <?= Deck::icon('alert-circle') ?>
                    <p><?= Format::e($error) ?></p>
                </div>
                <?php endif; ?>

                <p class="text-sm" id="share-opening" role="status" hidden>Opening the files…</p>

                <form class="stack stack-3" method="POST" action="/share/open" id="share-open-form" autocomplete="off">
                    <?= Csrf::field() ?>
                    <?php if ($needsPasscode): ?>
                    <input type="hidden" name="token" value="<?= Format::e($token) ?>">
                    <div class="field">
                        <label class="label" for="passcode">Passcode</label>
                        <input class="input input-narrow" id="passcode" name="passcode" type="password" autocomplete="off" required aria-describedby="passcode-help">
                        <p class="help" id="passcode-help">The person who sent you the link should have told you this separately.</p>
                    </div>
                    <?php else: ?>
                    <div class="field" id="share-paste">
                        <label class="label" for="token">The link you were sent</label>
                        <input class="input" id="token" name="token" type="text" autocomplete="off" spellcheck="false" required aria-describedby="token-help">
                        <p class="help" id="token-help">Paste the whole link, including everything after the #.</p>
                    </div>
                    <?php endif; ?>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">Open</button>
                    </div>
                </form>
            </div>
        </section>
        <script src="<?= Format::e(Asset::url('/js/share.js')) ?>" defer></script>
<?php require __DIR__ . '/_bottom.php'; ?>

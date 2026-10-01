<?php
/** A fresh emailed code before viewing evidence (FreshOtpMiddleware). */
use EchoDial\Deck\Deck;
use Keel\App\Support\Config;
use Keel\App\Support\Format;
use Keel\Core\Csrf;

$adminSection = 'reports';
require __DIR__ . '/_top.php';
$minutes = (int) Config::get('survivor_reports.admin_otp_fresh_minutes', 15);
?>
        <section class="card narrow-card" aria-labelledby="verify-title">
            <div class="card-body stack stack-4">
                <h1 class="h3" id="verify-title">Confirm it is you</h1>
                <p>Evidence can only be viewed with a code from your email entered in the last <?= $minutes ?> minutes, so a session left open cannot be used to look at it.</p>

                <?php if ($error !== null): ?>
                <div class="alert alert-bad" role="alert">
                    <?= Deck::icon('alert-circle') ?>
                    <p><?= Format::e($error) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($sent): ?>
                <p>We sent a 6-digit code to your email. It expires in 10 minutes.</p>
                <form class="stack stack-3" method="POST" action="/admin/verify" autocomplete="off">
                    <?= Csrf::field() ?>
                    <div class="field">
                        <label class="label" for="code">Code</label>
                        <input class="input otp-input input-narrow" id="code" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" required>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">Continue</button>
                    </div>
                </form>
                <?php endif; ?>

                <form method="POST" action="/admin/verify/send">
                    <?= Csrf::field() ?>
                    <button class="btn<?= $sent ? '' : ' btn-primary' ?>" type="submit"><?= Deck::icon('mail') ?> <?= $sent ? 'Send a new code' : 'Email me a code' ?></button>
                </form>
            </div>
        </section>
<?php require __DIR__ . '/_bottom.php'; ?>

<?php
/**
 * Withdrawal, confirmed twice: this page explains (first), then asks again
 * with a box to tick (final). The final form carries a one-time token issued
 * by the first confirmation.
 */
use EchoDial\Deck\Deck;
use Keel\App\Support\Format;
use Keel\Core\Csrf;

$quickExitSignOut = '/my-report/sign-out';
$error = $error ?? null;

require __DIR__ . '/_top.php';
?>
        <section class="card" aria-labelledby="withdraw-title">
            <div class="card-body stack stack-4">
                <h1 class="h3" id="withdraw-title" tabindex="-1"><?= $final ? 'Are you sure?' : 'Withdraw your report' ?></h1>

                <p>Withdrawing permanently deletes:</p>
                <ul class="cross-list">
                    <li>your report and your account of what happened;</li>
                    <li><?= $fileCount === 0 ? 'your evidence (you have none)' : 'your ' . Format::plural($fileCount, 'evidence file') ?>, and every share link, which stop working at once;</li>
                    <li>your email address, if you gave one;</li>
                    <li>your key: it will not open anything again.</li>
                </ul>
                <p>Your report is removed from every figure on the site straight away<?= $report['status'] === 'approved' && $report['consent'] === 'stats_and_account' ? ', and your published account disappears from the school\'s page' : '' ?>.</p>
                <?php if ($hasQuarantined): ?>
                <p class="text-sm text-muted">One of your files was set aside by our team under our illegal-content policy. If the law requires us to keep it, it stays encrypted and is no longer connected to you or your report.</p>
                <?php endif; ?>
                <p><strong>This cannot be undone.</strong> We keep no copy we could restore.</p>

                <?php if ($error !== null): ?>
                <p class="error" role="alert"><?= Format::e($error) ?></p>
                <?php endif; ?>

                <?php if (!$final): ?>
                <form method="POST" action="/my-report/withdraw" class="cluster">
                    <?= Csrf::field() ?>
                    <a class="btn" href="/my-report">Keep my report</a>
                    <button class="btn btn-danger push" type="submit">Continue</button>
                </form>
                <?php else: ?>
                <form method="POST" action="/my-report/withdraw/confirm" class="stack stack-3">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="withdraw_token" value="<?= Format::e($withdrawToken) ?>">
                    <label class="check">
                        <input type="checkbox" name="understand" value="1" required>
                        <span>I understand everything will be deleted and cannot be recovered.</span>
                    </label>
                    <div class="cluster">
                        <a class="btn" href="/my-report">Keep my report</a>
                        <button class="btn btn-danger push" type="submit"><?= Deck::icon('trash') ?> Delete everything permanently</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </section>

        <?php require __DIR__ . '/../partials/help-panel.php'; ?>
<?php require __DIR__ . '/_bottom.php'; ?>

<?php
use EchoDial\Deck\Deck;
use Keel\App\Support\Asset;
use Keel\Core\Theme;

$authMethod = $authMethod ?? 'both';
$title = 'Admin sign in';
$noindex = true;
?>
<!DOCTYPE html>
<html <?= Deck::htmlAttributes(lang: 'en') ?> <?= Deck::theme(mode: Theme::serverPreference()) ?>>
<head>
<?php require __DIR__ . '/../partials/head.php'; ?>
</head>
<body>
    <?php require __DIR__ . '/../partials/quick-exit.php'; ?>

    <main class="container stage stage-narrow">
        <section class="card">
            <div class="card-body stack stack-6">
                <div class="bar">
                    <div class="stack stack-0">
                        <a class="site-logo" href="/" aria-label="Unsilenced home"><?php $logoLabel = null; require __DIR__ . '/../partials/logo.php'; ?></a>
                        <h1 class="h4">Admin sign in</h1>
                        <p class="text-sm text-muted">For Unsilenced staff. There are no public accounts.</p>
                    </div>
                </div>

                <?php if ($authMethod === 'both'): ?>
                <div class="tabs" role="tablist" aria-label="Sign-in method">
                    <button type="button" class="tab" role="tab" id="tab-otp" aria-controls="panel-otp" aria-selected="true">Code</button>
                    <button type="button" class="tab" role="tab" id="tab-magic" aria-controls="panel-magic" aria-selected="false" tabindex="-1">Magic Link</button>
                </div>
                <?php endif; ?>

                <?php if ($authMethod === 'otp' || $authMethod === 'both'): ?>
                <div id="panel-otp" class="stack stack-4" role="tabpanel" aria-labelledby="tab-otp">
                    <div id="otp-step-email" class="stack stack-4">
                        <div class="field">
                            <label class="label" for="otp-email">Email</label>
                            <input type="email" id="otp-email" class="input" autocomplete="email" placeholder="you@example.com">
                        </div>
                        <button type="button" id="otp-send" class="btn btn-primary btn-block">Send Code</button>
                    </div>
                    <div id="otp-step-code" class="stack stack-4" hidden>
                        <div class="field">
                            <label class="label" for="otp-code">Enter the 6-digit code</label>
                            <input type="text" id="otp-code" class="input otp-input" maxlength="6" inputmode="numeric" autocomplete="one-time-code" placeholder="000000">
                        </div>
                        <button type="button" id="otp-verify" class="btn btn-primary btn-block">Verify</button>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($authMethod === 'magic_link' || $authMethod === 'both'): ?>
                <div id="panel-magic" class="stack stack-4" role="tabpanel" aria-labelledby="tab-magic" <?= $authMethod === 'both' ? 'hidden' : '' ?>>
                    <div class="field">
                        <label class="label" for="magic-email">Email</label>
                        <input type="email" id="magic-email" class="input" autocomplete="email" placeholder="you@example.com">
                    </div>
                    <button type="button" id="magic-send" class="btn btn-primary btn-block">Send Magic Link</button>
                    <div id="magic-sent" class="alert alert-good" hidden>
                        <?= Deck::icon('mail') ?>
                        <p>If that address has admin access, a sign-in link is on its way.</p>
                    </div>
                </div>
                <?php endif; ?>

                <p id="auth-error" class="error" hidden></p>
            </div>
        </section>
    </main>

    <script src="<?= htmlspecialchars(Asset::url('/js/login.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
</body>
</html>

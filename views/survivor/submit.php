<?php
/**
 * /submit. One form, nine steps. With JavaScript (public_html/js/report-form.js)
 * one step shows at a time, the answers stay in the page, and the final submit
 * solves the proof of work first. Without it, every step shows at once and a
 * note explains that sending needs JavaScript.
 *
 * autocomplete="off" on the form also stops the browser restoring what they
 * typed if someone presses Back after they have left.
 */
use EchoDial\Deck\Deck;
use Keel\App\Support\Asset;
use Keel\App\Support\Config;
use Keel\App\Support\Format;
use Keel\Core\Csrf;

$help = (array) Config::get('help', []);
$maxFiles = (int) Config::get('evidence.max_files', 20);
$maxMb = (int) round((int) Config::get('evidence.max_file_bytes', 20971520) / 1048576);
$steps = [
    1 => 'Before you start',
    2 => 'The school',
    3 => 'When and where',
    4 => 'Who',
    5 => 'Reporting',
    6 => 'What happened',
    7 => 'Publishing',
    8 => 'Evidence',
    9 => 'Check and send',
];
$stepCount = count($steps);
$fileErrors = $fileErrors ?? [];

require __DIR__ . '/_top.php';
?>
        <header class="stack stack-2">
            <h1 class="h2">Tell us what happened</h1>
            <p class="lede">Anonymously, at your own pace. We want to show how schools respond when students report, and when they feel they can't.</p>
        </header>

        <noscript>
            <div class="alert alert-warn">
                <?= Deck::icon('alert-triangle') ?>
                <p>To send a report, please turn on JavaScript. We use it only to keep your answers on this page until you send them, and to check you are a person without using any outside service. Nothing else on this site needs it.</p>
            </div>
        </noscript>

        <?php if (!empty($errors['form'])): ?>
        <div class="alert alert-bad" role="alert" id="form-error">
            <?= Deck::icon('alert-circle') ?>
            <p><?= Format::e($errors['form']) ?></p>
        </div>
        <?php elseif ($errors !== [] || $fileErrors !== []): ?>
        <div class="alert alert-bad" role="alert" id="form-error">
            <?= Deck::icon('alert-circle') ?>
            <p>Some answers need another look. The first one is open below.</p>
        </div>
        <?php endif; ?>

        <form id="report-form" class="report-form stack stack-6" method="POST" action="/submit" enctype="multipart/form-data" autocomplete="off" novalidate
              data-start-step="<?= (int) $step ?>" data-step-count="<?= $stepCount ?>"
              data-pow-bits="<?= (int) $pow['bits'] ?>" data-post-max="<?= (int) $postMaxBytes ?>"
              data-max-files="<?= $maxFiles ?>" data-max-file-bytes="<?= (int) Config::get('evidence.max_file_bytes', 20971520) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="pow_challenge" value="<?= Format::e($pow['challenge']) ?>">
            <input type="hidden" name="pow_nonce" value="">
            <div class="hp-field" aria-hidden="true">
                <label for="<?= Format::e($honeypot) ?>">Leave this empty</label>
                <input type="text" id="<?= Format::e($honeypot) ?>" name="<?= Format::e($honeypot) ?>" tabindex="-1" autocomplete="off" value="">
            </div>

            <div class="step-progress stack stack-1" hidden>
                <p class="text-sm fw-semi" id="step-progress-text">Step 1 of <?= $stepCount ?></p>
                <progress class="progress" id="step-progress" max="<?= $stepCount ?>" value="1" aria-labelledby="step-progress-text"></progress>
            </div>

            <?php foreach ($steps as $number => $stepTitle): ?>
            <section class="card form-step" id="step-<?= $number ?>" data-step="<?= $number ?>" aria-labelledby="step-<?= $number ?>-title">
                <div class="card-body stack stack-4">
                    <p class="step-count">Step <?= $number ?> of <?= $stepCount ?></p>
                    <h2 class="h4" id="step-<?= $number ?>-title" tabindex="-1"><?= Format::e($stepTitle) ?></h2>

                    <?php if ($number === 1): ?>
                    <div class="stack stack-3">
                        <p>You can stop at any time. <strong>Nothing is saved until you press Send at the end.</strong> If you close this page before then, what you typed is gone, and nothing about it stays on our server.</p>
                        <p>To leave this page at once, press <strong>Quick exit</strong> at the top of the screen, or press <kbd>Esc</kbd> twice.</p>
                        <div class="alert">
                            <?= Deck::icon('phone') ?>
                            <div>
                                <p class="alert-title">If you want to talk to someone</p>
                                <p class="alert-body">The <?= Format::e($help['hotline_name']) ?> is free and confidential, 24 hours a day: <a href="tel:<?= Format::e($help['hotline_tel']) ?>"><?= Format::e($help['hotline_display']) ?></a>, or chat at <a href="<?= Format::e($help['chat_url']) ?>" rel="noopener noreferrer"><?= Format::e($help['chat_label']) ?></a>. In danger now: <a href="tel:<?= Format::e($help['emergency_number']) ?>"><?= Format::e($help['emergency_number']) ?></a>.</p>
                            </div>
                        </div>
                        <div class="grid-2 publish-rules">
                            <div class="stack stack-2">
                                <h3 class="h6">What we may publish, only if you choose</h3>
                                <ul class="tick-list">
                                    <li>Your answers, added to the school's totals once enough people have reported.</li>
                                    <li>Your account, after a person on our team removes anything that could identify you or anyone else.</li>
                                    <li>With your account: the year, the kind of place, and the kind of person, such as "fellow student".</li>
                                </ul>
                            </div>
                            <div class="stack stack-2">
                                <h3 class="h6">What we never publish</h3>
                                <ul class="cross-list">
                                    <li>Your name, or anything that could identify you.</li>
                                    <li>The names of anyone else.</li>
                                    <li>The time of year or an exact date.</li>
                                    <li>Your evidence files, or your email address.</li>
                                </ul>
                            </div>
                        </div>
                        <p class="text-sm text-muted">On a shared or monitored device, a private or incognito window is safest. This page sets one cookie to keep the form secure. It is deleted when you close the browser and we never use it to track you.</p>
                    </div>

                    <?php elseif ($number === 2): ?>
                    <p>Which school is this about?</p>
                    <?php require __DIR__ . '/fields/school.php'; ?>

                    <?php elseif ($number === 3): ?>
                    <?php require __DIR__ . '/fields/when.php'; ?>

                    <?php elseif ($number === 4): ?>
                    <?php require __DIR__ . '/fields/who.php'; ?>

                    <?php elseif ($number === 5): ?>
                    <?php require __DIR__ . '/fields/reporting.php'; ?>

                    <?php elseif ($number === 6): ?>
                    <?php require __DIR__ . '/fields/account.php'; ?>

                    <?php elseif ($number === 7): ?>
                    <?php require __DIR__ . '/fields/consent.php'; ?>

                    <?php elseif ($number === 8): ?>
                    <div class="stack stack-3">
                        <p>This is optional, and you can add files later from your page. Photos and screenshots of messages, emails from the school, notes you made at the time and recordings can all help.</p>
                        <ul class="tick-list text-sm">
                            <li>Your files are encrypted and stored on our server only. They are never shown publicly.</li>
                            <li>The people who review reports see a copy with hidden details, such as the location where a photo was taken, removed.</li>
                            <li>Your original files, exactly as you add them, go only to people you choose to send a share link to, such as an attorney.</li>
                        </ul>
                        <div class="alert alert-warn">
                            <?= Deck::icon('shield') ?>
                            <div class="stack stack-2">
                                <p class="alert-title">Never add intimate images</p>
                                <p class="alert-body">Nude, sexual or intimate images or video should go only to the police or an attorney. <a href="/resources/save-evidence">How to save your evidence</a>.</p>
                                <label class="check">
                                    <input type="checkbox" name="evidence_attest" value="1" id="evidence_attest"<?= !empty($values['evidence_attest']) ? ' checked' : '' ?><?= !empty($errors['evidence_attest']) ? ' aria-describedby="evidence_attest-error"' : '' ?>>
                                    <span>I am not uploading nude, sexual or intimate images or video.</span>
                                </label>
                                <?php $field = 'evidence_attest'; require __DIR__ . '/_error.php'; ?>
                            </div>
                        </div>
                        <?php if (!empty($filesWereChosen)): ?>
                        <div class="alert alert-info" role="note">
                            <?= Deck::icon('info') ?>
                            <p>For your security, your browser does not keep chosen files when a page reloads. Please choose your files again.</p>
                        </div>
                        <?php endif; ?>
                        <div class="field">
                            <label class="file" for="evidence">
                                <?= Deck::icon('upload') ?>
                                <span class="fw-semi">Choose files</span>
                                <span class="text-sm" id="evidence-help">Photos (JPEG, PNG, HEIC), PDFs, text files, audio (M4A, MP3). Up to <?= $maxFiles ?> files, <?= $maxMb ?> MB each.</span>
                                <input type="file" id="evidence" name="evidence[]" multiple accept=".jpg,.jpeg,.png,.heic,.heif,.pdf,.txt,.m4a,.mp3,image/jpeg,image/png,image/heic,application/pdf,text/plain,audio/mp4,audio/x-m4a,audio/mpeg" aria-describedby="evidence-help">
                            </label>
                            <?php $field = 'evidence'; require __DIR__ . '/_error.php'; ?>
                        </div>
                        <ul class="file-list" id="evidence-list" aria-live="polite"></ul>
                        <?php if ($fileErrors !== []): ?>
                        <div class="alert alert-bad" role="alert">
                            <?= Deck::icon('alert-circle') ?>
                            <div class="stack stack-1">
                                <p class="alert-title">Some files could not be added</p>
                                <ul class="alert-body">
                                    <?php foreach ($fileErrors as $index => $message): ?>
                                    <li>File <?= (int) $index + 1 ?>: <?= Format::e($message) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php else: ?>
                    <div class="stack stack-4">
                        <p>Check your answers. You can go back to change anything.</p>
                        <dl class="review-list" id="review-summary"></dl>

                        <div class="field">
                            <label class="label" for="email">Email for updates (optional)</label>
                            <input class="input" type="email" id="email" name="email" autocomplete="off" spellcheck="false" value="<?= Format::e($values['email'] ?? '') ?>" aria-describedby="email-help<?= !empty($errors['email']) ? ' email-error' : '' ?>">
                            <div class="help stack stack-1" id="email-help">
                                <p><strong>Email can identify you.</strong> Anyone who can read your inbox will see a message from us. It only ever says an update is ready, never what it is.</p>
                                <p>We keep it encrypted and delete it when your report is published, not published, or withdrawn. Without it, check your page with your key.</p>
                            </div>
                            <?php $field = 'email'; require __DIR__ . '/_error.php'; ?>
                        </div>

                        <label class="check">
                            <input type="checkbox" name="attest" value="1" id="attest" required<?= !empty($errors['attest']) ? ' aria-describedby="attest-error"' : '' ?>>
                            <span>What I have shared is true to the best of my knowledge.</span>
                        </label>
                        <?php $field = 'attest'; require __DIR__ . '/_error.php'; ?>

                        <p class="text-sm text-muted">When you send, we give you a six-word key. It is the only way back to your report, so you will need to keep it somewhere safe.</p>
                        <p class="text-sm" id="send-status" role="status" aria-live="polite"></p>
                    </div>
                    <?php endif; ?>

                    <?php require __DIR__ . '/_hotline.php'; ?>

                    <div class="form-actions step-nav">
                        <?php if ($number > 1): ?>
                        <button type="button" class="btn" data-step-back hidden><?= Deck::icon('arrow-left') ?> Back</button>
                        <?php endif; ?>
                        <?php if ($number < $stepCount): ?>
                        <button type="button" class="btn btn-primary push" data-step-next hidden>Continue <?= Deck::icon('arrow-right') ?></button>
                        <?php else: ?>
                        <button type="submit" class="btn btn-primary push" id="send-report"><?= Deck::icon('send') ?> Send my report</button>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
            <?php endforeach; ?>
        </form>

        <script src="<?= Format::e(Asset::url('/js/pow.js')) ?>" defer></script>
        <script src="<?= Format::e(Asset::url('/js/report-form.js')) ?>" defer></script>
<?php require __DIR__ . '/_bottom.php'; ?>

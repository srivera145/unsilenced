<?php
/**
 * /my-report, signed in with her key: status, our note, her answers, her
 * evidence, her share links, email updates and withdrawal.
 */
use EchoDial\Deck\Deck;
use Keel\App\Models\EvidenceFile;
use Keel\App\Models\SurvivorReport;
use Keel\App\Support\Asset;
use Keel\App\Support\Config;
use Keel\App\Support\Format;
use Keel\Core\Csrf;

$quickExitSignOut = '/my-report/sign-out';
$config = (array) Config::get('survivor_reports', []);
$types = (array) Config::get('evidence.types', []);
$status = (string) $report['status'];
$editable = in_array($status, SurvivorReport::EDITABLE, true);
$fileErrors = $fileErrors ?? [];
$fileNames = $fileNames ?? [];
$maxFiles = (int) Config::get('evidence.max_files', 20);
$maxMb = (int) round((int) Config::get('evidence.max_file_bytes', 20971520) / 1048576);
$visibleFiles = array_values(array_filter($files, static fn (array $file): bool => !EvidenceFile::isQuarantined($file)));
$labels = static fn (string $group, ?string $list): string => implode('; ', array_map(
    static fn (string $key): string => (string) ($config[$group][$key] ?? $key),
    SurvivorReport::listValue($list)
));
$sizeLabel = static fn (int $bytes): string => $bytes >= 1048576 ? number_format($bytes / 1048576, 1) . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
$deletesOn = $report['rejected_at'] !== null ? gmdate('F j, Y', (int) strtotime($report['rejected_at'] . ' UTC') + 86400 * (int) ($config['rejected_retention_days'] ?? 30)) : null;
$statusText = match ($status) {
    'private' => 'Your report is kept private. No one at Unsilenced reads it, and it is not counted or published.',
    'submitted' => 'A person on our team will read your report. Nothing is published until they approve it.',
    'in_review' => 'A person on our team is reading your report now. Nothing is published until they approve it.',
    'changes_requested' => 'We have asked you to change something before your report can be published. Our note is below. When you edit your report, it goes back for review.',
    'approved' => $report['consent'] === 'stats_and_account'
        ? 'Your report is counted in the school\'s figures, and your account is published as shown below.'
        : ($report['consent'] === 'stats' ? 'Your report is counted in the school\'s figures. Your account is not published.' : 'Your report is kept private.'),
    'rejected' => 'We could not publish your report. Our note below explains why. Your report and everything with it will be deleted on ' . $deletesOn . '.',
    default => '',
};

require __DIR__ . '/_top.php';
?>
        <header class="bar wrap">
            <div class="stack stack-1">
                <h1 class="h2">Your report</h1>
                <p class="text-muted"><?= Format::e($report['school_name']) ?> · <?= (int) $report['incident_year'] ?></p>
            </div>
            <form class="push" method="POST" action="/my-report/sign-out">
                <?= Csrf::field() ?>
                <button class="btn btn-sm" type="submit"><?= Deck::icon('log-out') ?> Sign out</button>
            </form>
        </header>

        <?php if ($notice !== null): ?>
        <div class="alert alert-good" role="status">
            <?= Deck::icon('check-circle') ?>
            <p><?= Format::e($notice) ?></p>
        </div>
        <?php endif; ?>

        <section class="card" aria-labelledby="status-title">
            <div class="card-body stack stack-3">
                <div class="cluster cluster-tight">
                    <h2 class="h5" id="status-title">Status</h2>
                    <span class="badge <?= $status === 'approved' ? 'badge-good' : ($status === 'rejected' || $status === 'changes_requested' ? 'badge-warn' : 'badge-brand') ?>"><?= Format::e($config['statuses'][$status] ?? $status) ?></span>
                </div>
                <p><?= Format::e($statusText) ?></p>
                <?php if ($adminNote !== null): ?>
                <div class="alert alert-info">
                    <?= Deck::icon('message') ?>
                    <div class="stack stack-1">
                        <p class="alert-title">A note from us</p>
                        <p class="alert-body pre-line"><?= Format::e($adminNote) ?></p>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($status === 'approved' && $report['consent'] === 'stats_and_account' && $published !== null): ?>
                <div class="stack stack-2">
                    <h3 class="h6">How your account appears</h3>
                    <blockquote class="published-account pre-line"><?= Format::e($published) ?></blockquote>
                    <p class="text-sm text-muted">Shown on the school's page with the year, where it happened and who did it, as a category. Never the time of year or a date.</p>
                </div>
                <?php endif; ?>
                <?php if ($editable): ?>
                <p><a class="btn" href="/my-report/edit"><?= Deck::icon('edit') ?> Edit your report</a></p>
                <?php endif; ?>
            </div>
        </section>

        <?php if (!in_array($report['consent'], ['private'], true) && $status !== 'rejected'): ?>
        <section class="card" aria-labelledby="consent-title">
            <div class="card-body stack stack-3">
                <h2 class="h5" id="consent-title">What we publish</h2>
                <p>You chose: <strong><?= Format::e($config['consents'][$report['consent']]['label'] ?? $report['consent']) ?></strong>.</p>
                <p class="text-sm text-muted">You can publish less at any time. To publish more, edit your report<?= $editable ? '' : ' (not possible once it is published; you can withdraw it and send a new one)' ?>.</p>
                <form class="cluster" method="POST" action="/my-report/consent" data-confirm="confirm-consent">
                    <?= Csrf::field() ?>
                    <?php if ($report['consent'] === 'stats_and_account'): ?>
                    <button class="btn btn-sm" type="submit" name="consent" value="stats">Stop publishing my account</button>
                    <?php endif; ?>
                    <button class="btn btn-sm" type="submit" name="consent" value="private">Keep it private instead</button>
                </form>
            </div>
        </section>
        <dialog class="modal" id="confirm-consent" aria-labelledby="confirm-consent-title">
            <div class="modal-header"><h2 class="modal-title" id="confirm-consent-title">Publish less?</h2></div>
            <div class="modal-body"><p>This takes effect at once. To publish more again later, you would need to edit your report before it is approved, or send a new one.</p></div>
            <div class="modal-footer">
                <button class="btn" type="button" data-modal-close>Cancel</button>
                <button class="btn btn-primary" type="button" data-confirm-submit="">Yes, publish less</button>
            </div>
        </dialog>
        <?php endif; ?>

        <section class="card" aria-labelledby="answers-title">
            <div class="card-body stack stack-3">
                <h2 class="h5" id="answers-title">Your answers</h2>
                <dl class="review-list">
                    <dt>School</dt><dd><?= Format::e($report['school_name']) ?></dd>
                    <dt>Year</dt><dd><?= (int) $report['incident_year'] ?></dd>
                    <dt>Time of year</dt><dd><?= Format::e($config['seasons'][$report['incident_season'] ?? ''] ?? 'Not given') ?> <span class="text-muted text-sm">(never published)</span></dd>
                    <dt>Where</dt><dd><?= Format::e($config['settings'][$report['setting'] ?? ''] ?? 'Not given') ?></dd>
                    <dt>Who</dt><dd><?= Format::e($config['perpetrators'][$report['perpetrator']] ?? '') ?></dd>
                    <dt>Reported to the school</dt><dd><?= (int) $report['reported_to_school'] === 1 ? 'Yes: ' . Format::e($labels('school_channels', $report['school_channels'])) : 'No' ?></dd>
                    <?php if ((int) $report['reported_to_school'] === 1): ?>
                    <dt>What happened next</dt><dd><?= Format::e($labels('school_outcomes', $report['school_outcomes']) ?: 'Not given') ?></dd>
                    <dt>How the school responded</dt><dd><?= $report['response_rating'] !== null ? (int) $report['response_rating'] . ': ' . Format::e($config['ratings'][(int) $report['response_rating']] ?? '') : 'Not rated' ?></dd>
                    <?php else: ?>
                    <dt>Why not</dt><dd><?= Format::e($labels('not_reported_reasons', $report['not_reported_reasons']) ?: 'Not given') ?></dd>
                    <?php endif; ?>
                    <dt>Reported to the police</dt><dd><?= Format::e($config['police'][$report['reported_to_police'] ?? ''] ?? 'Not given') ?></dd>
                </dl>
                <?php if ($account !== null): ?>
                <details class="account-details">
                    <summary>Your account, as you wrote it (<?= number_format(mb_strlen($account)) ?> characters)</summary>
                    <p class="pre-line account-text"><?= Format::e($account) ?></p>
                </details>
                <?php else: ?>
                <p class="text-muted">You did not write an account.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="card" aria-labelledby="evidence-title" id="your-evidence">
            <div class="card-body stack stack-4">
                <h2 class="h5" id="evidence-title">Your evidence</h2>
                <p class="text-sm text-muted">Encrypted on our server and never shown publicly. Each file's fingerprint (SHA-256) and the time it arrived were recorded when you added it, so anyone you share it with can check it has not changed.</p>

                <?php if (!empty($errors['evidence']) || !empty($errors['evidence_attest']) || $fileErrors !== []): ?>
                <div class="alert alert-bad" role="alert">
                    <?= Deck::icon('alert-circle') ?>
                    <div class="stack stack-1">
                        <?php foreach (['evidence', 'evidence_attest'] as $key): ?>
                        <?php if (!empty($errors[$key])): ?><p><?= Format::e($errors[$key]) ?></p><?php endif; ?>
                        <?php endforeach; ?>
                        <?php if ($fileErrors !== []): ?>
                        <p class="alert-title">Some files could not be added</p>
                        <ul class="alert-body">
                            <?php foreach ($fileErrors as $index => $message): ?>
                            <li><?= Format::e($fileNames[$index] ?? 'File ' . ((int) $index + 1)) ?>: <?= Format::e($message) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($files === []): ?>
                <p>You have not added any files.</p>
                <?php else: ?>
                <ul class="evidence-list">
                    <?php foreach ($files as $file): $name = EvidenceFile::name($file); ?>
                    <li class="evidence-item stack stack-2">
                        <div class="bar wrap">
                            <div class="stack stack-0 min-is-0">
                                <span class="fw-semi break-anywhere"><?= Format::e($name) ?></span>
                                <span class="text-sm text-muted"><?= Format::e($types[$file['kind']]['label'] ?? $file['kind']) ?> · <?= $sizeLabel((int) $file['size_bytes']) ?> · added <?= Format::e($file['uploaded_at']) ?> UTC</span>
                            </div>
                            <?php if (EvidenceFile::isQuarantined($file)): ?>
                            <span class="badge badge-warn push">Held by our team</span>
                            <?php else: ?>
                            <div class="cluster cluster-tight push">
                                <a class="btn btn-sm" href="/my-report/evidence/<?= (int) $file['id'] ?>"><?= Deck::icon('eye') ?> View</a>
                                <form method="POST" action="/my-report/evidence/<?= (int) $file['id'] ?>/delete" id="delete-file-<?= (int) $file['id'] ?>" data-confirm="confirm-delete-file">
                                    <?= Csrf::field() ?>
                                    <button class="btn btn-sm btn-ghost" type="submit" aria-label="Delete <?= Format::e($name) ?>"><?= Deck::icon('trash') ?> Delete</button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-muted">SHA-256 <code class="hash"><?= Format::e($file['sha256']) ?></code></p>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <dialog class="modal" id="confirm-delete-file" aria-labelledby="confirm-delete-file-title">
                    <div class="modal-header"><h2 class="modal-title" id="confirm-delete-file-title">Delete this file?</h2></div>
                    <div class="modal-body"><p>It is deleted from our server and from any share link. This cannot be undone.</p></div>
                    <div class="modal-footer">
                        <button class="btn" type="button" data-modal-close>Cancel</button>
                        <button class="btn btn-danger" type="button" data-confirm-submit="">Delete</button>
                    </div>
                </dialog>
                <?php endif; ?>

                <?php if (count($files) < $maxFiles && $status !== 'rejected'): ?>
                <form class="stack stack-3 add-evidence" method="POST" action="/my-report/evidence" enctype="multipart/form-data" id="report-form" data-max-files="<?= $maxFiles - count($files) ?>" data-max-file-bytes="<?= (int) Config::get('evidence.max_file_bytes', 20971520) ?>">
                    <?= Csrf::field() ?>
                    <h3 class="h6">Add files</h3>
                    <label class="check">
                        <input type="checkbox" name="evidence_attest" value="1" id="evidence_attest">
                        <span>I am not uploading nude, sexual or intimate images or video. Those should go only to the police or an attorney. <a href="/resources/save-evidence">How to save your evidence</a>.</span>
                    </label>
                    <label class="file" for="evidence">
                        <?= Deck::icon('upload') ?>
                        <span class="fw-semi">Choose files</span>
                        <span class="text-sm" id="evidence-help">Photos (JPEG, PNG, HEIC), PDFs, text files, audio (M4A, MP3). <?= $maxMb ?> MB each; <?= $maxFiles - count($files) ?> more allowed.</span>
                        <input type="file" id="evidence" name="evidence[]" multiple accept=".jpg,.jpeg,.png,.heic,.heif,.pdf,.txt,.m4a,.mp3,image/jpeg,image/png,image/heic,application/pdf,text/plain,audio/mp4,audio/x-m4a,audio/mpeg" aria-describedby="evidence-help">
                    </label>
                    <ul class="file-list" id="evidence-list" aria-live="polite"></ul>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit"><?= Deck::icon('upload') ?> Add files</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </section>

        <section class="card" aria-labelledby="links-title" id="share-links">
            <div class="card-body stack stack-4">
                <h2 class="h5" id="links-title">Share links</h2>
                <p class="text-sm text-muted">A share link lets someone you choose, such as an attorney or an advocate, download the files you pick, exactly as you added them, with each file's fingerprint. It stops working when it expires, or the moment you turn it off. We record only when it was last opened.</p>

                <?php if ($links !== []): ?>
                <ul class="evidence-list">
                    <?php foreach ($links as $link): ?>
                    <li class="evidence-item bar wrap">
                        <div class="stack stack-0">
                            <span class="fw-semi"><?= Format::e($link['label'] ?? 'Share link') ?></span>
                            <span class="text-sm text-muted">
                                <?= Format::plural((int) $link['file_count'], 'file') ?>
                                · <?= $link['usable'] ? 'works until ' . Format::e($link['expires_at']) . ' UTC' : ((int) $link['failed_attempts'] >= \Keel\App\Services\Survivor\ShareLinkService::maxAttempts() ? 'stopped after too many wrong passcodes' : 'expired') ?>
                                · <?= $link['passcode_hash'] !== null ? 'passcode' : 'no passcode' ?>
                                · <?= $link['last_opened_at'] !== null ? 'last opened ' . Format::e($link['last_opened_at']) . ' UTC' : 'not opened yet' ?>
                            </span>
                        </div>
                        <form class="push" method="POST" action="/my-report/share-links/<?= (int) $link['id'] ?>/revoke">
                            <?= Csrf::field() ?>
                            <button class="btn btn-sm" type="submit"><?= Deck::icon('x-circle') ?> Turn off</button>
                        </form>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>

                <?php if ($visibleFiles !== []): ?>
                <details class="share-create"<?= !empty($errors['files']) || !empty($errors['expiry']) || !empty($errors['passcode']) ? ' open' : '' ?>>
                    <summary class="btn btn-sm"><?= Deck::icon('link') ?> Make a share link</summary>
                    <form class="stack stack-4 share-create-form" method="POST" action="/my-report/share-links" autocomplete="off">
                        <?= Csrf::field() ?>
                        <fieldset class="fieldset"<?= !empty($errors['files']) ? ' aria-describedby="files-error"' : '' ?>>
                            <legend>Files to share</legend>
                            <?php foreach ($visibleFiles as $file): ?>
                            <label class="check"><input type="checkbox" name="files[]" value="<?= (int) $file['id'] ?>"><span class="break-anywhere"><?= Format::e(EvidenceFile::name($file)) ?></span></label>
                            <?php endforeach; ?>
                            <?php $field = 'files'; require __DIR__ . '/_error.php'; ?>
                        </fieldset>
                        <div class="field">
                            <label class="label" for="label">Who it is for (optional, only you see this)</label>
                            <input class="input" id="label" name="label" maxlength="80" placeholder="For example: my attorney">
                        </div>
                        <fieldset class="fieldset"<?= !empty($errors['expiry']) ? ' aria-describedby="expiry-error"' : '' ?>>
                            <legend>How long it works</legend>
                            <div class="cluster">
                                <?php foreach ((array) ($config['share_link_expiry'] ?? []) as $key => $expiry): ?>
                                <label class="check"><input type="radio" name="expiry" value="<?= Format::e($key) ?>"<?= $key === '7d' ? ' checked' : '' ?>><span><?= Format::e($expiry['label']) ?></span></label>
                                <?php endforeach; ?>
                            </div>
                            <?php $field = 'expiry'; require __DIR__ . '/_error.php'; ?>
                        </fieldset>
                        <div class="field">
                            <label class="label" for="passcode">Passcode (optional)</label>
                            <input class="input input-narrow" type="text" id="passcode" name="passcode" autocomplete="off" spellcheck="false" aria-describedby="passcode-help<?= !empty($errors['passcode']) ? ' passcode-error' : '' ?>">
                            <p class="help" id="passcode-help">At least 6 characters. Tell it to the person separately, for example by phone, not in the same message as the link.</p>
                            <?php $field = 'passcode'; require __DIR__ . '/_error.php'; ?>
                        </div>
                        <div class="form-actions">
                            <button class="btn btn-primary" type="submit">Make the link</button>
                        </div>
                    </form>
                </details>
                <?php endif; ?>
            </div>
        </section>

        <?php if (in_array($status, SurvivorReport::QUEUE, true)): ?>
        <section class="card" aria-labelledby="email-title">
            <div class="card-body stack stack-3">
                <h2 class="h5" id="email-title">Email updates</h2>
                <p><?= $hasEmail ? 'We will email you when there is an update. The email only says an update is ready.' : 'We will not email you. Check this page with your key for updates.' ?></p>
                <p class="text-sm text-muted">Email can identify you: anyone who can read your inbox will see a message from us. We delete the address when your report is published, not published, or withdrawn.</p>
                <form class="stack stack-2" method="POST" action="/my-report/email" autocomplete="off">
                    <?= Csrf::field() ?>
                    <?php if ($hasEmail): ?>
                    <input type="hidden" name="remove" value="1">
                    <div><button class="btn btn-sm" type="submit">Stop emails and delete my address</button></div>
                    <?php else: ?>
                    <div class="field">
                        <label class="label" for="email">Email address</label>
                        <input class="input" type="email" id="email" name="email" spellcheck="false"<?= !empty($errors['email']) ? ' aria-invalid="true" aria-describedby="email-error"' : '' ?>>
                        <?php $field = 'email'; require __DIR__ . '/_error.php'; ?>
                    </div>
                    <div><button class="btn btn-sm" type="submit">Email me updates</button></div>
                    <?php endif; ?>
                </form>
            </div>
        </section>
        <?php endif; ?>

        <section class="card card-danger-zone" aria-labelledby="withdraw-title">
            <div class="card-body stack stack-3">
                <h2 class="h5" id="withdraw-title">Withdraw your report</h2>
                <p>Permanently delete your report, your account, every file and share link, and remove it from every figure. You can do this at any time.</p>
                <p><a class="btn btn-danger" href="/my-report/withdraw"><?= Deck::icon('trash') ?> Withdraw and delete everything</a></p>
            </div>
        </section>

        <?php require __DIR__ . '/../partials/help-panel.php'; ?>
        <script src="<?= Format::e(Asset::url('/js/report-form.js')) ?>" defer></script>
<?php require __DIR__ . '/_bottom.php'; ?>

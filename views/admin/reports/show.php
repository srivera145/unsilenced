<?php
/**
 * Reviewing one report. Her original account is read-only; only the
 * published version is edited, and only by removing (RedactionCheck). Evidence
 * is listed without file names (a name is metadata too) and opens only with a
 * code entered in the last 15 minutes.
 */
use EchoDial\Deck\Deck;
use Keel\App\Models\EvidenceFile;
use Keel\App\Models\School;
use Keel\App\Models\SurvivorReport;
use Keel\App\Support\Asset;
use Keel\App\Support\Config;
use Keel\App\Support\Format;
use Keel\Core\Csrf;

$adminSection = 'reports';
require __DIR__ . '/../_top.php';

$config = (array) Config::get('survivor_reports', []);
$types = (array) Config::get('evidence.types', []);
$status = (string) $report['status'];
$inQueue = in_array($status, SurvivorReport::QUEUE, true);
$labels = static fn (string $group, ?string $list): string => implode('; ', array_map(
    static fn (string $key): string => (string) ($config[$group][$key] ?? $key),
    SurvivorReport::listValue($list)
));
$sizeLabel = static fn (int $bytes): string => $bytes >= 1048576 ? number_format($bytes / 1048576, 1) . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
$statusLabels = ['submitted' => 'Waiting for review', 'in_review' => 'In review', 'changes_requested' => 'Changes requested', 'approved' => 'Approved', 'rejected' => 'Rejected'];
$copyLabels = [
    'stripped' => 'Metadata removed',
    'partial' => 'Some metadata may remain',
    'unavailable' => 'Cannot be shown (the file could not be cleaned)',
];
$schoolPath = School::path(['state' => $report['school_state'], 'slug' => $report['school_slug']]);
$publishesAccount = $report['consent'] === 'stats_and_account';
?>
        <header class="stack stack-2">
            <nav aria-label="Breadcrumb">
                <ol class="breadcrumb">
                    <li><a href="/admin/reports">Survivor reports</a></li>
                    <li><span aria-current="page">#<?= (int) $report['id'] ?></span></li>
                </ol>
            </nav>
            <div class="bar wrap">
                <h1 class="h2">Report #<?= (int) $report['id'] ?></h1>
                <span class="badge <?= $status === 'approved' ? 'badge-good' : ($status === 'rejected' ? 'badge-bad' : 'badge-brand') ?>"><?= Format::e($statusLabels[$status] ?? $status) ?></span>
                <?php if ($status === 'submitted'): ?>
                <form class="push" method="POST" action="/admin/reports/<?= (int) $report['id'] ?>/start-review">
                    <?= Csrf::field() ?>
                    <button class="btn btn-primary" type="submit">Start review</button>
                </form>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($errors !== []): ?>
        <div class="alert alert-bad" role="alert">
            <?= Deck::icon('alert-circle') ?>
            <div class="stack stack-1">
                <?php foreach ($errors as $message): ?>
                <p><?= Format::e($message) ?></p>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="split rail-24">
            <div class="stack stack-6 min-is-0">
                <section class="card" aria-labelledby="answers-title">
                    <div class="card-body stack stack-3">
                        <h2 class="h5" id="answers-title">Her answers</h2>
                        <dl class="review-list">
                            <dt>School</dt><dd><a href="<?= Format::e($schoolPath) ?>"><?= Format::e($report['school_name']) ?></a>, <?= Format::e($report['school_state']) ?> <span class="text-muted text-sm">(UNITID <?= (int) $report['school_unitid'] ?>)</span></dd>
                            <dt>Year</dt><dd><?= (int) $report['incident_year'] ?></dd>
                            <dt>Time of year</dt><dd><?= Format::e($config['seasons'][$report['incident_season'] ?? ''] ?? 'Not given') ?> <span class="text-muted text-sm">(never published)</span></dd>
                            <dt>Where</dt><dd><?= Format::e($config['settings'][$report['setting'] ?? ''] ?? 'Not given') ?></dd>
                            <dt>Who</dt><dd><?= Format::e($config['perpetrators'][$report['perpetrator']] ?? '') ?></dd>
                            <dt>Reported to the school</dt><dd><?= (int) $report['reported_to_school'] === 1 ? 'Yes: ' . Format::e($labels('school_channels', $report['school_channels'])) : 'No' ?></dd>
                            <?php if ((int) $report['reported_to_school'] === 1): ?>
                            <dt>What happened next</dt><dd><?= Format::e($labels('school_outcomes', $report['school_outcomes']) ?: 'Not given') ?></dd>
                            <dt>Response rating</dt><dd><?= $report['response_rating'] !== null ? (int) $report['response_rating'] . ' of 5' : 'Not rated' ?></dd>
                            <?php else: ?>
                            <dt>Why not</dt><dd><?= Format::e($labels('not_reported_reasons', $report['not_reported_reasons']) ?: 'Not given') ?></dd>
                            <?php endif; ?>
                            <dt>Police</dt><dd><?= Format::e($config['police'][$report['reported_to_police'] ?? ''] ?? 'Not given') ?></dd>
                            <dt>Publishing</dt><dd><strong><?= Format::e($config['consents'][$report['consent']]['label'] ?? $report['consent']) ?></strong></dd>
                            <dt>Sent</dt><dd><?= Format::e($report['submitted_at']) ?> UTC<?= $report['updated_at'] !== $report['submitted_at'] ? ' · last changed ' . Format::e($report['updated_at']) . ' UTC' : '' ?></dd>
                        </dl>
                    </div>
                </section>

                <section class="card" aria-labelledby="original-title">
                    <div class="card-body stack stack-3">
                        <h2 class="h5" id="original-title">Her account, as she wrote it</h2>
                        <p class="text-sm text-muted">Private. Never published or edited. <?= (int) $report['name_scan_confirmed'] === 1 ? 'She saw the name check\'s highlights and chose to keep the text as it is.' : '' ?></p>
                        <?php if ($account !== null): ?>
                        <div class="account-text pre-line"><?= Format::e($account) ?></div>
                        <?php else: ?>
                        <p class="text-muted">She did not write an account.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <?php if ($account !== null && $publishesAccount): ?>
                <section class="card" aria-labelledby="published-title" id="published">
                    <div class="card-body stack stack-4">
                        <h2 class="h5" id="published-title">Published version</h2>
                        <p class="text-sm">Remove anything that could identify her or anyone else: names, places, dates, small details. You may only <strong>remove</strong> words and put a placeholder where something was taken out. Adding words is refused. Punctuation and capitals may change.</p>

                        <?php if ($draftSegments !== []): ?>
                        <div class="alert alert-warn">
                            <?= Deck::icon('alert-triangle') ?>
                            <div class="stack stack-2 min-is-0">
                                <p class="alert-title">The name check flags these in the version below</p>
                                <div class="scan-preview"><?php foreach ($draftSegments as $segment): ?><?php if ($segment['type'] === null): ?><?= Format::e($segment['text']) ?><?php else: ?><mark class="scan-mark"><?= Format::e($segment['text']) ?><span class="sr-only"> (<?= Format::e($segment['label']) ?>)</span></mark><?php endif; ?><?php endforeach; ?></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($redaction !== null && !$redaction['ok']): ?>
                        <div class="alert alert-bad" role="alert">
                            <?= Deck::icon('alert-circle') ?>
                            <div class="stack stack-1">
                                <?php if ($redaction['added'] !== []): ?>
                                <p>Not in her account at that point: <?php foreach ($redaction['added'] as $index => $word): ?><?= $index > 0 ? ', ' : '' ?><span class="flagged-phrase"><?= Format::e($word) ?></span><?php endforeach; ?></p>
                                <?php endif; ?>
                                <?php if ($redaction['unknown_brackets'] !== []): ?>
                                <p>Not one of the placeholders: <?php foreach ($redaction['unknown_brackets'] as $index => $bracket): ?><?= $index > 0 ? ', ' : '' ?><span class="flagged-phrase"><?= Format::e($bracket) ?></span><?php endforeach; ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <form class="stack stack-3" method="POST" action="/admin/reports/<?= (int) $report['id'] ?>/published">
                            <?= Csrf::field() ?>
                            <div class="field">
                                <label class="label" for="published-text">Published text</label>
                                <textarea class="textarea" id="published-text" name="published" rows="12" aria-describedby="placeholders-help"><?= Format::e($draft) ?></textarea>
                            </div>
                            <div class="stack stack-2" id="placeholders-help">
                                <p class="text-sm">Placeholders (click to put one where the cursor is):</p>
                                <div class="cluster cluster-tight" data-placeholders-for="published-text">
                                    <?php foreach ($placeholders as $placeholder): ?>
                                    <button type="button" class="btn btn-sm btn-soft" data-placeholder="<?= Format::e($placeholder) ?>"><?= Format::e($placeholder) ?></button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="form-actions">
                                <button class="btn btn-primary" type="submit">Save published version</button>
                            </div>
                        </form>
                        <?php if ($published === null): ?>
                        <p class="text-sm text-muted">Not saved yet. The text above starts as her account.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <?php if ($diff !== null): ?>
                <section class="card" aria-labelledby="diff-title">
                    <div class="card-body stack stack-3">
                        <h2 class="h5" id="diff-title">What changes</h2>
                        <p class="text-sm text-muted"><?= Format::plural($diff['removed'], 'word or mark', 'words or marks') ?> removed, <?= Format::plural($diff['inserted'], 'placeholder or mark', 'placeholders or marks') ?> added. Check that no removal changes what she said, such as taking out "not".</p>
                        <div class="diff-columns">
                            <div class="stack stack-2 min-is-0">
                                <h3 class="h6">Her original</h3>
                                <div class="diff-text"><?= $diff['left'] ?></div>
                            </div>
                            <div class="stack stack-2 min-is-0">
                                <h3 class="h6">Published version</h3>
                                <div class="diff-text"><?= $diff['right'] ?></div>
                            </div>
                        </div>
                    </div>
                </section>
                <?php endif; ?>
                <?php elseif ($account !== null): ?>
                <p class="text-sm text-muted">She chose statistics only: her account is never published, so there is no published version to prepare.</p>
                <?php endif; ?>

                <section class="card" aria-labelledby="evidence-title" id="evidence">
                    <div class="card-body stack stack-4">
                        <h2 class="h5" id="evidence-title">Evidence</h2>
                        <?php if ($files === []): ?>
                        <p>None provided.</p>
                        <?php else: ?>
                        <p class="text-sm text-muted">You see a copy with metadata (location, camera, author, file name) removed. Originals go only to people she sends a share link to. <?= $otpFresh ? 'Your code is fresh for viewing.' : 'Viewing asks for an emailed code first.' ?></p>
                        <ul class="evidence-list">
                            <?php foreach ($files as $index => $file): ?>
                            <li class="evidence-item stack stack-2">
                                <div class="bar wrap">
                                    <div class="stack stack-0 min-is-0">
                                        <span class="fw-semi">File <?= (int) $index + 1 ?> · <?= Format::e($types[$file['kind']]['label'] ?? $file['kind']) ?> · <?= $sizeLabel((int) $file['size_bytes']) ?></span>
                                        <span class="text-sm text-muted">Added <?= Format::e($file['uploaded_at']) ?> UTC · <?= Format::e($copyLabels[$file['admin_copy']] ?? $file['admin_copy']) ?></span>
                                    </div>
                                    <div class="cluster cluster-tight push">
                                        <?php if (EvidenceFile::isQuarantined($file)): ?>
                                        <span class="badge badge-bad">Quarantined <?= Format::e($file['quarantined_at']) ?> UTC</span>
                                        <?php else: ?>
                                        <?php if ($file['reviewed_at'] !== null): ?><span class="badge badge-good">Viewed</span><?php else: ?><span class="badge">Not viewed</span><?php endif; ?>
                                        <?php if (EvidenceFile::viewableByAdmin($file)): ?>
                                        <a class="btn btn-sm" href="/admin/reports/<?= (int) $report['id'] ?>/evidence/<?= (int) $file['id'] ?>"><?= Deck::icon('eye') ?> View</a>
                                        <?php endif; ?>
                                        <form method="POST" action="/admin/reports/<?= (int) $report['id'] ?>/evidence/<?= (int) $file['id'] ?>/quarantine" data-confirm="confirm-quarantine">
                                            <?= Csrf::field() ?>
                                            <button class="btn btn-sm btn-danger" type="submit"><?= Deck::icon('shield') ?> Report illegal content</button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <p class="text-xs text-muted">SHA-256 of the original <code class="hash"><?= Format::e($file['sha256']) ?></code></p>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <dialog class="modal" id="confirm-quarantine" aria-labelledby="confirm-quarantine-title">
                            <div class="modal-header"><h2 class="modal-title" id="confirm-quarantine-title">Quarantine this file?</h2></div>
                            <div class="modal-body stack stack-2">
                                <p>No one will be able to view or download it again: not you, not her, not anyone with a share link. It stays encrypted and is kept. The procedure to follow opens next.</p>
                            </div>
                            <div class="modal-footer">
                                <button class="btn" type="button" data-modal-close>Cancel</button>
                                <button class="btn btn-danger" type="button" data-confirm-submit="">Quarantine</button>
                            </div>
                        </dialog>
                        <?php endif; ?>
                    </div>
                </section>

                <?php if ($inQueue): ?>
                <section class="card" aria-labelledby="approve-title" id="approve">
                    <form class="card-body stack stack-4" method="POST" action="/admin/reports/<?= (int) $report['id'] ?>/approve">
                        <?= Csrf::field() ?>
                        <h2 class="h5" id="approve-title">Approve</h2>
                        <fieldset class="fieldset"<?= !empty($errors['checklist']) ? ' aria-invalid="true"' : '' ?>>
                            <legend>Check every item</legend>
                            <?php foreach ($checklist as $key => $label): ?>
                            <label class="check"><input type="checkbox" name="check_<?= Format::e($key) ?>" value="1"<?= !empty($ticked[$key]) ? ' checked' : '' ?>><span><?= Format::e($label) ?></span></label>
                            <?php endforeach; ?>
                        </fieldset>
                        <fieldset class="fieldset">
                            <legend>Evidence</legend>
                            <label class="check"><input type="radio" name="evidence_reviewed" value="yes"<?= $evidenceChoice === 'yes' ? ' checked' : '' ?>><span>Reviewed: I have viewed every file</span></label>
                            <label class="check"><input type="radio" name="evidence_reviewed" value="none"<?= $evidenceChoice === 'none' ? ' checked' : '' ?>><span>None provided</span></label>
                        </fieldset>
                        <p class="text-sm text-muted">Approving publishes what she agreed to<?= $publishesAccount ? ': her report counts in the school\'s figures and the published version above appears on the school\'s page' : ': her report counts in the school\'s figures. Her account is not published' ?>. Her email, if she gave one, is sent an update and then deleted.</p>
                        <div class="form-actions">
                            <button class="btn btn-primary" type="submit"><?= Deck::icon('check') ?> Approve</button>
                        </div>
                    </form>
                </section>
                <?php endif; ?>

                <?php if ($status !== 'rejected'): ?>
                <section class="card" aria-labelledby="note-title">
                    <form class="card-body stack stack-3" method="POST" action="/admin/reports/<?= (int) $report['id'] ?>/note" id="note-form" data-confirm="confirm-reject">
                        <?= Csrf::field() ?>
                        <h2 class="h5" id="note-title">Note to her</h2>
                        <p class="text-sm text-muted">She reads this on her page. Say what to change, or why it cannot be published. Write kindly and plainly; never repeat details from her account.</p>
                        <textarea class="textarea" id="note" name="note" rows="4" maxlength="2000"<?= !empty($errors['note']) ? ' aria-invalid="true"' : '' ?>><?= Format::e($adminNote ?? '') ?></textarea>
                        <div class="cluster">
                            <button class="btn" type="submit" formaction="/admin/reports/<?= (int) $report['id'] ?>/note" data-skip-confirm>Save note</button>
                            <?php if ($inQueue): ?>
                            <button class="btn" type="submit" formaction="/admin/reports/<?= (int) $report['id'] ?>/request-changes" data-skip-confirm>Request changes</button>
                            <button class="btn btn-danger push" type="submit" formaction="/admin/reports/<?= (int) $report['id'] ?>/reject">Reject</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </section>
                <dialog class="modal" id="confirm-reject" aria-labelledby="confirm-reject-title">
                    <div class="modal-header"><h2 class="modal-title" id="confirm-reject-title">Reject this report?</h2></div>
                    <div class="modal-body"><p>It will not be published or counted. She sees your note on her page, and the report and its files are deleted automatically in <?= (int) ($config['rejected_retention_days'] ?? 30) ?> days.</p></div>
                    <div class="modal-footer">
                        <button class="btn" type="button" data-modal-close>Cancel</button>
                        <button class="btn btn-danger" type="button" data-confirm-submit="">Reject</button>
                    </div>
                </dialog>
                <?php endif; ?>
            </div>

            <aside class="stack stack-4">
                <section class="card" aria-labelledby="history-title">
                    <div class="card-body stack stack-3">
                        <h2 class="h6" id="history-title">History</h2>
                        <ol class="history-list">
                            <?php foreach ($events as $event): ?>
                            <li>
                                <span class="fw-semi"><?= Format::e(str_replace('_', ' ', $event['event'])) ?></span>
                                <span class="text-xs text-muted"><?= Format::e($event['created_at']) ?> UTC · <?= $event['actor'] === 'admin' ? Format::e($event['admin_email'] ?? 'an admin') : Format::e($event['actor']) ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                </section>
                <section class="card">
                    <div class="card-body stack stack-2">
                        <h2 class="h6">Illegal content</h2>
                        <p class="text-sm">If a file may be illegal (for example, an intimate image of a minor), quarantine it and follow <a href="/admin/illegal-content">the procedure</a>. Do not download, copy or forward it.</p>
                    </div>
                </section>
            </aside>
        </div>
        <script src="<?= Format::e(Asset::url('/js/admin-review.js')) ?>" defer></script>
<?php require __DIR__ . '/../_bottom.php'; ?>

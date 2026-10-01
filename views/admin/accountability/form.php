<?php
use EchoDial\Deck\Deck;
use Keel\App\Models\School;
use Keel\App\Support\Config;
use Keel\App\Support\Format;
use Keel\Core\Csrf;

$adminSection = 'accountability';
require __DIR__ . '/../_top.php';
$v = static fn (string $key): string => Format::e($values[$key] ?? '');
$invalid = static fn (string $key): string => isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="' . $key . '-error"' : '';
$flagged = $flagged ?? [];
$maxSummary = (int) Config::get('accountability.summary_max_length', 1000);
$action = $item === null ? '/admin/accountability' : '/admin/accountability/' . (int) $item['id'];
?>
        <header class="stack stack-2">
            <nav aria-label="Breadcrumb">
                <ol class="breadcrumb">
                    <li><a href="/admin/accountability">Accountability</a></li>
                    <?php if ($school !== null): ?>
                    <li><a href="/admin/accountability?school_id=<?= (int) $school['id'] ?>"><?= Format::e($school['name']) ?></a></li>
                    <?php endif; ?>
                    <li><span aria-current="page"><?= $item === null ? 'New record' : 'Edit record' ?></span></li>
                </ol>
            </nav>
            <h1 class="h2"><?= $item === null ? 'New accountability record' : 'Edit accountability record' ?></h1>
        </header>

        <div class="alert alert-info">
            <?= Deck::icon('shield') ?>
            <div>
                <p class="alert-title">Never name individuals</p>
                <p class="alert-body">Write the summary in your own words, describe what the record says, and do not copy article text. Do not name students, staff or anyone accused. The summary is checked for anything that looks like a person's name.</p>
            </div>
        </div>

        <?php if ($errors !== []): ?>
        <div class="alert alert-bad" role="alert">
            <?= Deck::icon('alert-circle') ?>
            <p>Fix the fields marked below.</p>
        </div>
        <?php endif; ?>

        <div class="split" style="--rail: 22rem">
            <form class="card" method="POST" action="<?= Format::e($action) ?>" novalidate>
                <div class="card-body stack stack-4">
                    <?= Csrf::field() ?>
                    <div class="field">
                        <label class="label" for="unitid">School UNITID</label>
                        <input class="input" id="unitid" name="unitid" inputmode="numeric" value="<?= $v('unitid') ?>" required aria-describedby="unitid-help"<?= $invalid('unitid') ?>>
                        <p class="help" id="unitid-help"><?= $school !== null ? 'Currently: ' . Format::e($school['name']) . ', ' . Format::e($school['state']) : 'The IPEDS UNITID shown on the school\'s admin page.' ?></p>
                        <?php $field = 'unitid'; require __DIR__ . '/../_field-error.php'; ?>
                    </div>
                    <div class="field-row">
                        <div class="field">
                            <label class="label" for="type">Type</label>
                            <select class="select" id="type" name="type" required<?= $invalid('type') ?>>
                                <option value="">Choose…</option>
                                <?php foreach ((array) Config::get('accountability.types', []) as $key => $label): ?>
                                <option value="<?= Format::e($key) ?>"<?= ($values['type'] ?? '') === $key ? ' selected' : '' ?>><?= Format::e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php $field = 'type'; require __DIR__ . '/../_field-error.php'; ?>
                        </div>
                        <div class="field">
                            <label class="label" for="item_date">Date</label>
                            <input class="input" type="date" id="item_date" name="item_date" value="<?= $v('item_date') ?>" required<?= $invalid('item_date') ?>>
                            <?php $field = 'item_date'; require __DIR__ . '/../_field-error.php'; ?>
                        </div>
                        <div class="field">
                            <label class="label" for="status">Status</label>
                            <select class="select" id="status" name="status" required<?= $invalid('status') ?>>
                                <option value="">Choose…</option>
                                <?php foreach ((array) Config::get('accountability.statuses', []) as $key => $label): ?>
                                <option value="<?= Format::e($key) ?>"<?= ($values['status'] ?? '') === $key ? ' selected' : '' ?>><?= Format::e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php $field = 'status'; require __DIR__ . '/../_field-error.php'; ?>
                        </div>
                    </div>
                    <div class="field">
                        <label class="label" for="summary">Summary, in our own words</label>
                        <textarea class="textarea" id="summary" name="summary" rows="5" maxlength="<?= $maxSummary ?>" required aria-describedby="summary-help"<?= $invalid('summary') ?>><?= $v('summary') ?></textarea>
                        <p class="help" id="summary-help">Up to <?= number_format($maxSummary) ?> characters. Describe the record; no names, no quoted article text.</p>
                        <?php $field = 'summary'; require __DIR__ . '/../_field-error.php'; ?>
                    </div>

                    <?php if ($flagged !== []): ?>
                    <div class="alert alert-warn" role="alert">
                        <?= Deck::icon('alert-triangle') ?>
                        <div class="stack stack-2">
                            <p class="alert-title">This summary may contain a person's name</p>
                            <p class="alert-body">Flagged: <?php foreach ($flagged as $index => $phrase): ?><?= $index > 0 ? ', ' : '' ?><span class="flagged-phrase"><?= Format::e($phrase) ?></span><?php endforeach; ?></p>
                            <p class="alert-body">Unsilenced never displays names of individuals. Remove any names. If none of these are people's names (for example, a company, a group or a place), confirm below.</p>
                            <label class="check">
                                <input type="checkbox" name="confirm_no_names" value="1" aria-describedby="confirm_no_names-error">
                                <span>I confirm none of the flagged phrases is the name of a person</span>
                            </label>
                            <?php $field = 'confirm_no_names'; require __DIR__ . '/../_field-error.php'; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="field-row">
                        <div class="field">
                            <label class="label" for="source_name">Source name</label>
                            <input class="input" id="source_name" name="source_name" value="<?= $v('source_name') ?>" required placeholder="e.g. U.S. Department of Education, Office for Civil Rights"<?= $invalid('source_name') ?>>
                            <?php $field = 'source_name'; require __DIR__ . '/../_field-error.php'; ?>
                        </div>
                        <div class="field">
                            <label class="label" for="source_url">Source URL</label>
                            <input class="input" type="url" id="source_url" name="source_url" value="<?= $v('source_url') ?>" required placeholder="https://"<?= $invalid('source_url') ?>>
                            <?php $field = 'source_url'; require __DIR__ . '/../_field-error.php'; ?>
                        </div>
                    </div>
                    <label class="check">
                        <input type="checkbox" name="is_published" value="1"<?= (int) ($values['is_published'] ?? 0) === 1 ? ' checked' : '' ?>>
                        <span>Published (shown on the school's public page)</span>
                    </label>
                    <?php if ($item !== null && !empty($item['name_check_confirmed_at'])): ?>
                    <p class="text-xs text-muted">Name check last confirmed by admin #<?= (int) $item['name_check_confirmed_by'] ?> on <?= Format::e($item['name_check_confirmed_at']) ?>.</p>
                    <?php endif; ?>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit"><?= $item === null ? 'Create record' : 'Save changes' ?></button>
                    </div>
                </div>
            </form>

            <?php if ($item !== null): ?>
            <section class="card" aria-labelledby="delete-title">
                <div class="card-body stack stack-3">
                    <h2 id="delete-title" class="card-title">Delete record</h2>
                    <?php if ($school !== null): ?>
                    <p class="text-sm"><a href="<?= Format::e(School::path($school)) ?>">View the school's public page</a></p>
                    <?php endif; ?>
                    <form id="delete-item" method="POST" action="/admin/accountability/<?= (int) $item['id'] ?>/delete" data-confirm="confirm-delete-item">
                        <?= Csrf::field() ?>
                        <button class="btn btn-danger" type="submit"><?= Deck::icon('trash') ?> Delete</button>
                    </form>
                </div>
            </section>

            <dialog class="modal" id="confirm-delete-item" aria-labelledby="confirm-delete-title">
                <div class="modal-header"><h2 id="confirm-delete-title" class="modal-title">Delete this record?</h2></div>
                <div class="modal-body"><p>It will disappear from the school's public page. This cannot be undone.</p></div>
                <div class="modal-footer">
                    <button class="btn" type="button" data-modal-close>Cancel</button>
                    <button class="btn btn-danger" type="button" data-confirm-submit="delete-item">Delete</button>
                </div>
            </dialog>
            <?php endif; ?>
        </div>
<?php require __DIR__ . '/../_bottom.php'; ?>

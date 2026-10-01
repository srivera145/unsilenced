<?php
use EchoDial\Deck\Deck;
use Keel\App\Models\StatePage;
use Keel\App\Support\Format;
use Keel\Core\Csrf;

$adminSection = 'states';
require __DIR__ . '/../_top.php';
$v = static fn (string $key): string => Format::e($values[$key] ?? '');
$invalid = static fn (string $key): string => isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="' . $key . '-error"' : '';
$action = $page === null ? '/admin/states' : '/admin/states/' . (int) $page['id'];
?>
        <header class="stack stack-2">
            <nav aria-label="Breadcrumb">
                <ol class="breadcrumb">
                    <li><a href="/admin/states">State pages</a></li>
                    <li><span aria-current="page"><?= $page === null ? 'New state page' : Format::e($page['name']) ?></span></li>
                </ol>
            </nav>
            <h1 class="h2"><?= $page === null ? 'New state page' : Format::e($page['name']) ?></h1>
            <?php if ($page !== null): ?>
            <p><a href="/states/<?= strtolower(Format::e($page['code'])) ?>">View public page</a></p>
            <?php endif; ?>
        </header>

        <div class="alert alert-warn">
            <?= Deck::icon('alert-triangle') ?>
            <div>
                <p class="alert-title">Legal text must be reviewed before it is published</p>
                <p class="alert-body">Enter only information that has been checked by a qualified reviewer. While the status is “Needs legal review”, the public page shows “Information coming soon” and the national hotline, and none of the text below.</p>
            </div>
        </div>

        <?php if ($errors !== []): ?>
        <div class="alert alert-bad" role="alert"><?= Deck::icon('alert-circle') ?><p>Fix the fields marked below.</p></div>
        <?php endif; ?>

        <form class="card" method="POST" action="<?= Format::e($action) ?>" novalidate>
            <div class="card-body stack stack-4">
                <?= Csrf::field() ?>
                <div class="field-row">
                    <div class="field">
                        <label class="label" for="name">State name</label>
                        <input class="input" id="name" name="name" value="<?= $v('name') ?>" required<?= $invalid('name') ?>>
                        <?php $field = 'name'; require __DIR__ . '/../_field-error.php'; ?>
                    </div>
                    <div class="field">
                        <label class="label" for="code">Code</label>
                        <input class="input" id="code" name="code" maxlength="2" value="<?= $v('code') ?>" required<?= $invalid('code') ?>>
                        <?php $field = 'code'; require __DIR__ . '/../_field-error.php'; ?>
                    </div>
                    <div class="field">
                        <label class="label" for="status">Status</label>
                        <select class="select" id="status" name="status"<?= $invalid('status') ?>>
                            <?php foreach (StatePage::STATUSES as $key => $label): ?>
                            <option value="<?= Format::e($key) ?>"<?= ($values['status'] ?? '') === $key ? ' selected' : '' ?>><?= Format::e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php $field = 'status'; require __DIR__ . '/../_field-error.php'; ?>
                    </div>
                </div>
                <div class="field">
                    <label class="label" for="statute_of_limitations">Statute of limitations</label>
                    <textarea class="textarea" id="statute_of_limitations" name="statute_of_limitations" rows="8"<?= $invalid('statute_of_limitations') ?>><?= $v('statute_of_limitations') ?></textarea>
                    <?php $field = 'statute_of_limitations'; require __DIR__ . '/../_field-error.php'; ?>
                </div>
                <div class="field">
                    <label class="label" for="resources">State resources</label>
                    <textarea class="textarea" id="resources" name="resources" rows="8" aria-describedby="resources-help"<?= $invalid('resources') ?>><?= $v('resources') ?></textarea>
                    <p class="help" id="resources-help">Same formatting as resource pages: <code>## Heading</code>, <code>- bullet</code>, <code>**bold**</code>, <code>[text](https://…)</code>.</p>
                    <?php $field = 'resources'; require __DIR__ . '/../_field-error.php'; ?>
                </div>
                <div class="field-row">
                    <div class="field">
                        <label class="label" for="legal_reviewed_by">Legal review by</label>
                        <input class="input" id="legal_reviewed_by" name="legal_reviewed_by" value="<?= $v('legal_reviewed_by') ?>"<?= $invalid('legal_reviewed_by') ?>>
                        <?php $field = 'legal_reviewed_by'; require __DIR__ . '/../_field-error.php'; ?>
                    </div>
                    <div class="field">
                        <label class="label" for="legal_reviewed_on">Review date</label>
                        <input class="input" type="date" id="legal_reviewed_on" name="legal_reviewed_on" value="<?= $v('legal_reviewed_on') ?>"<?= $invalid('legal_reviewed_on') ?>>
                        <?php $field = 'legal_reviewed_on'; require __DIR__ . '/../_field-error.php'; ?>
                    </div>
                </div>
                <div class="form-actions">
                    <button class="btn btn-primary" type="submit"><?= $page === null ? 'Create state page' : 'Save changes' ?></button>
                </div>
            </div>
        </form>

        <?php if ($page !== null): ?>
        <form id="delete-state" method="POST" action="/admin/states/<?= (int) $page['id'] ?>/delete" data-confirm="confirm-delete-state">
            <?= Csrf::field() ?>
            <button class="btn btn-danger btn-sm" type="submit"><?= Deck::icon('trash') ?> Delete state page</button>
        </form>
        <dialog class="modal" id="confirm-delete-state" aria-labelledby="confirm-delete-title">
            <div class="modal-header"><h2 id="confirm-delete-title" class="modal-title">Delete <?= Format::e($page['name']) ?>?</h2></div>
            <div class="modal-body"><p>Its public page will show “not found”. This cannot be undone.</p></div>
            <div class="modal-footer">
                <button class="btn" type="button" data-modal-close>Cancel</button>
                <button class="btn btn-danger" type="button" data-confirm-submit="delete-state">Delete</button>
            </div>
        </dialog>
        <?php endif; ?>
<?php require __DIR__ . '/../_bottom.php'; ?>

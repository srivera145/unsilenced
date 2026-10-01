<?php
use EchoDial\Deck\Deck;
use Keel\App\Models\School;
use Keel\App\Support\Config;
use Keel\App\Support\Format;
use Keel\Core\Csrf;

$adminSection = 'schools';
require __DIR__ . '/../_top.php';
$v = static fn (string $key): string => Format::e($values[$key] ?? '');
$invalid = static fn (string $key): string => isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="' . $key . '-error"' : '';
$items = $items ?? [];
$action = $school === null ? '/admin/schools' : '/admin/schools/' . (int) $school['id'];
?>
        <header class="stack stack-2">
            <nav aria-label="Breadcrumb">
                <ol class="breadcrumb">
                    <li><a href="/admin/schools">Schools</a></li>
                    <li><span aria-current="page"><?= $school === null ? 'New school' : Format::e($school['name']) ?></span></li>
                </ol>
            </nav>
            <h1 class="h2"><?= $school === null ? 'New school' : Format::e($school['name']) ?></h1>
            <?php if ($school !== null): ?>
            <p><a href="<?= Format::e(School::path($school)) ?>">View public page</a></p>
            <?php endif; ?>
        </header>

        <?php if ($errors !== []): ?>
        <div class="alert alert-bad" role="alert">
            <?= Deck::icon('alert-circle') ?>
            <p>Fix the fields marked below.</p>
        </div>
        <?php endif; ?>

        <div class="split" style="--rail: 24rem">
            <form class="card" method="POST" action="<?= Format::e($action) ?>" novalidate>
                <div class="card-body stack stack-4">
                    <?= Csrf::field() ?>
                    <div class="field-row">
                        <div class="field">
                            <label class="label" for="unitid">IPEDS UNITID</label>
                            <input class="input" id="unitid" name="unitid" inputmode="numeric" value="<?= $v('unitid') ?>" required<?= $invalid('unitid') ?>>
                            <?php $field = 'unitid'; require __DIR__ . '/../_field-error.php'; ?>
                        </div>
                        <div class="field">
                            <label class="label" for="name">Name</label>
                            <input class="input" id="name" name="name" value="<?= $v('name') ?>" required<?= $invalid('name') ?>>
                            <?php $field = 'name'; require __DIR__ . '/../_field-error.php'; ?>
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field">
                            <label class="label" for="city">City</label>
                            <input class="input" id="city" name="city" value="<?= $v('city') ?>">
                        </div>
                        <div class="field">
                            <label class="label" for="state">State</label>
                            <select class="select" id="state" name="state" required<?= $invalid('state') ?>>
                                <option value="">Choose…</option>
                                <?php foreach (Config::allJurisdictions() as $code => $name): ?>
                                <option value="<?= Format::e($code) ?>"<?= ($values['state'] ?? '') === $code ? ' selected' : '' ?>><?= Format::e($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php $field = 'state'; require __DIR__ . '/../_field-error.php'; ?>
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field">
                            <label class="label" for="control">Type</label>
                            <select class="select" id="control" name="control"<?= $invalid('control') ?>>
                                <option value="">Not reported</option>
                                <?php foreach ((array) Config::get('controls', []) as $key => $label): ?>
                                <option value="<?= Format::e($key) ?>"<?= ($values['control'] ?? '') === $key ? ' selected' : '' ?>><?= Format::e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php $field = 'control'; require __DIR__ . '/../_field-error.php'; ?>
                        </div>
                        <div class="field">
                            <label class="label" for="enrollment">Total enrollment</label>
                            <input class="input" id="enrollment" name="enrollment" inputmode="numeric" value="<?= $v('enrollment') ?>"<?= $invalid('enrollment') ?>>
                            <?php $field = 'enrollment'; require __DIR__ . '/../_field-error.php'; ?>
                        </div>
                        <div class="field">
                            <label class="label" for="enrollment_year">Enrollment year (IPEDS)</label>
                            <input class="input" id="enrollment_year" name="enrollment_year" inputmode="numeric" value="<?= $v('enrollment_year') ?>"<?= $invalid('enrollment_year') ?>>
                            <?php $field = 'enrollment_year'; require __DIR__ . '/../_field-error.php'; ?>
                        </div>
                    </div>
                    <div class="field">
                        <label class="label" for="slug">URL name</label>
                        <input class="input" id="slug" name="slug" value="<?= $v('slug') ?>" aria-describedby="slug-help"<?= $invalid('slug') ?>>
                        <p class="help" id="slug-help">The last part of /schools/<?= Format::e(strtolower((string) ($values['state'] ?? 'xx'))) ?>/… Left blank, it is made from the name. Changing it breaks existing links.</p>
                        <?php $field = 'slug'; require __DIR__ . '/../_field-error.php'; ?>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit"><?= $school === null ? 'Create school' : 'Save changes' ?></button>
                    </div>
                </div>
            </form>

            <?php if ($school !== null): ?>
            <div class="stack stack-6">
                <section class="card" aria-labelledby="items-title">
                    <div class="card-header">
                        <h2 id="items-title" class="card-title">Accountability records</h2>
                        <a class="btn btn-sm push" href="/admin/accountability/new?school_id=<?= (int) $school['id'] ?>"><?= Deck::icon('plus') ?> Add</a>
                    </div>
                    <?php if ($items === []): ?>
                    <div class="card-body"><p class="text-muted text-sm">None yet.</p></div>
                    <?php else: ?>
                    <ul class="list">
                        <?php foreach ($items as $item): ?>
                        <li class="list-row">
                            <div class="list-main">
                                <a class="list-title" href="/admin/accountability/<?= (int) $item['id'] ?>/edit"><?= Format::e(Format::date($item['item_date'])) ?> · <?= Format::e(Config::get('accountability.types.' . $item['type'], $item['type'])) ?></a>
                                <span class="list-sub"><?= (int) $item['is_published'] === 1 ? 'Published' : 'Draft' ?></span>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </section>

                <section class="card" aria-labelledby="delete-title">
                    <div class="card-body stack stack-3">
                        <h2 id="delete-title" class="card-title">Delete school</h2>
                        <p class="text-sm text-muted">Deletes the school, its Clery figures and its accountability records. The next IPEDS import will add the school back if it is still in the file.</p>
                        <form id="delete-school" method="POST" action="/admin/schools/<?= (int) $school['id'] ?>/delete" data-confirm="confirm-delete-school">
                            <?= Csrf::field() ?>
                            <button class="btn btn-danger" type="submit"><?= Deck::icon('trash') ?> Delete</button>
                        </form>
                    </div>
                </section>
            </div>

            <dialog class="modal" id="confirm-delete-school" aria-labelledby="confirm-delete-title">
                <div class="modal-header"><h2 id="confirm-delete-title" class="modal-title">Delete <?= Format::e($school['name']) ?>?</h2></div>
                <div class="modal-body"><p>This removes its Clery figures and accountability records too. It cannot be undone.</p></div>
                <div class="modal-footer">
                    <button class="btn" type="button" data-modal-close>Cancel</button>
                    <button class="btn btn-danger" type="button" data-confirm-submit="delete-school">Delete</button>
                </div>
            </dialog>
            <?php endif; ?>
        </div>
<?php require __DIR__ . '/../_bottom.php'; ?>

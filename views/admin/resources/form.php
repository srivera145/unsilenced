<?php
use EchoDial\Deck\Deck;
use Keel\App\Support\Format;
use Keel\App\Support\Markdown;
use Keel\Core\Csrf;

$adminSection = 'resources';
require __DIR__ . '/../_top.php';
$v = static fn (string $key): string => Format::e($values[$key] ?? '');
$invalid = static fn (string $key): string => isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="' . $key . '-error"' : '';
$action = $page === null ? '/admin/resources' : '/admin/resources/' . (int) $page['id'];
?>
        <header class="stack stack-2">
            <nav aria-label="Breadcrumb">
                <ol class="breadcrumb">
                    <li><a href="/admin/resources">Resource pages</a></li>
                    <li><span aria-current="page"><?= $page === null ? 'New page' : Format::e($page['title']) ?></span></li>
                </ol>
            </nav>
            <h1 class="h2"><?= $page === null ? 'New resource page' : Format::e($page['title']) ?></h1>
            <?php if ($page !== null && (int) $page['is_published'] === 1): ?>
            <p><a href="/resources/<?= Format::e($page['slug']) ?>">View public page</a></p>
            <?php endif; ?>
        </header>

        <?php if ($errors !== []): ?>
        <div class="alert alert-bad" role="alert"><?= Deck::icon('alert-circle') ?><p>Fix the fields marked below.</p></div>
        <?php endif; ?>

        <div class="split rail-26">
            <form class="card" method="POST" action="<?= Format::e($action) ?>" novalidate>
                <div class="card-body stack stack-4">
                    <?= Csrf::field() ?>
                    <div class="field-row">
                        <div class="field">
                            <label class="label" for="title">Heading</label>
                            <input class="input" id="title" name="title" value="<?= $v('title') ?>" required<?= $invalid('title') ?>>
                            <?php $field = 'title'; require __DIR__ . '/../_field-error.php'; ?>
                        </div>
                        <div class="field">
                            <label class="label" for="browser_title">Browser tab title</label>
                            <input class="input" id="browser_title" name="browser_title" value="<?= $v('browser_title') ?>" aria-describedby="browser_title-help"<?= $invalid('browser_title') ?>>
                            <p class="help" id="browser_title-help">Shows in the tab, history and bookmarks, where someone else may see it. Leave blank to use the heading if it is neutral; otherwise use something like “Keeping records”.</p>
                            <?php $field = 'browser_title'; require __DIR__ . '/../_field-error.php'; ?>
                        </div>
                        <div class="field">
                            <label class="label" for="slug">URL name</label>
                            <input class="input" id="slug" name="slug" value="<?= $v('slug') ?>" required<?= $invalid('slug') ?>>
                            <?php $field = 'slug'; require __DIR__ . '/../_field-error.php'; ?>
                        </div>
                    </div>
                    <div class="field">
                        <label class="label" for="summary">Summary for search results</label>
                        <input class="input" id="summary" name="summary" maxlength="300" value="<?= $v('summary') ?>" required<?= $invalid('summary') ?>>
                        <?php $field = 'summary'; require __DIR__ . '/../_field-error.php'; ?>
                    </div>
                    <div class="field">
                        <label class="label" for="body">Page text</label>
                        <textarea class="textarea mono" id="body" name="body" rows="22" required aria-describedby="body-help"<?= $invalid('body') ?>><?= $v('body') ?></textarea>
                        <p class="help" id="body-help">Plain text with: <code>## Heading</code>, <code>- bullet</code>, <code>1. step</code>, <code>**bold**</code>, <code>[link text](https://…)</code>. HTML is not allowed and shows as text. The hotline panel is added automatically.</p>
                        <?php $field = 'body'; require __DIR__ . '/../_field-error.php'; ?>
                    </div>
                    <div class="field-row">
                        <div class="field">
                            <label class="label" for="sort_order">Order</label>
                            <input class="input" id="sort_order" name="sort_order" inputmode="numeric" value="<?= $v('sort_order') ?>">
                        </div>
                        <label class="check self-end">
                            <input type="checkbox" name="is_published" value="1"<?= (int) ($values['is_published'] ?? 0) === 1 ? ' checked' : '' ?>>
                            <span>Published</span>
                        </label>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit"><?= $page === null ? 'Create page' : 'Save changes' ?></button>
                    </div>
                </div>
            </form>

            <div class="stack stack-6">
                <?php if (trim((string) ($values['body'] ?? '')) !== ''): ?>
                <section class="card" aria-labelledby="preview-title">
                    <div class="card-header"><h2 id="preview-title" class="card-title">Preview of saved text</h2></div>
                    <div class="card-body prose text-sm"><?= Markdown::toHtml((string) $values['body']) ?></div>
                </section>
                <?php endif; ?>

                <?php if ($page !== null): ?>
                <section class="card" aria-labelledby="delete-title">
                    <div class="card-body stack stack-3">
                        <h2 id="delete-title" class="card-title">Delete page</h2>
                        <p class="text-sm text-muted">Links to /resources/<?= Format::e($page['slug']) ?> will show “not found”. Unpublishing is usually better.</p>
                        <form id="delete-page" method="POST" action="/admin/resources/<?= (int) $page['id'] ?>/delete" data-confirm="confirm-delete-page">
                            <?= Csrf::field() ?>
                            <button class="btn btn-danger" type="submit"><?= Deck::icon('trash') ?> Delete</button>
                        </form>
                    </div>
                </section>
                <dialog class="modal" id="confirm-delete-page" aria-labelledby="confirm-delete-title">
                    <div class="modal-header"><h2 id="confirm-delete-title" class="modal-title">Delete this page?</h2></div>
                    <div class="modal-body"><p>This cannot be undone.</p></div>
                    <div class="modal-footer">
                        <button class="btn" type="button" data-modal-close>Cancel</button>
                        <button class="btn btn-danger" type="button" data-confirm-submit="delete-page">Delete</button>
                    </div>
                </dialog>
                <?php endif; ?>
            </div>
        </div>
<?php require __DIR__ . '/../_bottom.php'; ?>

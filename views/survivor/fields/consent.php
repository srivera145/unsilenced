<?php
/** The publishing choice: (a) statistics, (b) statistics and account, (c) private. Needs $values, $errors. */
use Keel\App\Support\Config;
use Keel\App\Support\Format;

$consentValue = (string) ($values['consent'] ?? '');
?>
<fieldset class="fieldset"<?= !empty($errors['consent']) ? ' aria-describedby="consent-error"' : '' ?>>
    <legend>What may we do with your report?</legend>
    <div class="stack stack-2">
        <?php foreach ((array) Config::get('survivor_reports.consents', []) as $key => $consent): ?>
        <label class="check check-card">
            <input type="radio" name="consent" value="<?= Format::e($key) ?>" required<?= $consentValue === (string) $key ? ' checked' : '' ?>>
            <span class="check-text">
                <span class="fw-semi"><?= Format::e($consent['label']) ?></span>
                <span class="check-note"><?= Format::e($consent['help']) ?></span>
            </span>
        </label>
        <?php endforeach; ?>
    </div>
    <?php $field = 'consent'; require __DIR__ . '/../_error.php'; ?>
</fieldset>
<p class="text-sm text-muted">Whatever you choose, we never publish your name, the names of anyone else, the time of year, an exact date or your evidence. You can change your mind later, or withdraw your report completely, from your page.</p>

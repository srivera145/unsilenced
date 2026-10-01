<?php
/** Who did this, as a category only. Needs $values, $errors. */
use Keel\App\Support\Config;
use Keel\App\Support\Format;

$whoValue = (string) ($values['perpetrator'] ?? '');
?>
<fieldset class="fieldset"<?= !empty($errors['perpetrator']) ? ' aria-describedby="perpetrator-error"' : '' ?>>
    <legend>Who did this?</legend>
    <p class="help">Choose the closest. Please do not write anyone's name, here or anywhere in your report.</p>
    <div class="stack stack-1">
        <?php foreach ((array) Config::get('survivor_reports.perpetrators', []) as $key => $label): ?>
        <label class="check">
            <input type="radio" name="perpetrator" value="<?= Format::e($key) ?>" required<?= $whoValue === (string) $key ? ' checked' : '' ?>>
            <span><?= Format::e($label) ?></span>
        </label>
        <?php endforeach; ?>
    </div>
    <?php $field = 'perpetrator'; require __DIR__ . '/../_error.php'; ?>
</fieldset>

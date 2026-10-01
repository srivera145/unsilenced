<?php
/** Year (required), time of year and setting. Needs $values, $errors. */
use Keel\App\Services\Survivor\ReportInput;
use Keel\App\Support\Config;
use Keel\App\Support\Format;

$yearValue = (int) ($values['incident_year'] ?? 0);
$seasonValue = (string) ($values['incident_season'] ?? '');
$settingValue = (string) ($values['setting'] ?? '');
?>
<div class="field">
    <label class="label" for="incident_year">The year it happened</label>
    <select class="select input-narrow" id="incident_year" name="incident_year" required aria-describedby="incident_year-help<?= !empty($errors['incident_year']) ? ' incident_year-error' : '' ?>"<?= !empty($errors['incident_year']) ? ' aria-invalid="true"' : '' ?>>
        <option value="">Choose a year</option>
        <?php for ($year = (int) gmdate('Y'); $year >= ReportInput::EARLIEST_YEAR; $year--): ?>
        <option value="<?= $year ?>"<?= $yearValue === $year ? ' selected' : '' ?>><?= $year ?></option>
        <?php endfor; ?>
    </select>
    <p class="help" id="incident_year-help">If you are not sure, your best guess is fine.</p>
    <?php $field = 'incident_year'; require __DIR__ . '/../_error.php'; ?>
</div>

<fieldset class="fieldset">
    <legend>The time of year (optional)</legend>
    <p class="help">Only the people who review reports see this. It is never published.</p>
    <div class="cluster">
        <?php foreach (['' => 'I would rather not say'] + (array) Config::get('survivor_reports.seasons', []) as $key => $label): ?>
        <label class="check">
            <input type="radio" name="incident_season" value="<?= Format::e($key) ?>"<?= $seasonValue === (string) $key ? ' checked' : '' ?>>
            <span><?= Format::e($label) ?></span>
        </label>
        <?php endforeach; ?>
    </div>
</fieldset>

<fieldset class="fieldset">
    <legend>Where it happened (optional)</legend>
    <div class="stack stack-1">
        <?php foreach ((array) Config::get('survivor_reports.settings', []) + ['' => 'I would rather not say'] as $key => $label): ?>
        <label class="check">
            <input type="radio" name="setting" value="<?= Format::e($key) ?>"<?= $settingValue === (string) $key ? ' checked' : '' ?>>
            <span><?= Format::e($label) ?></span>
        </label>
        <?php endforeach; ?>
    </div>
</fieldset>

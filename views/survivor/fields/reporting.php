<?php
/**
 * Reporting to the school and police. The yes and no follow-up groups are
 * both shown without JavaScript; report-form.js shows only the one that
 * applies. Needs $values, $errors.
 */
use Keel\App\Support\Config;
use Keel\App\Support\Format;

$config = (array) Config::get('survivor_reports', []);
$answer = (string) ($values['reported_to_school_answer'] ?? '');
$checkedIn = static fn (string $field, string $key): bool => in_array($key, (array) ($values[$field] ?? []), true);
$ratingValue = (string) ($values['response_rating'] ?? '');
$policeValue = (string) ($values['reported_to_police'] ?? '');
?>
<fieldset class="fieldset" id="reported-question"<?= !empty($errors['reported_to_school']) ? ' aria-describedby="reported_to_school-error"' : '' ?>>
    <legend>Did you report it to the school?</legend>
    <div class="cluster">
        <label class="check"><input type="radio" name="reported_to_school" value="yes" required<?= $answer === 'yes' ? ' checked' : '' ?>><span>Yes</span></label>
        <label class="check"><input type="radio" name="reported_to_school" value="no" required<?= $answer === 'no' ? ' checked' : '' ?>><span>No</span></label>
    </div>
    <?php $field = 'reported_to_school'; require __DIR__ . '/../_error.php'; ?>
</fieldset>

<div class="stack stack-4" data-if-reported="yes">
    <fieldset class="fieldset"<?= !empty($errors['school_channels']) ? ' aria-describedby="school_channels-error"' : '' ?>>
        <legend>If you did: who did you report it to? Choose any that apply.</legend>
        <div class="stack stack-1">
            <?php foreach ((array) ($config['school_channels'] ?? []) as $key => $label): ?>
            <label class="check"><input type="checkbox" name="school_channels[]" value="<?= Format::e($key) ?>"<?= $checkedIn('school_channels', (string) $key) ? ' checked' : '' ?>><span><?= Format::e($label) ?></span></label>
            <?php endforeach; ?>
        </div>
        <?php $field = 'school_channels'; require __DIR__ . '/../_error.php'; ?>
    </fieldset>

    <fieldset class="fieldset">
        <legend>What happened next? Choose any that apply (optional).</legend>
        <div class="stack stack-1">
            <?php foreach ((array) ($config['school_outcomes'] ?? []) as $key => $label): ?>
            <label class="check"><input type="checkbox" name="school_outcomes[]" value="<?= Format::e($key) ?>"<?= $checkedIn('school_outcomes', (string) $key) ? ' checked' : '' ?>><span><?= Format::e($label) ?></span></label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <fieldset class="fieldset">
        <legend>How well did the school respond? (optional)</legend>
        <div class="stack stack-1">
            <?php foreach ((array) ($config['ratings'] ?? []) as $key => $label): ?>
            <label class="check"><input type="radio" name="response_rating" value="<?= (int) $key ?>"<?= $ratingValue === (string) $key ? ' checked' : '' ?>><span><?= (int) $key ?>: <?= Format::e($label) ?></span></label>
            <?php endforeach; ?>
            <label class="check"><input type="radio" name="response_rating" value=""<?= $ratingValue === '' ? ' checked' : '' ?>><span>I would rather not rate it</span></label>
        </div>
    </fieldset>
</div>

<div class="stack stack-4" data-if-reported="no">
    <fieldset class="fieldset">
        <legend>If you did not: what made you decide not to? Choose any that apply (optional).</legend>
        <div class="stack stack-1">
            <?php foreach ((array) ($config['not_reported_reasons'] ?? []) as $key => $label): ?>
            <label class="check"><input type="checkbox" name="not_reported_reasons[]" value="<?= Format::e($key) ?>"<?= $checkedIn('not_reported_reasons', (string) $key) ? ' checked' : '' ?>><span><?= Format::e($label) ?></span></label>
            <?php endforeach; ?>
        </div>
    </fieldset>
</div>

<fieldset class="fieldset">
    <legend>Did you report it to the local police? (optional)</legend>
    <div class="stack stack-1">
        <?php foreach ((array) ($config['police'] ?? []) as $key => $label): ?>
        <label class="check"><input type="radio" name="reported_to_police" value="<?= Format::e($key) ?>"<?= $policeValue === (string) $key ? ' checked' : '' ?>><span><?= Format::e($label) ?></span></label>
        <?php endforeach; ?>
    </div>
</fieldset>

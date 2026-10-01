<?php
/**
 * Choosing the school. public_html/js/report-form.js searches /submit/schools
 * and lists results as radio buttons; the choice goes in school_id.
 * Needs $values, $errors, $school.
 */
use EchoDial\Deck\Deck;
use Keel\App\Support\Format;

$chosen = $school ?? null;
?>
<div class="stack stack-3" data-school-picker>
    <input type="hidden" name="school_id" id="school_id" value="<?= $chosen !== null ? (int) $chosen['id'] : '' ?>">

    <div class="chosen-school" id="school-chosen"<?= $chosen === null ? ' hidden' : '' ?>>
        <p class="stack stack-1">
            <span class="text-sm text-muted">Your school</span>
            <strong id="school-chosen-name"><?= $chosen !== null ? Format::e($chosen['name']) : '' ?></strong>
            <span class="text-sm" id="school-chosen-place"><?= $chosen !== null ? Format::e(trim(($chosen['city'] ?? '') . ', ' . $chosen['state'], ', ')) : '' ?></span>
        </p>
        <button type="button" class="btn btn-sm" id="school-change" hidden>Choose a different school</button>
    </div>

    <div class="field" id="school-search-field"<?= $chosen !== null ? ' hidden' : '' ?>>
        <label class="label" for="school-search">Search by the school's name or city</label>
        <div class="cluster cluster-tight">
            <div class="search grow">
                <?= Deck::icon('search') ?>
                <input class="input" type="search" id="school-search" autocomplete="off" spellcheck="false" aria-describedby="school-search-help<?= !empty($errors['school_id']) ? ' school_id-error' : '' ?>">
            </div>
            <button type="button" class="btn" id="school-search-button" hidden>Search</button>
        </div>
        <p class="help" id="school-search-help">Type at least two letters, then choose your school from the list.</p>
    </div>

    <div id="school-results"></div>
    <p id="school-status" class="text-sm text-muted" role="status" aria-live="polite"></p>
    <?php $field = 'school_id'; require __DIR__ . '/../_error.php'; ?>
</div>

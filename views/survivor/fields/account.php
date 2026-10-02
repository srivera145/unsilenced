<?php
/**
 * Their account, and the check for names and contact details. The check runs
 * when they move on (report-form.js posts to /submit/scan) and again on the
 * server when the form is sent; $segments holds the server's result when the
 * form is shown again. Needs $values, $errors, $segments.
 */
use EchoDial\Deck\Deck;
use Keel\App\Support\Config;
use Keel\App\Support\Format;

$maxAccount = (int) Config::get('survivor_reports.account_max_length', 5000);
$segments = $segments ?? [];
$accountText = (string) ($values['account'] ?? '');
?>
<div class="field">
    <label class="label" for="account">What happened, in your own words (optional)</label>
    <div class="help stack stack-2" id="account-help">
        <p>Write as much or as little as you want, or nothing at all. You might include what happened, what you told the school, and what the school did or did not do.</p>
        <p>Please leave out names, of the person who did this, of anyone who saw it, and of staff, and phone numbers, addresses, room numbers and fraternity or sorority names. Leave out roles that point to one person too, such as an RA, a TA, a coach, a team captain or a chapter officer. Write general words instead, such as "a student", "a student employee", "a staff member" or "the Title IX office".</p>
    </div>
    <textarea class="textarea" id="account" name="account" rows="12" maxlength="<?= $maxAccount ?>" spellcheck="true" aria-describedby="account-help account-count<?= !empty($errors['account']) ? ' account-error' : '' ?>"><?= Format::e($accountText) ?></textarea>
    <p class="help" id="account-count" data-max="<?= $maxAccount ?>">Up to <?= number_format($maxAccount) ?> characters.</p>
    <?php $field = 'account'; require __DIR__ . '/../_error.php'; ?>
</div>

<div class="alert alert-warn" id="scan-results" role="region" aria-labelledby="scan-title"<?= $segments === [] ? ' hidden' : '' ?>>
    <?= Deck::icon('alert-triangle') ?>
    <div class="stack stack-3 min-is-0">
        <h3 class="alert-title" id="scan-title" tabindex="-1">Please check these before you go on</h3>
        <p class="alert-body">Some words look like names, contact details or roles that could point to one person. They are highlighted below. You can change your account above to take them out, or tell us you have checked. Before anything is published, a person on our team also removes anything that could identify you or anyone else.</p>
        <div class="scan-preview" id="scan-preview"><?php foreach ($segments as $segment): ?><?php if ($segment['type'] === null): ?><?= Format::e($segment['text']) ?><?php else: ?><mark class="scan-mark"><?= Format::e($segment['text']) ?><span class="sr-only"> (<?= Format::e($segment['label']) ?>)</span></mark><?php endif; ?><?php endforeach; ?></div>
        <label class="check">
            <input type="checkbox" name="name_scan_confirmed" value="1" id="name_scan_confirmed"<?= (int) ($values['name_scan_confirmed'] ?? 0) === 1 ? ' checked' : '' ?><?= !empty($errors['name_scan_confirmed']) ? ' aria-describedby="name_scan_confirmed-error"' : '' ?>>
            <span>I have checked these. Keep my account as it is.</span>
        </label>
        <?php $field = 'name_scan_confirmed'; require __DIR__ . '/../_error.php'; ?>
    </div>
</div>

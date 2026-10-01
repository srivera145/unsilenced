<?php
/** Renders $errors[$field] under a form control. Set $field before requiring. */
if (!empty($errors[$field])): ?>
<p class="error" id="<?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?>-error"><?= htmlspecialchars((string) $errors[$field]) ?></p>
<?php endif;

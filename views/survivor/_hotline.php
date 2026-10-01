<?php
/** One line with the hotline, at the foot of every form step. */
$hotline = (array) \Keel\App\Support\Config::get('help', []);
?>
<p class="step-help">Want to talk to someone? Call <a href="tel:<?= htmlspecialchars((string) $hotline['hotline_tel'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $hotline['hotline_display']) ?></a>, free and confidential, any time. You can stop at any point: nothing is saved until you send.</p>

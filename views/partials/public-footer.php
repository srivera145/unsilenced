<?php
use Keel\App\Support\Config;
?>
<footer class="footer">
    <div class="container stack stack-4">
        <div class="footer-grid">
            <div class="footer-brand">
                <?php $logoLabel = (string) Config::get('site.name', 'Unsilenced'); require __DIR__ . '/logo.php'; ?>
                <p>Public data on how U.S. colleges handle sexual assault. We publish facts and their sources, not accusations.</p>
            </div>
            <nav class="footer-col" aria-label="Data">
                <p class="footer-heading">Data</p>
                <a href="/schools">Find a school</a>
                <a href="/states">States</a>
                <a href="/methodology">How we get our data</a>
            </nav>
            <nav class="footer-col" aria-label="Help">
                <p class="footer-heading">Help</p>
                <a href="/resources/get-help">Get help now</a>
                <a href="/resources/your-options">Your options</a>
                <a href="/resources/save-evidence">Save your evidence</a>
            </nav>
        </div>
        <p class="text-xs">Our public pages set no cookies and load nothing from other websites. Data: <?= htmlspecialchars((string) Config::get('sources.clery.short')) ?>; <?= htmlspecialchars((string) Config::get('sources.ipeds.short')) ?>.</p>
    </div>
</footer>

<?php
/**
 * The files behind an open share link: originals, metadata intact, each with
 * the SHA-256 and UTC time recorded when it was uploaded. "Download all" is a
 * ZIP with manifest.txt listing the same.
 */
use EchoDial\Deck\Deck;
use Keel\App\Models\EvidenceFile;
use Keel\App\Support\Config;
use Keel\App\Support\Format;
use Keel\Core\Csrf;

$quickExitSignOut = '/share/close';
$types = (array) Config::get('evidence.types', []);
$sizeLabel = static fn (int $bytes): string => $bytes >= 1048576 ? number_format($bytes / 1048576, 1) . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';

require __DIR__ . '/_top.php';
?>
        <header class="bar wrap">
            <div class="stack stack-1">
                <h1 class="h2">Shared files</h1>
                <p class="text-muted">This link works until <?= Format::e($link['expires_at']) ?> UTC.</p>
            </div>
            <form class="push" method="POST" action="/share/close">
                <?= Csrf::field() ?>
                <button class="btn btn-sm" type="submit"><?= Deck::icon('log-out') ?> Close</button>
            </form>
        </header>

        <section class="card" aria-labelledby="files-title">
            <div class="card-body stack stack-4">
                <h2 class="h5" id="files-title"><?= Format::plural(count($files), 'file') ?></h2>
                <p class="text-sm">Each file is exactly as it was uploaded, including details such as when and where a photo was taken. The <strong>SHA-256 fingerprint</strong> was calculated by our server from the file when it arrived, at the time shown (UTC). To check a download has not changed, calculate its SHA-256 and compare: <code>shasum -a 256 file</code> on macOS or Linux, <code>Get-FileHash file</code> in Windows PowerShell.</p>

                <?php if ($files === []): ?>
                <p>There are no files on this link any more. The person who shared it may have deleted them.</p>
                <?php else: ?>
                <div class="stack stack-1">
                    <p><a class="btn btn-primary btn-wrap" href="/share/download"><?= Deck::icon('download') ?> Download all as a ZIP</a></p>
                    <p class="text-sm text-muted">The ZIP includes manifest.txt, listing each file's fingerprint and upload time.</p>
                </div>
                <ul class="evidence-list">
                    <?php foreach ($files as $file): $name = EvidenceFile::name($file); ?>
                    <li class="evidence-item stack stack-2">
                        <div class="bar wrap">
                            <div class="stack stack-0 min-is-0">
                                <span class="fw-semi break-anywhere"><?= Format::e($name) ?></span>
                                <span class="text-sm text-muted"><?= Format::e($types[$file['kind']]['label'] ?? $file['kind']) ?> · <?= $sizeLabel((int) $file['size_bytes']) ?> · uploaded <?= Format::e($file['uploaded_at']) ?> UTC</span>
                            </div>
                            <a class="btn btn-sm push" href="/share/files/<?= (int) $file['id'] ?>"><?= Deck::icon('download') ?> Download</a>
                        </div>
                        <p class="text-xs">SHA-256 <code class="hash"><?= Format::e($file['sha256']) ?></code></p>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </section>

        <p class="text-sm text-muted">Unsilenced records only when this link was last opened: no address, device or anything else about you.</p>
<?php require __DIR__ . '/_bottom.php'; ?>

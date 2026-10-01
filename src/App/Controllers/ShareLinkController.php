<?php

namespace Keel\App\Controllers;

use Keel\App\Models\EvidenceFile;
use Keel\App\Services\Survivor\ShareLinkService;
use Keel\App\Services\Survivor\VaultService;
use Keel\App\Services\Survivor\ZipStream;
use Keel\App\Support\SurvivorSession;
use Keel\Core\Controller;
use Keel\Core\ErrorHandler;
use Keel\Core\Request;
use Keel\Core\Response;
use Keel\Core\View;

/**
 * /share: what the person she sends a link to sees.
 *
 * The link is /share#token. The fragment never reaches the server, so /share
 * itself is just a page whose script (public_html/js/share.js) reads the
 * token, removes it from the address bar and history, and posts it to
 * /share/open. Without JavaScript the same page has a box to paste the link
 * into. Once opened (and the passcode, if any, is right), the link's id sits
 * in this area's session for 30 idle minutes.
 *
 * Every failure looks the same, "This link is not available", whether the
 * token never existed, expired, was revoked or is locked.
 */
class ShareLinkController extends Controller
{
    public function show(Request $request): void
    {
        $this->view('survivor.share-open', [
            'title' => 'Files',
            'noindex' => true,
            'needsPasscode' => false,
            'token' => '',
            'error' => SurvivorSession::takeFlash('/share') === 'timed_out' ? 'This page closed after 30 minutes without activity. Open the link again.' : null,
        ]);
    }

    public function open(Request $request): void
    {
        $token = self::tokenFrom((string) $request->input('token', ''));
        $links = new ShareLinkService();
        $link = $links->findUsable($token);

        if ($link === null) {
            $this->unavailable();
        }

        if ($link['passcode_hash'] !== null) {
            $passcode = (string) $request->input('passcode', '');

            if ($passcode === '' || !$links->checkPasscode($link, $passcode)) {
                // A wrong passcode may have just locked it.
                if ($passcode !== '' && $links->findUsable($token) === null) {
                    $this->unavailable();
                }

                http_response_code($passcode === '' ? 200 : 422);
                $this->view('survivor.share-open', [
                    'title' => 'Files',
                    'noindex' => true,
                    'needsPasscode' => true,
                    'token' => $token,
                    'error' => $passcode === '' ? null : 'That passcode is not right. Check it with the person who sent you the link.',
                ]);

                return;
            }
        }

        SurvivorSession::openShareLink((int) $link['id']);
        $this->redirect('/share/files');
    }

    public function files(Request $request): void
    {
        $link = $this->current();
        $links = new ShareLinkService();
        $links->markOpened((int) $link['id']);

        $this->view('survivor.share-files', [
            'title' => 'Files',
            'noindex' => true,
            'link' => $link,
            'files' => $links->files((int) $link['id']),
        ]);
    }

    /** One original file, metadata intact. */
    public function download(Request $request, string $id): void
    {
        $link = $this->current();
        $file = $this->fileOfLink($link, (int) $id) ?? ErrorHandler::render(404);
        (new ShareLinkService())->markOpened((int) $link['id']);
        $vault = new VaultService();

        Response::stream(
            static fn (callable $write) => $vault->streamOriginal($file, $write),
            200,
            MyReportController::fileHeaders($file, EvidenceFile::name($file), false, (int) $file['size_bytes'])
        );
    }

    /** Every file, plus manifest.txt with each one's SHA-256 and upload time. */
    public function downloadAll(Request $request): void
    {
        $link = $this->current();
        $links = new ShareLinkService();
        $files = $links->files((int) $link['id']);
        $links->markOpened((int) $link['id']);
        $vault = new VaultService();
        $generatedAt = time();

        Response::stream(static function (callable $write) use ($files, $vault, $generatedAt): void {
            $zip = new ZipStream($write);
            $names = [];

            foreach ($files as $file) {
                $uploaded = (int) strtotime($file['uploaded_at'] . ' UTC');
                $zip->addFile(EvidenceFile::name($file), $uploaded, static function (callable $add) use ($vault, $file): void {
                    $vault->streamOriginal($file, $add);
                });
                $names[] = EvidenceFile::name($file);
            }

            $zip->addString('manifest.txt', self::manifest($files, $names, $generatedAt), $generatedAt);
            $zip->finish();
        }, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="files-' . gmdate('Y-m-d', $generatedAt) . '.zip"',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /** Closes the link in this browser. Also the quick exit's beacon here. */
    public function close(Request $request): void
    {
        SurvivorSession::clear('/share');

        if ($request->input('beacon') === '1') {
            Response::raw('', 204);
        }

        $this->redirect('/share');
    }

    /**
     * The manifest: how to check each file is exactly what was uploaded.
     * Times are the server's clock, in UTC, when the file arrived.
     */
    public static function manifest(array $files, array $names, int $generatedAt): string
    {
        $lines = [
            'Files shared through Unsilenced',
            'Generated: ' . gmdate('Y-m-d H:i:s', $generatedAt) . ' UTC',
            '',
            'Each file is exactly as it was uploaded, metadata included. The SHA-256',
            'fingerprint below was calculated by our server from the original bytes when',
            'the file arrived, and recorded with the server\'s time (UTC). To check a',
            'file has not changed, calculate its SHA-256 and compare:',
            '  macOS / Linux:  shasum -a 256 "file name"',
            '  Windows:        Get-FileHash "file name" -Algorithm SHA256',
            '',
        ];

        foreach ($files as $index => $file) {
            $lines[] = 'File:      ' . ($names[$index] ?? 'file');
            $lines[] = 'Size:      ' . number_format((int) $file['size_bytes']) . ' bytes';
            $lines[] = 'Uploaded:  ' . $file['uploaded_at'] . ' UTC';
            $lines[] = 'SHA-256:   ' . $file['sha256'];
            $lines[] = '';
        }

        return implode("\r\n", $lines);
    }

    /** Accepts the bare token or the whole link pasted into the box. */
    private static function tokenFrom(string $input): string
    {
        $input = trim($input);
        $hash = strrpos($input, '#');

        return $hash === false ? $input : substr($input, $hash + 1);
    }

    private function current(): array
    {
        $linkId = SurvivorSession::shareLinkId();
        $link = $linkId !== null ? (new ShareLinkService())->find($linkId) : null;

        if ($link === null) {
            SurvivorSession::clear('/share');
            $this->redirect('/share');
        }

        return $link;
    }

    private function fileOfLink(array $link, int $fileId): ?array
    {
        foreach ((new ShareLinkService())->files((int) $link['id']) as $file) {
            if ((int) $file['id'] === $fileId) {
                return $file;
            }
        }

        return null;
    }

    private function unavailable(): never
    {
        SurvivorSession::clear('/share');
        ob_start();
        View::render('survivor.share-unavailable', ['title' => 'Files', 'noindex' => true]);
        Response::raw((string) ob_get_clean(), 404, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}

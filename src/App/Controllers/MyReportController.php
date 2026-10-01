<?php

namespace Keel\App\Controllers;

use Keel\App\Models\EvidenceFile;
use Keel\App\Models\ModerationEvent;
use Keel\App\Models\School;
use Keel\App\Models\SurvivorCase;
use Keel\App\Models\SurvivorReport;
use Keel\App\Services\Survivor\CaseDeletionService;
use Keel\App\Services\Survivor\CaseKeyService;
use Keel\App\Services\Survivor\EvidenceRejected;
use Keel\App\Services\Survivor\NameScanService;
use Keel\App\Services\Survivor\ReportInput;
use Keel\App\Services\Survivor\ShareLinkService;
use Keel\App\Services\Survivor\VaultService;
use Keel\App\Support\Config;
use Keel\App\Support\SurvivorSession;
use Keel\App\Support\UploadedFiles;
use Keel\Core\Controller;
use Keel\Core\ErrorHandler;
use Keel\Core\Request;
use Keel\Core\Response;
use Keel\Core\Session;

/**
 * /my-report: her case key opens her report. From here she reads its status
 * and any note from us, edits it until it is approved, manages her evidence,
 * makes and revokes share links, and can withdraw everything.
 *
 * Nothing here is logged to the admin activity log (it records IP
 * addresses); her own actions go to the report's moderation history, which
 * holds no address and is deleted with the case.
 */
class MyReportController extends Controller
{
    private const NOTICES = [
        'timed_out' => 'You were signed out after 30 minutes without activity. Enter your key to continue.',
        'saved' => 'Your changes are saved.',
        'consent_saved' => 'Your publishing choice is saved.',
        'email_saved' => 'Your email setting is saved.',
        'evidence_added' => 'Your files are added.',
        'evidence_deleted' => 'The file is deleted.',
        'link_revoked' => 'The link is turned off. It no longer opens.',
        'signed_out' => 'You are signed out.',
    ];

    public function show(Request $request): void
    {
        $caseId = SurvivorSession::caseId();
        $notice = SurvivorSession::takeFlash('/my-report');

        if ($caseId === null || ($case = SurvivorCase::find($caseId)) === null) {
            SurvivorSession::signOut();
            $this->view('survivor.my-report-key', [
                'title' => 'Your page',
                'noindex' => true,
                'notice' => self::NOTICES[$notice] ?? null,
                'error' => null,
                'unknownWords' => [],
            ]);

            return;
        }

        $report = SurvivorReport::findByCase($caseId) ?? $this->gone();
        $files = EvidenceFile::forReport((int) $report['id']);
        $links = (new ShareLinkService())->forCase($caseId);

        $this->view('survivor.my-report', [
            'title' => 'Your page',
            'noindex' => true,
            'notice' => self::NOTICES[$notice] ?? null,
            'case' => $case,
            'report' => $report,
            'account' => SurvivorReport::account($report),
            'published' => SurvivorReport::published($report),
            'adminNote' => SurvivorReport::adminNote($report),
            'files' => $files,
            'links' => $links,
            'hasEmail' => !empty($case['email_encrypted']),
            'errors' => [],
            'fileErrors' => [],
        ]);
    }

    /** Key entry. */
    public function open(Request $request): void
    {
        $typed = mb_substr((string) $request->input('case_key', ''), 0, 300);
        $case = (new CaseKeyService())->findCase($typed);

        if ($case === null) {
            http_response_code(422);
            $this->view('survivor.my-report-key', [
                'title' => 'Your page',
                'noindex' => true,
                'notice' => null,
                'error' => 'That key did not open a report. Check each word and try again.',
                'unknownWords' => CaseKeyService::unknownWords($typed),
            ]);

            return;
        }

        SurvivorSession::signIn((int) $case['id']);
        $this->redirect('/my-report');
    }

    /** The sign-out button, and the quick exit's beacon on these pages. */
    public function signOut(Request $request): void
    {
        SurvivorSession::signOut();

        if ($request->input('beacon') === '1') {
            Response::raw('', 204);
        }

        SurvivorSession::flash('/my-report', 'signed_out');
        $this->redirect('/my-report');
    }

    public function edit(Request $request): void
    {
        [$case, $report] = $this->current();
        $this->requireEditable($report);

        $values = ReportInput::fromReport($report, SurvivorReport::account($report));
        $this->renderEdit($report, $values, [], []);
    }

    public function update(Request $request): void
    {
        [$case, $report] = $this->current();
        $this->requireEditable($report);

        $input = ReportInput::fromRequest($request);
        if ($input['errors'] !== []) {
            http_response_code(422);
            $this->renderEdit($report, $input['values'], $input['errors'], $input['findings']);

            return;
        }

        // Editing puts a report back in the queue (or keeps it private), and
        // whatever an admin had prepared for publishing no longer matches.
        $status = $input['values']['consent'] === 'private' ? 'private' : 'submitted';
        SurvivorReport::updateAnswers((int) $report['id'], $input['values'], $status);
        ModerationEvent::record((int) $report['id'], 'survivor', null, 'edited', (string) $report['status'], $status);

        if ($status === 'private') {
            SurvivorCase::forgetEmail((int) $case['id']);
        }

        SurvivorSession::flash('/my-report', 'saved');
        $this->redirect('/my-report');
    }

    /**
     * After approval she can still publish less: stop showing her account
     * (b → a), or leave the statistics and keep it private (→ c). Never more.
     */
    public function lowerConsent(Request $request): void
    {
        [$case, $report] = $this->current();
        $order = ['private' => 0, 'stats' => 1, 'stats_and_account' => 2];
        $wanted = (string) $request->input('consent', '');
        $currentLevel = $order[$report['consent']] ?? 0;

        if (!isset($order[$wanted]) || $order[$wanted] >= $currentLevel || $report['status'] === 'rejected') {
            $this->redirect('/my-report');
        }

        $columns = ['consent' => $wanted];
        if ($wanted === 'private') {
            $columns['status'] = 'private';
            SurvivorCase::forgetEmail((int) $case['id']);
        }
        if ($wanted !== 'stats_and_account') {
            $columns['published_encrypted'] = null;
        }

        SurvivorReport::write((int) $report['id'], $columns);
        ModerationEvent::record((int) $report['id'], 'survivor', null, 'consent_lowered', (string) $report['status'], (string) ($columns['status'] ?? $report['status']), ['consent' => $wanted]);

        SurvivorSession::flash('/my-report', 'consent_saved');
        $this->redirect('/my-report');
    }

    public function email(Request $request): void
    {
        [$case, $report] = $this->current();
        $email = trim((string) $request->input('email', ''));

        if ($request->input('remove') === '1' || $email === '') {
            SurvivorCase::forgetEmail((int) $case['id']);
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) !== false && in_array($report['status'], SurvivorReport::QUEUE, true)) {
            SurvivorCase::setEmail((int) $case['id'], mb_substr($email, 0, 254));
        } else {
            $this->renderDashboard($case, $report, ['email' => 'Enter an email address, or leave it empty to have none.']);

            return;
        }

        SurvivorSession::flash('/my-report', 'email_saved');
        $this->redirect('/my-report');
    }

    public function addEvidence(Request $request): void
    {
        [$case, $report] = $this->current();

        if (UploadedFiles::postTooLarge()) {
            $this->renderDashboard($case, $report, ['evidence' => 'Those files are too large to send at once. Add them a few at a time.']);

            return;
        }

        $files = UploadedFiles::from('evidence');
        $errors = [];
        $fileErrors = [];
        $max = (int) Config::get('evidence.max_files', 20);

        if ($files === []) {
            $errors['evidence'] = 'Choose at least one file.';
        } elseif (!in_array((string) $request->input('evidence_attest', ''), ['1', 'on'], true)) {
            $errors['evidence_attest'] = 'Please confirm you are not adding intimate images.';
        } elseif (EvidenceFile::countForReport((int) $report['id']) + count($files) > $max) {
            $errors['evidence'] = 'A report can have up to ' . $max . ' files. Delete some, or add fewer.';
        }

        if ($errors === []) {
            $vault = new VaultService();
            $added = 0;
            foreach ($files as $index => $file) {
                if ($file['error'] !== null) {
                    $fileErrors[$index] = $file['error'];
                    continue;
                }

                try {
                    EvidenceFile::create((int) $report['id'], $vault->store($file['tmp_name'], $file['name']));
                    $added++;
                } catch (EvidenceRejected $rejected) {
                    $fileErrors[$index] = $rejected->getMessage();
                }
            }

            if ($added > 0) {
                ModerationEvent::record((int) $report['id'], 'survivor', null, 'evidence_added', null, null, ['files' => $added]);
            }

            if ($fileErrors === []) {
                SurvivorSession::flash('/my-report', 'evidence_added');
                $this->redirect('/my-report');
            }
        }

        http_response_code(422);
        $this->renderDashboard($case, $report, $errors, $fileErrors, array_column($files, 'name'));
    }

    /** Her own file, as she uploaded it (metadata included: it is hers). */
    public function viewEvidence(Request $request, string $id): void
    {
        [$case, $report] = $this->current();
        $file = EvidenceFile::findForReport((int) $report['id'], (int) $id);

        if ($file === null || EvidenceFile::isQuarantined($file)) {
            ErrorHandler::render(404);
        }

        $vault = new VaultService();
        $inline = in_array($file['kind'], ['jpeg', 'png', 'pdf', 'text', 'mp3', 'm4a'], true) && $request->input('download') !== '1';

        Response::stream(
            static fn (callable $write) => $vault->streamOriginal($file, $write),
            200,
            self::fileHeaders($file, EvidenceFile::name($file), $inline, (int) $file['size_bytes'])
        );
    }

    public function deleteEvidence(Request $request, string $id): void
    {
        [$case, $report] = $this->current();
        $file = EvidenceFile::findForReport((int) $report['id'], (int) $id);

        // A quarantined file is held under the illegal-content policy.
        if ($file !== null && !EvidenceFile::isQuarantined($file)) {
            EvidenceFile::delete((int) $file['id']);
            (new VaultService())->deleteFiles($file);
            ModerationEvent::record((int) $report['id'], 'survivor', null, 'evidence_deleted');
        }

        SurvivorSession::flash('/my-report', 'evidence_deleted');
        $this->redirect('/my-report');
    }

    public function createShareLink(Request $request): void
    {
        [$case, $report] = $this->current();
        $links = new ShareLinkService();
        $errors = [];

        $visible = [];
        foreach (EvidenceFile::forReport((int) $report['id']) as $file) {
            if (!EvidenceFile::isQuarantined($file)) {
                $visible[(int) $file['id']] = true;
            }
        }

        $chosen = array_values(array_filter(
            array_map('intval', (array) $request->input('files', [])),
            static fn (int $id): bool => isset($visible[$id])
        ));
        $expiry = (string) $request->input('expiry', '');
        $label = mb_substr(trim((string) $request->input('label', '')), 0, 80);
        $passcode = (string) $request->input('passcode', '');

        if ($chosen === []) {
            $errors['files'] = 'Choose at least one file to share.';
        }
        if (!array_key_exists($expiry, (array) Config::get('survivor_reports.share_link_expiry', []))) {
            $errors['expiry'] = 'Choose how long the link should work.';
        }
        if ($passcode !== '' && mb_strlen($passcode) < 6) {
            $errors['passcode'] = 'Use at least 6 characters, or leave it empty.';
        }
        if ($links->countForCase((int) $case['id']) >= (int) Config::get('survivor_reports.share_links_max_per_case', 25)) {
            $errors['files'] = 'You have the most links allowed. Turn one off to make another.';
        }

        if ($errors !== []) {
            http_response_code(422);
            $this->renderDashboard($case, $report, $errors);

            return;
        }

        $created = $links->create((int) $case['id'], $chosen, $label, $expiry, $passcode === '' ? null : $passcode);
        ModerationEvent::record((int) $report['id'], 'survivor', null, 'share_link_created', null, null, ['files' => count($chosen), 'expiry' => $expiry]);

        $this->view('survivor.share-link-created', [
            'title' => 'Your page',
            'noindex' => true,
            'url' => ShareLinkService::url($created['token']),
            'label' => $label,
            'expiry' => (string) Config::get('survivor_reports.share_link_expiry.' . $expiry . '.label'),
            'fileCount' => count($chosen),
            'hasPasscode' => $passcode !== '',
        ]);
    }

    public function revokeShareLink(Request $request, string $id): void
    {
        [$case, $report] = $this->current();

        if ((new ShareLinkService())->revoke((int) $case['id'], (int) $id)) {
            ModerationEvent::record((int) $report['id'], 'survivor', null, 'share_link_revoked');
        }

        SurvivorSession::flash('/my-report', 'link_revoked');
        $this->redirect('/my-report');
    }

    /** Withdrawal, first confirmation: what will be deleted. */
    public function withdraw(Request $request): void
    {
        [$case, $report] = $this->current();

        $this->view('survivor.withdraw', [
            'title' => 'Your page',
            'noindex' => true,
            'report' => $report,
            'fileCount' => EvidenceFile::countForReport((int) $report['id']),
            'hasQuarantined' => $this->hasQuarantined((int) $report['id']),
            'final' => false,
        ]);
    }

    /** Second confirmation. Issues a one-time token the final step must carry. */
    public function confirmWithdraw(Request $request): void
    {
        [$case, $report] = $this->current();
        $token = bin2hex(random_bytes(16));
        Session::put('survivor./my-report.withdraw', $token);

        $this->view('survivor.withdraw', [
            'title' => 'Your page',
            'noindex' => true,
            'report' => $report,
            'fileCount' => EvidenceFile::countForReport((int) $report['id']),
            'hasQuarantined' => $this->hasQuarantined((int) $report['id']),
            'final' => true,
            'withdrawToken' => $token,
            'error' => null,
        ]);
    }

    public function destroy(Request $request): void
    {
        [$case, $report] = $this->current();
        $expected = (string) Session::get('survivor./my-report.withdraw', '');
        $given = (string) $request->input('withdraw_token', '');

        if ($expected === '' || !hash_equals($expected, $given) || !in_array((string) $request->input('understand', ''), ['1', 'on'], true)) {
            http_response_code(422);
            $this->view('survivor.withdraw', [
                'title' => 'Your page',
                'noindex' => true,
                'report' => $report,
                'fileCount' => EvidenceFile::countForReport((int) $report['id']),
                'hasQuarantined' => $this->hasQuarantined((int) $report['id']),
                'final' => true,
                'withdrawToken' => $expected !== '' ? $expected : (function (): string {
                    $token = bin2hex(random_bytes(16));
                    Session::put('survivor./my-report.withdraw', $token);

                    return $token;
                })(),
                'error' => 'To delete everything, tick the box to confirm.',
            ]);

            return;
        }

        (new CaseDeletionService())->delete((int) $case['id']);
        SurvivorSession::signOut();

        $this->view('survivor.withdrawn', ['title' => 'Your page', 'noindex' => true]);
    }

    // --- helpers -----------------------------------------------------------------

    /** @return array{0: array, 1: array} the signed-in case and its report, or a redirect to the key page */
    private function current(): array
    {
        $caseId = SurvivorSession::caseId();
        $case = $caseId !== null ? SurvivorCase::find($caseId) : null;
        $report = $case !== null ? SurvivorReport::findByCase((int) $case['id']) : null;

        if ($case === null || $report === null) {
            SurvivorSession::signOut();
            $this->redirect('/my-report');
        }

        return [$case, $report];
    }

    private function requireEditable(array $report): void
    {
        if (!in_array($report['status'], SurvivorReport::EDITABLE, true)) {
            $this->redirect('/my-report');
        }
    }

    private function hasQuarantined(int $reportId): bool
    {
        foreach (EvidenceFile::forReport($reportId) as $file) {
            if (EvidenceFile::isQuarantined($file)) {
                return true;
            }
        }

        return false;
    }

    private function renderEdit(array $report, array $values, array $errors, array $findings): void
    {
        $this->view('survivor.my-report-edit', [
            'title' => 'Your page',
            'noindex' => true,
            'report' => $report,
            'values' => $values,
            'errors' => $errors,
            'findings' => $findings,
            'segments' => $findings !== [] ? (new NameScanService())->segments((string) ($values['account'] ?? ''), $findings) : [],
            'school' => isset($values['school_id']) ? School::find((int) $values['school_id']) : null,
        ]);
    }

    private function renderDashboard(array $case, array $report, array $errors, array $fileErrors = [], array $fileNames = []): void
    {
        $this->view('survivor.my-report', [
            'title' => 'Your page',
            'noindex' => true,
            'notice' => null,
            'case' => $case,
            'report' => $report,
            'account' => SurvivorReport::account($report),
            'published' => SurvivorReport::published($report),
            'adminNote' => SurvivorReport::adminNote($report),
            'files' => EvidenceFile::forReport((int) $report['id']),
            'links' => (new ShareLinkService())->forCase((int) $case['id']),
            'hasEmail' => !empty($case['email_encrypted']),
            'errors' => $errors,
            'fileErrors' => $fileErrors,
            'fileNames' => $fileNames,
        ]);
    }

    private function gone(): never
    {
        SurvivorSession::signOut();
        $this->redirect('/my-report');
    }

    /**
     * Headers for sending an evidence file. The name in Content-Disposition
     * is ASCII-only plus an RFC 5987 UTF-8 copy; nosniff and, except for
     * PDFs (the browser's PDF viewer will not run in a sandbox), a sandboxed
     * CSP stop a browser treating a file as a page. $size is known for an
     * original (size_bytes); a stripped copy's size differs, so it is left out.
     */
    public static function fileHeaders(array $file, string $name, bool $inline, ?int $size = null): array
    {
        $ascii = (string) preg_replace('/[^A-Za-z0-9._ -]+/', '_', $name);
        $ascii = trim($ascii) === '' ? 'file' : $ascii;
        $mime = (string) $file['mime_type'] . ($file['kind'] === 'text' ? '; charset=utf-8' : '');

        $headers = [
            'Content-Type' => $mime,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name),
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ];

        if ($size !== null) {
            $headers['Content-Length'] = (string) $size;
        }

        if ($file['kind'] !== 'pdf') {
            $headers['Content-Security-Policy'] = "default-src 'none'; img-src 'self'; media-src 'self'; style-src 'unsafe-inline'; sandbox";
        }

        return $headers;
    }
}

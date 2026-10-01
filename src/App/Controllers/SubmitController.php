<?php

namespace Keel\App\Controllers;

use Keel\App\Models\EvidenceFile;
use Keel\App\Models\ModerationEvent;
use Keel\App\Models\School;
use Keel\App\Models\SurvivorCase;
use Keel\App\Models\SurvivorReport;
use Keel\App\Services\Survivor\CaseKeyService;
use Keel\App\Services\Survivor\EvidenceRejected;
use Keel\App\Services\Survivor\FileInspector;
use Keel\App\Services\Survivor\NameScanService;
use Keel\App\Services\Survivor\ProofOfWork;
use Keel\App\Services\Survivor\ReportInput;
use Keel\App\Services\Survivor\VaultService;
use Keel\App\Support\Config;
use Keel\App\Support\UploadedFiles;
use Keel\Core\Controller;
use Keel\Core\Csrf;
use Keel\Core\Database;
use Keel\Core\RateLimiter;
use Keel\Core\Request;

/**
 * /submit: the report form.
 *
 * The whole form is one page; its steps are shown one at a time by
 * public_html/js/report-form.js and its answers stay in the page. Nothing is
 * sent until the final submit, except, when she moves on from step 6, her
 * account to /submit/scan for the name check, which stores and logs nothing.
 * An abandoned form leaves nothing on the server.
 *
 * The final submit is checked in this order: CSRF, the honeypot, the proof of
 * work, the global rate limit, then her answers and files. Anything that
 * fails re-shows the form with her answers, so a timeout or a busy minute
 * never costs her what she wrote (files must be chosen again: browsers do not
 * allow a page to refill a file input).
 */
class SubmitController extends Controller
{
    private const HONEYPOT = 'website';

    public function show(Request $request): void
    {
        $values = [];
        $schoolId = trim((string) $request->input('school', ''));
        if (ctype_digit($schoolId) && ($school = School::find((int) $schoolId)) !== null) {
            $values['school_id'] = (int) $school['id'];
        }

        $this->renderForm($values, [], [], 1);
    }

    /** JSON for step 2's school search. Every school, not only those with Clery figures. */
    public function schools(Request $request): void
    {
        $query = mb_substr(trim((string) $request->input('q', '')), 0, 120);
        if (mb_strlen($query) < 2) {
            $this->json(['schools' => []]);
        }

        $results = School::search($query, null, 1, 12, false);
        $this->json(['schools' => array_map(static fn (array $school): array => [
            'id' => (int) $school['id'],
            'name' => (string) $school['name'],
            'place' => trim(($school['city'] ?? '') . ', ' . ($school['state'] ?? ''), ', '),
        ], $results['rows'])]);
    }

    /** Step 6's check, as JSON: her account cut into plain and flagged pieces. Nothing is kept. */
    public function scan(Request $request): void
    {
        $account = mb_substr(str_replace("\r\n", "\n", (string) $request->input('account', '')), 0, (int) Config::get('survivor_reports.account_max_length', 5000));
        $school = ctype_digit((string) $request->input('school_id', '')) ? School::find((int) $request->input('school_id')) : null;
        $ignore = $school !== null ? (preg_split('/[^\p{L}\']+/u', (string) $school['name']) ?: []) : [];

        $scanner = new NameScanService();
        $findings = $scanner->scan($account, $ignore);

        $this->json([
            'count' => count($findings),
            'segments' => $scanner->segments($account, $findings),
        ]);
    }

    public function store(Request $request): void
    {
        if (UploadedFiles::postTooLarge()) {
            http_response_code(413);
            $this->view('survivor.submit-too-large', ['title' => 'Share', 'noindex' => true]);

            return;
        }

        $token = (string) $request->input('_csrf', '');
        if (!Csrf::verify($token)) {
            $this->again($request, 'For your safety, this page timed out. Your answers are still here. Check them, then press Send again.');

            return;
        }

        if (trim((string) $request->input(self::HONEYPOT, '')) !== '') {
            $this->again($request, 'Something went wrong. Please press Send again.');

            return;
        }

        // A spent challenge means the same form sent twice (reload, or Back
        // then Forward). Her report went in the first time; never a second.
        $challenge = (string) $request->input('pow_challenge', '');
        if (ProofOfWork::isSpent($challenge)) {
            $this->view('survivor.submit-already', ['title' => 'Share', 'noindex' => true]);

            return;
        }

        if (!ProofOfWork::verify($challenge, (string) $request->input('pow_nonce', ''))) {
            $this->again($request, 'We could not check that this came from a person. Please press Send again; it takes a few seconds.');

            return;
        }

        if (!RateLimiter::attempt('survivor-submissions', (int) Config::get('survivor_reports.submissions_per_minute', 10), 1)) {
            $this->again($request, 'A lot of reports are arriving right now. Your answers are still here. Please wait a minute, then press Send again.');

            return;
        }

        $input = ReportInput::fromRequest($request);
        $values = $input['values'];
        $errors = $input['errors'];

        if (!in_array((string) $request->input('attest', ''), ['1', 'on'], true)) {
            $errors['attest'] = 'Please confirm this is true to the best of your knowledge.';
        }

        $email = trim((string) $request->input('email', ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter an email address, or leave this empty.';
        }
        // A private report has no updates to send.
        $email = $values['consent'] === 'private' || $email === '' ? null : mb_substr($email, 0, 254);

        $files = UploadedFiles::from('evidence');
        $fileErrors = $this->checkFiles($request, $files, 0, $errors);

        if ($errors !== [] || $fileErrors !== []) {
            $this->renderForm(
                $values + ['email' => $email ?? $request->input('email', '')],
                $errors,
                $input['findings'],
                ReportInput::firstErrorStep($errors + ($fileErrors !== [] ? ['evidence' => ''] : [])),
                $fileErrors,
                $files !== []
            );

            return;
        }

        $key = (new CaseKeyService())->generate();
        $this->create($key, $values, $email, $files);
        ProofOfWork::consume();

        $this->view('survivor.submit-key', [
            'title' => 'Share',
            'noindex' => true,
            'caseKey' => $key,
            'consent' => $values['consent'],
            'fileCount' => count($files),
        ]);
    }

    /**
     * Creates the case, the report and its files together: if any file fails
     * to store, nothing is kept, and the files already written are removed.
     */
    private function create(string $key, array $values, ?string $email, array $files): void
    {
        $keys = new CaseKeyService();
        $vault = new VaultService();
        $stored = [];
        $connection = Database::connection();
        $connection->beginTransaction();

        try {
            $caseId = SurvivorCase::create($keys->lookupId($key), $keys->hash($key), $email);
            $status = $values['consent'] === 'private' ? 'private' : 'submitted';
            $reportId = SurvivorReport::create($caseId, $values, $status);

            foreach ($files as $file) {
                $row = $vault->store($file['tmp_name'], $file['name']);
                $stored[] = $row;
                EvidenceFile::create($reportId, $row);
            }

            ModerationEvent::record($reportId, 'survivor', null, 'submitted', null, $status, ['files' => count($files)]);
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            foreach ($stored as $row) {
                $vault->deleteFiles($row);
            }

            throw $exception;
        }
    }

    /**
     * Checks chosen files before anything is created: the attestation, how
     * many, how big, and what they are. @return array<int, string> index => message
     */
    private function checkFiles(Request $request, array $files, int $alreadyStored, array &$errors): array
    {
        if ($files === []) {
            return [];
        }

        if (!in_array((string) $request->input('evidence_attest', ''), ['1', 'on'], true)) {
            $errors['evidence_attest'] = 'Please confirm you are not adding intimate images, or remove the files.';
        }

        $max = (int) Config::get('evidence.max_files', 20);
        if ($alreadyStored + count($files) > $max) {
            $errors['evidence'] = 'A report can have up to ' . $max . ' files.';
        }

        $fileErrors = [];
        foreach ($files as $index => $file) {
            if ($file['error'] !== null) {
                $fileErrors[$index] = $file['error'];
                continue;
            }

            try {
                if ($file['size'] > (int) Config::get('evidence.max_file_bytes', 20971520)) {
                    throw new EvidenceRejected('This file is larger than ' . (int) round(Config::get('evidence.max_file_bytes', 20971520) / 1048576) . ' MB, so it cannot be added.');
                }
                FileInspector::detect((string) file_get_contents($file['tmp_name']));
            } catch (EvidenceRejected $rejected) {
                $fileErrors[$index] = $rejected->getMessage();
            }
        }

        return $fileErrors;
    }

    /** Re-shows the form at the last step with her answers and one message. */
    private function again(Request $request, string $message): void
    {
        $input = ReportInput::fromRequest($request);
        $values = $input['values'] + ['email' => mb_substr(trim((string) $request->input('email', '')), 0, 254)];

        $this->renderForm($values, ['form' => $message], $input['findings'], 9, [], UploadedFiles::from('evidence') !== []);
    }

    private function renderForm(array $values, array $errors, array $findings, int $step, array $fileErrors = [], bool $filesWereChosen = false): void
    {
        if ($errors !== []) {
            http_response_code(422);
        }

        $school = isset($values['school_id']) && $values['school_id'] !== null ? School::find((int) $values['school_id']) : null;

        $this->view('survivor.submit', [
            'title' => 'Share',
            'noindex' => true,
            'values' => $values,
            'errors' => $errors,
            'findings' => $findings,
            'segments' => $findings !== [] ? (new NameScanService())->segments((string) ($values['account'] ?? ''), $findings) : [],
            'step' => $step,
            'school' => $school,
            'pow' => ProofOfWork::challenge(),
            'fileErrors' => $fileErrors,
            'filesWereChosen' => $filesWereChosen,
            'honeypot' => self::HONEYPOT,
            'postMaxBytes' => UploadedFiles::postMaxBytes(),
        ]);
    }
}

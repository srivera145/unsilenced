<?php

namespace Keel\App\Controllers\Admin;

use Keel\App\Controllers\MyReportController;
use Keel\App\Middleware\FreshOtpMiddleware;
use Keel\App\Models\EvidenceFile;
use Keel\App\Models\ModerationEvent;
use Keel\App\Models\SurvivorCase;
use Keel\App\Models\SurvivorReport;
use Keel\App\Models\User;
use Keel\App\Services\OtpService;
use Keel\App\Services\Survivor\NameScanService;
use Keel\App\Services\Survivor\RedactionCheck;
use Keel\App\Services\Survivor\StatusNotifier;
use Keel\App\Services\Survivor\VaultService;
use Keel\App\Services\Survivor\WordDiff;
use Keel\App\Support\Config;
use Keel\App\Support\Markdown;
use Keel\Core\Activity;
use Keel\Core\Request;
use Keel\Core\Response;
use Keel\Core\Session;

/**
 * The moderation queue: submitted → in review → approved, changes requested
 * or rejected.
 *
 * - Admins read the survivor's original account and edit only a separate published
 *   version, which may remove words and add placeholders, never anything new
 *   (RedactionCheck). Their original is never changed here.
 * - Approval needs every checklist item ticked, and every evidence file
 *   viewed when there is any.
 * - Evidence is shown only from the metadata-free copy, and only to an admin
 *   who entered an emailed code in the last 15 minutes (FreshOtpMiddleware).
 * - Every evidence view, edit, approval, rejection and request for changes
 *   goes to the activity log, with ids only.
 * - Private reports (consent c) are not here: no admin reads them.
 */
class ModerationController extends AdminController
{
    private const CHECKLIST = [
        'no_names' => 'No names or identifying details of anyone (including the person who did this, witnesses, staff)',
        'no_roles' => 'No role, title, team or position that could point to one person (such as RA, coach, TA, team captain or chapter officer): each is replaced with a general category',
        'no_survivor_details' => 'Nothing that could identify the survivor',
        'school_correct' => 'The school is correct',
        'consent_respected' => 'The survivor\'s publishing choice is respected',
    ];

    public function index(Request $request): void
    {
        $status = (string) $request->input('status', '');
        $status = in_array($status, [...SurvivorReport::QUEUE, 'approved', 'rejected'], true) ? $status : null;

        $this->view('admin.reports.index', [
            'title' => 'Queue',
            'status' => $status,
            'reports' => SurvivorReport::queue($status),
            'counts' => SurvivorReport::countsByStatus(),
        ]);
    }

    public function show(Request $request, string $id): void
    {
        $this->renderReview($this->report($id), []);
    }

    public function startReview(Request $request, string $id): void
    {
        $report = $this->report($id);

        if ($report['status'] === 'submitted') {
            $this->changeStatus($report, 'in_review', 'review_started');
        }

        $this->redirect('/admin/reports/' . (int) $report['id'] . '?status=review_started');
    }

    /** The published version: their words with things removed, never added. */
    public function savePublished(Request $request, string $id): void
    {
        $report = $this->report($id);
        $original = (string) SurvivorReport::account($report);
        $text = trim(str_replace("\r\n", "\n", (string) $request->input('published', '')));
        $text = mb_substr($text, 0, (int) Config::get('survivor_reports.account_max_length', 5000));

        $check = RedactionCheck::check($original, $text);
        if ($original === '' || !$check['ok']) {
            $this->renderReview($report, ['published' => $original === '' ? 'This report has no account to publish.' : 'The published version may only remove words from the survivor\'s account. Use one of the placeholders where something is taken out.'], [
                'publishedDraft' => $text,
                'redaction' => $check,
            ]);

            return;
        }

        SurvivorReport::savePublished((int) $report['id'], $text, (int) $this->adminId());
        ModerationEvent::record((int) $report['id'], 'admin', $this->adminId(), 'published_edited');
        Activity::log('survivor_report.published_edited', 'SurvivorReport', (int) $report['id']);

        $this->redirect('/admin/reports/' . (int) $report['id'] . '?status=published_saved');
    }

    public function saveNote(Request $request, string $id): void
    {
        $report = $this->report($id);
        $note = $this->text($request, 'note', 2000);

        SurvivorReport::write((int) $report['id'], ['admin_note_encrypted' => SurvivorReport::sealNote($note)]);
        ModerationEvent::record((int) $report['id'], 'admin', $this->adminId(), 'note_saved');
        Activity::log('survivor_report.note_saved', 'SurvivorReport', (int) $report['id']);

        if ($note !== '') {
            (new StatusNotifier())->notify((int) $report['case_id']);
        }

        $this->redirect('/admin/reports/' . (int) $report['id'] . '?status=note_saved');
    }

    public function requestChanges(Request $request, string $id): void
    {
        $report = $this->report($id);
        $note = $this->text($request, 'note', 2000);

        if ($note === '') {
            $this->renderReview($report, ['note' => 'Say what the survivor should change. They will see this note.']);

            return;
        }

        SurvivorReport::write((int) $report['id'], ['admin_note_encrypted' => SurvivorReport::sealNote($note)]);
        $this->changeStatus($report, 'changes_requested', 'changes_requested');

        $this->redirect('/admin/reports/' . (int) $report['id'] . '?status=changes_requested');
    }

    public function reject(Request $request, string $id): void
    {
        $report = $this->report($id);
        $note = $this->text($request, 'note', 2000);

        SurvivorReport::write((int) $report['id'], [
            'admin_note_encrypted' => SurvivorReport::sealNote($note),
            'rejected_at' => gmdate('Y-m-d H:i:s'),
            'reviewed_by' => $this->adminId(),
        ]);
        $this->changeStatus($report, 'rejected', 'rejected');
        SurvivorCase::forgetEmail((int) $report['case_id']);

        $this->redirect('/admin/reports/' . (int) $report['id'] . '?status=rejected');
    }

    public function approve(Request $request, string $id): void
    {
        $report = $this->report($id);
        $errors = [];

        $ticked = [];
        foreach (array_keys(self::CHECKLIST) as $item) {
            $ticked[$item] = $this->checked($request, 'check_' . $item);
        }
        if (in_array(false, $ticked, true)) {
            $errors['checklist'] = 'Tick every item on the checklist. If one is not true, request changes or edit the published version instead.';
        }

        $evidence = (string) $request->input('evidence_reviewed', '');
        $files = array_filter(EvidenceFile::forReport((int) $report['id']), [EvidenceFile::class, 'viewableByAdmin']);
        $unviewed = array_filter($files, static fn (array $file): bool => $file['reviewed_at'] === null);

        if (!in_array($evidence, ['yes', 'none'], true)) {
            $errors['evidence_reviewed'] = 'Say whether the evidence was reviewed.';
        } elseif ($evidence === 'none' && $files !== []) {
            $errors['evidence_reviewed'] = 'This report has evidence. View each file, then choose "Reviewed".';
        } elseif ($evidence === 'yes' && $files === []) {
            $errors['evidence_reviewed'] = 'There is no evidence to review. Choose "None provided".';
        } elseif ($evidence === 'yes' && $unviewed !== []) {
            $errors['evidence_reviewed'] = 'View every file before approving: ' . count($unviewed) . ' not viewed yet.';
        }

        if ($report['consent'] === 'stats_and_account' && trim((string) SurvivorReport::published($report)) === '') {
            $errors['published'] = 'The survivor chose to publish their account. Save a published version (removing anything identifying) before approving.';
        }

        if (!in_array($report['status'], SurvivorReport::QUEUE, true)) {
            $errors['checklist'] = 'Only a report in the queue can be approved.';
        }

        if ($errors !== []) {
            $this->renderReview($report, $errors, ['ticked' => $ticked, 'evidenceChoice' => $evidence]);

            return;
        }

        SurvivorReport::write((int) $report['id'], [
            'approved_at' => gmdate('Y-m-d H:i:s'),
            'reviewed_by' => $this->adminId(),
            'evidence_reviewed' => $evidence,
        ]);
        $this->changeStatus($report, 'approved', 'approved', ['checklist' => array_keys($ticked), 'evidence' => $evidence]);
        SurvivorCase::forgetEmail((int) $report['case_id']);

        $this->redirect('/admin/reports/' . (int) $report['id'] . '?status=approved');
    }

    /** The page for one file. Text is shown here; everything else loads from evidenceFile(). */
    public function viewEvidence(Request $request, string $id, string $fileId): void
    {
        $report = $this->report($id);
        $file = $this->viewableFile($report, $fileId);
        $text = null;

        if ($file['kind'] === 'text') {
            $text = (new VaultService())->readAdminCopy($file);
            $this->recordView($report, $file);
        }

        // No file name: it is metadata too ("jane-statement.pdf"). Passed as
        // 'evidence': View::render() uses $file itself.
        $this->view('admin.reports.evidence', [
            'title' => 'Review',
            'report' => $report,
            'evidence' => $file,
            'text' => $text,
        ]);
    }

    /** The metadata-free copy's bytes. Each fetch is an evidence view. */
    public function evidenceFile(Request $request, string $id, string $fileId): void
    {
        $report = $this->report($id);
        $file = $this->viewableFile($report, $fileId);
        $this->recordView($report, $file);
        $vault = new VaultService();

        Response::stream(
            static fn (callable $write) => $vault->streamAdminCopy($file, $write),
            200,
            MyReportController::fileHeaders($file, 'evidence-' . (int) $file['id'] . '.' . Config::get('evidence.types.' . $file['kind'] . '.extension', 'bin'), $file['kind'] !== 'heic')
        );
    }

    /**
     * "Report illegal content": the file can no longer be viewed by anyone,
     * here, by the survivor or through a share link, and stays encrypted. The steps
     * page follows.
     */
    public function quarantine(Request $request, string $id, string $fileId): void
    {
        $report = $this->report($id);
        $file = EvidenceFile::findForReport((int) $report['id'], (int) $fileId) ?? $this->notFound();

        EvidenceFile::quarantine((int) $file['id'], $this->adminId());
        ModerationEvent::record((int) $report['id'], 'admin', $this->adminId(), 'evidence_quarantined', null, null, ['file' => (int) $file['id']]);
        Activity::log('evidence_file.quarantined', 'EvidenceFile', (int) $file['id'], ['report_id' => (int) $report['id']]);

        $this->redirect('/admin/illegal-content?status=quarantined');
    }

    public function illegalContent(Request $request): void
    {
        $path = dirname(__DIR__, 4) . '/docs/ILLEGAL-CONTENT.md';

        $this->view('admin.reports.illegal-content', [
            'title' => 'Procedure',
            'html' => is_file($path) ? Markdown::toHtml((string) file_get_contents($path)) : '',
        ]);
    }

    // --- a fresh code before evidence --------------------------------------------

    public function showVerify(Request $request): void
    {
        $this->view('admin.verify', ['title' => 'Confirm it is you', 'sent' => false, 'error' => null]);
    }

    public function sendVerifyCode(Request $request): void
    {
        $user = User::find((int) $this->adminId());
        $result = (new OtpService())->requestCode((string) $user['email']);

        $this->view('admin.verify', [
            'title' => 'Confirm it is you',
            'sent' => $result['success'],
            'error' => $result['success'] ? null : (string) $result['message'],
        ]);
    }

    public function verify(Request $request): void
    {
        $user = User::find((int) $this->adminId());
        $result = (new OtpService())->verifyCode((string) $user['email'], trim((string) $request->input('code', '')));

        if (!$result['success']) {
            http_response_code(422);
            $this->view('admin.verify', ['title' => 'Confirm it is you', 'sent' => true, 'error' => 'That code is not right or has expired.']);

            return;
        }

        Session::put(FreshOtpMiddleware::SESSION_KEY, time());
        Activity::log('user.code_reentered');

        $next = (string) Session::get(FreshOtpMiddleware::RETURN_KEY, '');
        Session::forget(FreshOtpMiddleware::RETURN_KEY);
        $this->redirect(preg_match('#^/admin/reports/\d+/evidence/\d+(/file)?$#', $next) ? $next : '/admin/reports');
    }

    // --- helpers -----------------------------------------------------------------

    /** A report admins may see: any but private. */
    private function report(string $id): array
    {
        $report = ctype_digit($id) ? SurvivorReport::find((int) $id) : null;

        return $report !== null && $report['status'] !== 'private' ? $report : $this->notFound();
    }

    private function viewableFile(array $report, string $fileId): array
    {
        $file = ctype_digit($fileId) ? EvidenceFile::findForReport((int) $report['id'], (int) $fileId) : null;

        return $file !== null && EvidenceFile::viewableByAdmin($file) ? $file : $this->notFound();
    }

    private function recordView(array $report, array $file): void
    {
        EvidenceFile::markReviewed((int) $file['id'], $this->adminId());
        Activity::log('evidence_file.viewed', 'EvidenceFile', (int) $file['id'], ['report_id' => (int) $report['id']]);
    }

    private function changeStatus(array $report, string $status, string $event, array $details = []): void
    {
        SurvivorReport::write((int) $report['id'], ['status' => $status]);
        ModerationEvent::record((int) $report['id'], 'admin', $this->adminId(), $event, (string) $report['status'], $status, $details);
        Activity::log('survivor_report.' . $event, 'SurvivorReport', (int) $report['id'], $details);
        (new StatusNotifier())->notify((int) $report['case_id']);
    }

    private function renderReview(array $report, array $errors, array $extra = []): void
    {
        if ($errors !== []) {
            http_response_code(422);
        }

        $account = SurvivorReport::account($report);
        $published = SurvivorReport::published($report);
        $draft = $extra['publishedDraft'] ?? $published ?? $account ?? '';
        $files = EvidenceFile::forReport((int) $report['id']);
        $ignore = preg_split('/[^\p{L}\']+/u', (string) $report['school_name']) ?: [];
        $scanner = new NameScanService();
        $draftFindings = $draft !== '' ? $scanner->scan($draft, $ignore) : [];

        $this->view('admin.reports.show', [
            'title' => 'Review',
            'report' => $report,
            'account' => $account,
            'published' => $published,
            'draft' => $draft,
            'diff' => $account !== null && $published !== null ? WordDiff::sideBySide($account, $published) : null,
            'draftSegments' => $draftFindings !== [] ? $scanner->segments($draft, $draftFindings) : [],
            'redaction' => $extra['redaction'] ?? null,
            'adminNote' => SurvivorReport::adminNote($report),
            'files' => $files,
            'events' => ModerationEvent::forReport((int) $report['id']),
            'checklist' => self::CHECKLIST,
            'ticked' => $extra['ticked'] ?? [],
            'evidenceChoice' => $extra['evidenceChoice'] ?? '',
            'placeholders' => RedactionCheck::placeholders(),
            'errors' => $errors,
            'otpFresh' => FreshOtpMiddleware::isFresh(),
        ]);
    }
}

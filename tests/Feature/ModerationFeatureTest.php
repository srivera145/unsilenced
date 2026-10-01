<?php

declare(strict_types=1);

namespace Tests\Feature;

use Keel\App\Jobs\PurgeRejectedReports;
use Keel\App\Middleware\FreshOtpMiddleware;
use Keel\App\Models\EvidenceFile;
use Keel\App\Models\SurvivorCase;
use Keel\App\Models\SurvivorReport;
use Keel\App\Services\Survivor\CaseKeyService;
use Keel\Core\Database;
use Keel\Core\Session;
use Tests\Support\EvidenceFixtures;
use Tests\Support\SurvivorHelpers;
use Tests\TestCase;

/**
 * The admin side: the queue, redacting the published version, the approval
 * checklist, viewing evidence (metadata-free, behind a fresh code), and
 * quarantining a file.
 */
class ModerationFeatureTest extends TestCase
{
    use SurvivorHelpers;

    private const ALL_TICKED = [
        'check_no_names' => '1',
        'check_no_survivor_details' => '1',
        'check_school_correct' => '1',
        'check_consent_respected' => '1',
    ];

    private array $admin;
    private int $reportId;
    private int $caseId;
    private int $fileId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importFixtures();
        $this->enableSubmissions();

        $key = (string) $this->submitReport(['email' => 'me@example.org'], [[EvidenceFixtures::write(EvidenceFixtures::jpegWithGps(), '.jpg'), 'IMG_77.jpg']])['key'];
        $case = (new CaseKeyService())->findCase($key);
        $this->caseId = (int) $case['id'];
        $this->reportId = (int) SurvivorReport::findByCase($this->caseId)['id'];
        $this->fileId = (int) EvidenceFile::forReport($this->reportId)[0]['id'];
        $this->admin = $this->actingAsAdmin();
    }

    private function url(string $suffix = ''): string
    {
        return '/admin/reports/' . $this->reportId . $suffix;
    }

    private function freshCode(): void
    {
        Session::put(FreshOtpMiddleware::SESSION_KEY, time());
    }

    public function testTheQueueShowsSubmittedReportsAndNeverPrivateOnes(): void
    {
        $private = $this->makeReport(['consent' => 'private'], 'private');

        $queue = $this->get('/admin/reports')->body;
        self::assertStringContainsString('<title>Queue · Unsilenced</title>', $queue);
        self::assertStringContainsString($this->url(), $queue);
        self::assertStringNotContainsString('/admin/reports/' . $private['report_id'], $queue);
        self::assertStringContainsString('1 report is kept private by their authors', $queue);
        self::assertSame(404, $this->get('/admin/reports/' . $private['report_id'])->status, 'no admin reads a private report');
    }

    public function testTheReviewPageShowsHerAccountButNeverAFileName(): void
    {
        $page = $this->get($this->url())->body;

        self::assertStringContainsString('<title>Review · Unsilenced</title>', $page);
        self::assertStringContainsString(self::ACCOUNT, $page);
        self::assertStringContainsString('File 1 · Photo (JPEG)', $page);
        self::assertStringNotContainsString('IMG_77', $page, 'a file name is metadata too');
        self::assertStringContainsString('(never published)', $page);
    }

    public function testStartingReviewChangesTheStatusAndEmailsHer(): void
    {
        $this->post($this->url('/start-review'), ['_csrf' => $this->csrfToken()]);

        self::assertSame('in_review', SurvivorReport::find($this->reportId)['status']);
        $mail = $this->latestMailLog();
        self::assertStringContainsString('Subject: An update is ready', $mail);
        self::assertStringContainsString('me@example.org', $mail);
        self::assertStringNotContainsString('Fixture State', $mail, 'the email says nothing about the report');
        self::assertStringNotContainsString('Title IX', $mail);
    }

    public function testThePublishedVersionMayOnlyRemove(): void
    {
        $added = $this->post($this->url('/published'), ['_csrf' => $this->csrfToken(), 'published' => self::ACCOUNT . ' The dean covered it up.']);
        self::assertSame(422, $added->status);
        self::assertStringContainsString('<span class="flagged-phrase">dean</span>', $added->body);
        self::assertNull(SurvivorReport::find($this->reportId)['published_encrypted']);

        $bracket = $this->post($this->url('/published'), ['_csrf' => $this->csrfToken(), 'published' => '[he was expelled] In my second year']);
        self::assertSame(422, $bracket->status);

        $redacted = 'In my [date removed] I went to the Title IX office. They told me to wait.';
        self::assertSame(302, $this->post($this->url('/published'), ['_csrf' => $this->csrfToken(), 'published' => $redacted])->status);
        self::assertSame($redacted, SurvivorReport::published(SurvivorReport::find($this->reportId)));
        self::assertSame(self::ACCOUNT, SurvivorReport::account(SurvivorReport::find($this->reportId)), 'her original is unchanged');

        $page = $this->get($this->url())->body;
        self::assertStringContainsString('<del class="diff-del">second</del>', $page);
        self::assertStringContainsString('<ins class="diff-ins">[date removed]</ins>', $page);
        self::assertSame(1, (int) Database::connection()->query("SELECT COUNT(*) FROM activity_log WHERE action = 'survivor_report.published_edited'")->fetchColumn());
    }

    public function testEvidenceNeedsAFreshCodeAndShowsTheCopyWithoutGps(): void
    {
        Session::forget(FreshOtpMiddleware::SESSION_KEY);
        $redirect = $this->get($this->url('/evidence/' . $this->fileId));
        self::assertSame(302, $redirect->status);
        self::assertSame('/admin/verify', $redirect->header('Location'));
        self::assertSame(302, $this->get($this->url('/evidence/' . $this->fileId . '/file'))->status);

        // A code from 16 minutes ago is not fresh.
        Session::put(FreshOtpMiddleware::SESSION_KEY, time() - 16 * 60);
        self::assertSame(302, $this->get($this->url('/evidence/' . $this->fileId))->status);

        // The code round trip: email a code, enter it, land back on the file.
        $this->post('/admin/verify/send', ['_csrf' => $this->csrfToken()]);
        preg_match('/\b(\d{6})\b/', $this->latestMailLog(), $code);
        $verified = $this->post('/admin/verify', ['_csrf' => $this->csrfToken(), 'code' => $code[1]]);
        self::assertSame($this->url('/evidence/' . $this->fileId), $verified->header('Location'));

        self::assertSame(200, $this->get($this->url('/evidence/' . $this->fileId))->status);
        $copy = $this->get($this->url('/evidence/' . $this->fileId . '/file'));
        self::assertSame('image/jpeg', $copy->header('Content-Type'));
        self::assertArrayNotHasKey('GPS', EvidenceFixtures::exif($copy->body));
        self::assertArrayNotHasKey('IFD0', EvidenceFixtures::exif($copy->body));
        self::assertStringNotContainsString(EvidenceFixtures::PERSON, $copy->body);
        self::assertStringNotContainsString('IMG_77', (string) $copy->header('Content-Disposition'));

        self::assertNotNull(EvidenceFile::find($this->fileId)['reviewed_at']);
        self::assertSame(1, (int) Database::connection()->query("SELECT COUNT(*) FROM activity_log WHERE action = 'evidence_file.viewed' AND subject_id = {$this->fileId}")->fetchColumn());
    }

    public function testApprovalNeedsTheWholeChecklistAndEveryFileViewed(): void
    {
        $missing = $this->post($this->url('/approve'), ['_csrf' => $this->csrfToken(), 'evidence_reviewed' => 'yes'] + array_slice(self::ALL_TICKED, 0, 3, true));
        self::assertSame(422, $missing->status);
        self::assertStringContainsString('Tick every item on the checklist', $missing->body);

        $unviewed = $this->post($this->url('/approve'), ['_csrf' => $this->csrfToken(), 'evidence_reviewed' => 'yes'] + self::ALL_TICKED);
        self::assertStringContainsString('View every file before approving: 1 not viewed yet', $unviewed->body);

        $none = $this->post($this->url('/approve'), ['_csrf' => $this->csrfToken(), 'evidence_reviewed' => 'none'] + self::ALL_TICKED);
        self::assertStringContainsString('This report has evidence', $none->body);

        $this->freshCode();
        $this->get($this->url('/evidence/' . $this->fileId . '/file'));
        $noPublished = $this->post($this->url('/approve'), ['_csrf' => $this->csrfToken(), 'evidence_reviewed' => 'yes'] + self::ALL_TICKED);
        self::assertStringContainsString('Save a published version', $noPublished->body);
        self::assertSame('submitted', SurvivorReport::find($this->reportId)['status']);
    }

    public function testApprovingPublishesWhatSheChoseEmailsHerAndDeletesHerAddress(): void
    {
        $this->freshCode();
        $this->get($this->url('/evidence/' . $this->fileId . '/file'));
        $this->post($this->url('/published'), ['_csrf' => $this->csrfToken(), 'published' => 'I went to the Title IX office. They told me to wait.']);

        $approved = $this->post($this->url('/approve'), ['_csrf' => $this->csrfToken(), 'evidence_reviewed' => 'yes'] + self::ALL_TICKED);
        self::assertSame(302, $approved->status);

        $report = SurvivorReport::find($this->reportId);
        self::assertSame('approved', $report['status']);
        self::assertSame('yes', $report['evidence_reviewed']);
        self::assertNotNull($report['approved_at']);
        self::assertNull(SurvivorCase::find($this->caseId)['email_encrypted'], 'her address is deleted once approved');
        self::assertStringContainsString('An update is ready', $this->latestMailLog());

        $details = json_decode((string) Database::connection()->query("SELECT details FROM moderation_events WHERE event = 'approved'")->fetchColumn(), true);
        self::assertSame(['no_names', 'no_survivor_details', 'school_correct', 'consent_respected'], $details['checklist']);
        self::assertSame(1, (int) Database::connection()->query("SELECT COUNT(*) FROM activity_log WHERE action = 'survivor_report.approved'")->fetchColumn());

        $school = $this->get('/schools/ny/fixture-state-university')->body;
        self::assertStringContainsString('I went to the Title IX office. They told me to wait.', $school);
        self::assertStringContainsString('Evidence on file', $school);
        self::assertStringNotContainsString('Fall', $school, 'never the season');
        self::assertStringNotContainsString('In my second year', $school, 'never her unedited account');
    }

    public function testRequestingChangesNeedsANoteSheThenSees(): void
    {
        self::assertSame(422, $this->post($this->url('/request-changes'), ['_csrf' => $this->csrfToken(), 'note' => ''])->status);

        $this->post($this->url('/request-changes'), ['_csrf' => $this->csrfToken(), 'note' => 'Please take out the name of the residence hall.']);
        $report = SurvivorReport::find($this->reportId);
        self::assertSame('changes_requested', $report['status']);
        self::assertStringNotContainsString('residence hall', (string) $report['admin_note_encrypted'], 'the note is encrypted too');
        self::assertSame('Please take out the name of the residence hall.', SurvivorReport::adminNote($report));
    }

    public function testARejectedReportIsDeletedWithEverythingAfterThirtyDays(): void
    {
        $this->post($this->url('/reject'), ['_csrf' => $this->csrfToken(), 'note' => 'We could not publish this.']);
        self::assertSame('rejected', SurvivorReport::find($this->reportId)['status']);
        self::assertNull(SurvivorCase::find($this->caseId)['email_encrypted']);

        self::assertSame(0, (new PurgeRejectedReports())->run(), 'not before 30 days');
        Database::connection()->prepare('UPDATE survivor_reports SET rejected_at = ? WHERE id = ?')->execute([gmdate('Y-m-d H:i:s', time() - 31 * 86400), $this->reportId]);

        self::assertSame(1, (new PurgeRejectedReports())->run());
        self::assertNull(SurvivorCase::find($this->caseId));
        self::assertSame([], $this->vaultFiles());
    }

    public function testQuarantineStopsEveryViewAndKeepsTheFileThroughWithdrawal(): void
    {
        $this->freshCode();
        $quarantine = $this->post($this->url('/evidence/' . $this->fileId . '/quarantine'), ['_csrf' => $this->csrfToken()]);
        self::assertSame('/admin/illegal-content?status=quarantined', $quarantine->header('Location'));

        $file = EvidenceFile::find($this->fileId);
        self::assertNotNull($file['quarantined_at']);
        self::assertSame(404, $this->get($this->url('/evidence/' . $this->fileId))->status);
        self::assertSame(404, $this->get($this->url('/evidence/' . $this->fileId . '/file'))->status);
        self::assertStringContainsString('NCMEC', $this->get('/admin/illegal-content')->body);
        self::assertSame(1, (int) Database::connection()->query("SELECT COUNT(*) FROM activity_log WHERE action = 'evidence_file.quarantined'")->fetchColumn());

        // Withdrawn: the quarantined file is kept, encrypted, with nothing linking it to her.
        (new \Keel\App\Services\Survivor\CaseDeletionService())->delete($this->caseId);
        $kept = EvidenceFile::find($this->fileId);
        self::assertNull($kept['report_id']);
        self::assertSame('', $kept['name_encrypted']);
        self::assertNull(SurvivorCase::find($this->caseId));
        self::assertCount(2, $this->vaultFiles());
    }
}

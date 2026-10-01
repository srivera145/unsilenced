<?php

declare(strict_types=1);

namespace Tests\Feature;

use Keel\App\Models\EvidenceFile;
use Keel\App\Models\SurvivorCase;
use Keel\App\Models\SurvivorReport;
use Keel\App\Services\Survivor\CaseKeyService;
use Keel\App\Services\Survivor\ProofOfWork;
use Keel\App\Services\Survivor\VaultService;
use Keel\App\Support\Config;
use Keel\App\Support\SurvivorSession;
use Keel\Core\Database;
use Tests\Support\EvidenceFixtures;
use Tests\Support\SurvivorHelpers;
use Tests\TestCase;

/**
 * A survivor's whole path: the form, her key, her page, her evidence, her
 * edits, and withdrawing everything.
 */
class SurvivorReportFlowFeatureTest extends TestCase
{
    use SurvivorHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importFixtures();
        $this->enableSubmissions();
    }

    public function testTheFormIsOnePageOfNineStepsWithTheHotlineOnEach(): void
    {
        $page = $this->get('/submit')->body;

        self::assertStringContainsString('<title>Share · Unsilenced</title>', $page);
        self::assertSame(9, substr_count($page, 'class="card form-step"'));
        self::assertSame(9, substr_count($page, 'class="step-help"'));
        self::assertStringContainsString('autocomplete="off"', $page);
        self::assertStringContainsString('name="pow_challenge"', $page);
        self::assertStringContainsString('class="hp-field" aria-hidden="true"', $page);
        self::assertStringContainsString('I am not uploading nude, sexual or intimate images or video.', $page);
        self::assertStringContainsString('href="/resources/save-evidence"', $page);
        self::assertStringContainsString('Nothing is saved until you press Send at the end.', $page);
        self::assertStringNotContainsString('class="site-nav"', $page, 'no navigation to leave the form by accident');
    }

    public function testASchoolCanBeChosenFromAPageLink(): void
    {
        $school = $this->school(990002);
        $page = $this->get('/submit?school=' . $school['id'])->body;

        self::assertStringContainsString('name="school_id" id="school_id" value="' . $school['id'] . '"', $page);
        self::assertStringContainsString('Fixture Polytechnic Institute', $page);
    }

    public function testSchoolSearchAndTheNameCheckAnswerJsonAndStoreNothing(): void
    {
        $this->get('/submit');
        $schools = $this->get('/submit/schools?q=Fixture%20Poly')->json();
        self::assertSame('Fixture Polytechnic Institute', $schools['schools'][0]['name']);
        self::assertSame('Troy, NY', $schools['schools'][0]['place']);
        self::assertSame([], $this->get('/submit/schools?q=F')->json()['schools']);

        $scan = $this->postJson('/submit/scan', ['account' => 'I told Jane Doe at 555-123-4567.'], ['X-CSRF-Token' => $this->csrfToken()])->json();
        self::assertSame(2, $scan['count']);
        self::assertSame('I told Jane Doe at 555-123-4567.', implode('', array_column($scan['segments'], 'text')));

        self::assertSame(419, $this->postJson('/submit/scan', ['account' => 'x'])->status, 'CSRF-protected');
        self::assertSame(0, (int) Database::connection()->query('SELECT COUNT(*) FROM survivor_cases')->fetchColumn());
    }

    public function testSubmittingCreatesTheReportShowsTheKeyOnceAndStoresOnlyHashes(): void
    {
        $photo = EvidenceFixtures::write(EvidenceFixtures::jpegWithGps(), '.jpg');
        ['response' => $response, 'key' => $key] = $this->submitReport(['email' => 'me@example.org'], [[$photo, 'IMG_2041.jpg']]);

        self::assertSame(200, $response->status, $response->body);
        self::assertNotNull($key, 'the key page lists six words');
        self::assertStringContainsString('Save this somewhere safe. We cannot recover it.', $response->body);
        self::assertStringContainsString('id="key-check"', $response->body);
        self::assertStringContainsString('Type the last two words of your key', $response->body);

        $case = Database::connection()->query('SELECT * FROM survivor_cases')->fetch();
        self::assertSame((new CaseKeyService())->lookupId($key), $case['lookup_id']);
        self::assertStringStartsWith('$argon2id$', $case['key_hash']);
        self::assertSame('me@example.org', SurvivorCase::email($case));

        $report = SurvivorReport::findByCase((int) $case['id']);
        self::assertSame('submitted', $report['status']);
        self::assertSame('stats_and_account', $report['consent']);
        self::assertSame('fall', $report['incident_season']);
        self::assertSame('title_ix', $report['school_channels']);
        self::assertSame('no_response,discouraged', $report['school_outcomes']);
        self::assertSame(self::ACCOUNT, SurvivorReport::account($report));

        // Nothing she wrote, and not her key, is in the database in plain text.
        $dump = json_encode(Database::connection()->query('SELECT * FROM survivor_cases')->fetchAll())
            . json_encode(Database::connection()->query('SELECT * FROM survivor_reports')->fetchAll())
            . json_encode(Database::connection()->query('SELECT * FROM evidence_files')->fetchAll())
            . json_encode(Database::connection()->query('SELECT * FROM moderation_events')->fetchAll());
        foreach ([$key, 'Title IX office told me', 'me@example.org', 'IMG_2041'] as $secret) {
            self::assertStringNotContainsString($secret, $dump, $secret);
        }
        foreach (explode(' ', $key) as $word) {
            self::assertStringNotContainsString('"' . $word . '"', $dump);
        }

        $file = EvidenceFile::forReport((int) $report['id'])[0];
        self::assertSame('jpeg', $file['kind']);
        self::assertSame(hash('sha256', EvidenceFixtures::jpegWithGps()), $file['sha256']);
        self::assertSame('IMG_2041.jpg', EvidenceFile::name($file));
    }

    public function testTheSameFormArrivingTwiceSaysItWasAlreadySent(): void
    {
        $this->get('/submit');
        $challenge = ProofOfWork::challenge();
        $data = $this->answers() + [
            '_csrf' => $this->csrfToken(),
            'pow_challenge' => $challenge['challenge'],
            'pow_nonce' => ProofOfWork::solve($challenge['challenge'], $challenge['bits']),
        ];

        self::assertNotNull(self::keyFrom($this->post('/submit', $data)->body));
        $this->get('/submit'); // Back to the form: a new challenge is issued
        $second = $this->post('/submit', $data);

        self::assertStringContainsString('Your report was already sent', $second->body);
        self::assertNull(self::keyFrom($second->body), 'the key is never shown again');
        self::assertSame(1, (int) Database::connection()->query('SELECT COUNT(*) FROM survivor_reports')->fetchColumn());
    }

    public function testAnExpiredSessionKeepsHerAnswersInsteadOfLosingThem(): void
    {
        $this->get('/submit');
        $response = $this->post('/submit', $this->answers(['account' => 'What I wrote over an hour.']) + ['_csrf' => 'stale-token']);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('this page timed out. Your answers are still here', $response->body);
        self::assertStringContainsString('What I wrote over an hour.</textarea>', $response->body);
        self::assertStringContainsString('data-start-step="9"', $response->body);
        self::assertSame(0, (int) Database::connection()->query('SELECT COUNT(*) FROM survivor_reports')->fetchColumn());
    }

    public function testNoProofOfWorkTheHoneypotAndTheRateLimitStoreNothing(): void
    {
        $this->get('/submit');
        $noProof = $this->post('/submit', $this->answers() + ['_csrf' => $this->csrfToken(), 'pow_challenge' => ProofOfWork::challenge()['challenge'], 'pow_nonce' => '']);
        self::assertStringContainsString('We could not check that this came from a person', $noProof->body);
        self::assertStringContainsString(self::ACCOUNT, $noProof->body, 'her account is still in the form');

        $bot = $this->submitReport(['website' => 'http://spam.example']);
        self::assertNull($bot['key']);

        Config::set('survivor_reports.submissions_per_minute', 2);
        self::assertNotNull($this->submitReport()['key']);
        self::assertNotNull($this->submitReport()['key']);
        $limited = $this->submitReport();
        self::assertNull($limited['key']);
        self::assertStringContainsString('A lot of reports are arriving right now', $limited['response']->body);

        self::assertSame(2, (int) Database::connection()->query('SELECT COUNT(*) FROM survivor_reports')->fetchColumn());
        $limits = Database::connection()->query('SELECT `key` FROM rate_limits')->fetchAll(\PDO::FETCH_COLUMN);
        self::assertSame(['survivor-submissions'], $limits, 'the limit is global: no IP address in the key');
    }

    public function testMissingAnswersReopenTheFormAtTheFirstProblem(): void
    {
        $response = $this->submitReport(['perpetrator' => '', 'reported_to_school' => 'yes', 'school_channels' => [], 'attest' => ''])['response'];

        self::assertSame(422, $response->status);
        self::assertStringContainsString('data-start-step="4"', $response->body);
        self::assertStringContainsString('Choose who you reported to at the school.', $response->body);
        self::assertStringContainsString('Please confirm this is true', $response->body);
    }

    public function testNamesInTheAccountMustBeRemovedOrConfirmed(): void
    {
        $account = 'Tyler from Sigma Chi called me from 555-123-4567.';
        $flagged = $this->submitReport(['account' => $account]);

        self::assertNull($flagged['key']);
        self::assertStringContainsString('data-start-step="6"', $flagged['response']->body);
        self::assertStringContainsString('<mark class="scan-mark">Tyler<span class="sr-only"> (a name)</span></mark>', $flagged['response']->body);
        self::assertStringContainsString('<mark class="scan-mark">Sigma Chi', $flagged['response']->body);

        $confirmed = $this->submitReport(['account' => $account, 'name_scan_confirmed' => '1']);
        self::assertNotNull($confirmed['key']);
        self::assertSame(1, (int) Database::connection()->query('SELECT name_scan_confirmed FROM survivor_reports')->fetchColumn());
    }

    public function testAPrivateReportIsNeverQueuedAndKeepsNoEmail(): void
    {
        $key = $this->submitReport(['consent' => 'private', 'email' => 'me@example.org'])['key'];
        $case = (new CaseKeyService())->findCase((string) $key);

        self::assertNull($case['email_encrypted']);
        self::assertSame('private', SurvivorReport::findByCase((int) $case['id'])['status']);
    }

    public function testFilesNeedTheAttestationAndAreJudgedByTheirBytes(): void
    {
        $photo = EvidenceFixtures::write(EvidenceFixtures::jpegWithGps(), '.jpg');
        $refused = $this->submitReport(['evidence_attest' => ''], [[$photo, 'photo.jpg']]);
        self::assertNull($refused['key']);
        self::assertStringContainsString('Please confirm you are not adding intimate images', $refused['response']->body);
        self::assertStringContainsString('Please choose your files again', $refused['response']->body);

        $video = EvidenceFixtures::write(EvidenceFixtures::read('video.mp4'), '.m4a');
        $videoRefused = $this->submitReport([], [[$video, 'recording.m4a']]);
        self::assertNull($videoRefused['key']);
        self::assertStringContainsString('Video cannot be added here', $videoRefused['response']->body);
        self::assertSame([], $this->vaultFiles(), 'nothing was written');

        // A photo named .pdf is stored as the photo it is.
        $renamed = $this->submitReport([], [[$photo, 'statement.pdf']]);
        self::assertNotNull($renamed['key']);
        self::assertSame('jpeg', Database::connection()->query('SELECT kind FROM evidence_files')->fetchColumn());
    }

    public function testHerKeyOpensHerPageInAnyCaseOrSpacing(): void
    {
        $key = (string) $this->submitReport()['key'];

        $words = array_slice(CaseKeyService::words(), 100, 5);
        $wrong = $this->openReport(implode(' ', $words) . ' zzzz');
        self::assertSame(422, $wrong->status);
        self::assertStringContainsString('That key did not open a report', $wrong->body);
        self::assertStringContainsString('Our keys never use &quot;zzzz&quot;', $wrong->body);

        $open = $this->openReport('  ' . strtoupper(str_replace(' ', ' - ', $key)) . ' ');
        self::assertSame(302, $open->status);
        self::assertSame('/my-report', $open->header('Location'));

        $page = $this->get('/my-report')->body;
        self::assertStringContainsString('<title>Your page · Unsilenced</title>', $page);
        self::assertStringContainsString('Waiting for review', $page);
        self::assertStringContainsString('Fixture State University', $page);
        self::assertStringContainsString('data-signout="/my-report/sign-out"', $page, 'quick exit closes her page as she leaves');
    }

    public function testThirtyIdleMinutesSignHerOut(): void
    {
        $this->openReport((string) $this->submitReport()['key']);
        self::assertStringContainsString('Your report', $this->get('/my-report')->body);

        SurvivorSession::touch('/my-report', time() - 31 * 60);
        $page = $this->get('/my-report')->body;

        self::assertStringContainsString('You were signed out after 30 minutes without activity', $page);
        self::assertNull(SurvivorSession::caseId());
        self::assertSame(302, $this->get('/my-report/edit')->status);
    }

    public function testEditingPutsTheReportBackForReviewAndClearsThePublishedVersion(): void
    {
        $key = (string) $this->submitReport()['key'];
        $case = (new CaseKeyService())->findCase($key);
        $report = SurvivorReport::findByCase((int) $case['id']);
        SurvivorReport::write((int) $report['id'], ['status' => 'changes_requested']);
        SurvivorReport::savePublished((int) $report['id'], 'In my second year', (int) $this->createUser()['id']);

        $this->openReport($key);
        $form = $this->get('/my-report/edit')->body;
        self::assertStringContainsString(self::ACCOUNT, $form);

        $saved = $this->post('/my-report/edit', $this->answers(['account' => 'A shorter account.', 'incident_year' => '2022']) + ['_csrf' => $this->csrfToken()]);
        self::assertSame(302, $saved->status);

        $report = SurvivorReport::find((int) $report['id']);
        self::assertSame('submitted', $report['status']);
        self::assertSame(2022, (int) $report['incident_year']);
        self::assertSame('A shorter account.', SurvivorReport::account($report));
        self::assertNull($report['published_encrypted']);

        SurvivorReport::write((int) $report['id'], ['status' => 'approved']);
        self::assertSame(302, $this->get('/my-report/edit')->status, 'no edits once approved');
    }

    public function testAfterApprovalSheCanStillPublishLessButNeverMore(): void
    {
        $key = (string) $this->submitReport()['key'];
        $case = (new CaseKeyService())->findCase($key);
        $report = SurvivorReport::findByCase((int) $case['id']);
        SurvivorReport::write((int) $report['id'], ['status' => 'approved']);
        SurvivorReport::savePublished((int) $report['id'], 'In my second year', (int) $this->createUser()['id']);
        $this->openReport($key);

        $this->post('/my-report/consent', ['_csrf' => $this->csrfToken(), 'consent' => 'stats']);
        $report = SurvivorReport::find((int) $report['id']);
        self::assertSame('stats', $report['consent']);
        self::assertNull($report['published_encrypted']);

        $this->post('/my-report/consent', ['_csrf' => $this->csrfToken(), 'consent' => 'stats_and_account']);
        self::assertSame('stats', SurvivorReport::find((int) $report['id'])['consent'], 'publishing more is refused');

        $this->post('/my-report/consent', ['_csrf' => $this->csrfToken(), 'consent' => 'private']);
        self::assertSame('private', SurvivorReport::find((int) $report['id'])['status']);
    }

    public function testShePagesHerOwnEvidenceAddsAndDeletesIt(): void
    {
        $key = (string) $this->submitReport()['key'];
        $this->openReport($key);
        $jpeg = EvidenceFixtures::jpegWithGps();

        $missingAttest = $this->postWithFiles('/my-report/evidence', ['_csrf' => $this->csrfToken()], ['evidence' => [[EvidenceFixtures::write($jpeg, '.jpg'), 'a.jpg']]]);
        self::assertSame(422, $missingAttest->status);

        $added = $this->postWithFiles('/my-report/evidence', ['_csrf' => $this->csrfToken(), 'evidence_attest' => '1'], ['evidence' => [
            [EvidenceFixtures::write($jpeg, '.jpg'), 'door.jpg'],
            [EvidenceFixtures::write(EvidenceFixtures::pdfWithAuthor(), '.pdf'), 'emails.pdf'],
        ]]);
        self::assertSame(302, $added->status);

        $page = $this->get('/my-report')->body;
        self::assertStringContainsString('door.jpg', $page);
        self::assertStringContainsString(hash('sha256', $jpeg), $page);

        $file = Database::connection()->query("SELECT * FROM evidence_files WHERE kind = 'jpeg'")->fetch();
        $view = $this->get('/my-report/evidence/' . $file['id']);
        self::assertSame($jpeg, $view->body, 'her own file, exactly as she added it');
        self::assertSame('image/jpeg', $view->header('Content-Type'));
        self::assertSame('nosniff', $view->header('X-Content-Type-Options'));

        $vault = new VaultService();
        $path = $vault->blobPath($file['blob_name']);
        self::assertFileExists($path);
        $this->post('/my-report/evidence/' . $file['id'] . '/delete', ['_csrf' => $this->csrfToken()]);
        self::assertNull(EvidenceFile::find((int) $file['id']));
        self::assertFileDoesNotExist($path);
        self::assertFileDoesNotExist($vault->blobPath($file['admin_blob_name']));
    }

    public function testSomeoneElsesEvidenceIdOpensNothing(): void
    {
        $theirs = $this->submitReport([], [[EvidenceFixtures::write(EvidenceFixtures::jpegWithGps(), '.jpg'), 'theirs.jpg']]);
        $fileId = (int) Database::connection()->query('SELECT id FROM evidence_files')->fetchColumn();

        $this->openReport((string) $this->submitReport()['key']);
        self::assertSame(404, $this->get('/my-report/evidence/' . $fileId)->status);
        unset($theirs);
    }

    public function testWithdrawingTakesTwoConfirmationsAndLeavesNoRowAndNoFile(): void
    {
        $photo = EvidenceFixtures::write(EvidenceFixtures::jpegWithGps(), '.jpg');
        $key = (string) $this->submitReport(['email' => 'me@example.org'], [[$photo, 'photo.jpg'], [EvidenceFixtures::write('notes', '.txt'), 'notes.txt']])['key'];
        $case = (new CaseKeyService())->findCase($key);
        $report = SurvivorReport::findByCase((int) $case['id']);
        $fileIds = array_map('intval', array_column(EvidenceFile::forReport((int) $report['id']), 'id'));
        $this->openReport($key);
        $this->post('/my-report/share-links', ['_csrf' => $this->csrfToken(), 'files' => $fileIds, 'expiry' => '7d']);

        // An admin looked at it: the audit entry stays, its link to her goes.
        $this->actingAsAdmin();
        \Keel\Core\Activity::log('evidence_file.viewed', 'EvidenceFile', $fileIds[0], ['report_id' => (int) $report['id']]);
        \Keel\Core\Session::forget('user_id');
        $this->openReport($key);

        self::assertCount(4, $this->vaultFiles(), 'two files, each with an admin copy');
        self::assertSame(1, $this->rowsForCase((int) $case['id'], (int) $report['id'], $fileIds)['share_links']);

        self::assertStringContainsString('Withdraw your report', $this->get('/my-report/withdraw')->body);
        $second = $this->post('/my-report/withdraw', ['_csrf' => $this->csrfToken()]);
        self::assertStringContainsString('Are you sure?', $second->body);
        preg_match('/name="withdraw_token" value="([0-9a-f]+)"/', $second->body, $token);

        $unticked = $this->post('/my-report/withdraw/confirm', ['_csrf' => $this->csrfToken(), 'withdraw_token' => $token[1]]);
        self::assertSame(422, $unticked->status);
        self::assertNotNull(SurvivorCase::find((int) $case['id']));

        $done = $this->post('/my-report/withdraw/confirm', ['_csrf' => $this->csrfToken(), 'withdraw_token' => $token[1], 'understand' => '1']);
        self::assertStringContainsString('Everything has been deleted', $done->body);

        self::assertSame(array_fill_keys(['survivor_cases', 'survivor_reports', 'evidence_files', 'share_links', 'share_link_files', 'moderation_events', 'activity_log'], 0), $this->rowsForCase((int) $case['id'], (int) $report['id'], $fileIds));
        self::assertSame([], $this->vaultFiles(), 'no file left in the vault');
        self::assertSame(1, (int) Database::connection()->query("SELECT COUNT(*) FROM activity_log WHERE action = 'evidence_file.viewed' AND subject_id IS NULL AND metadata IS NULL")->fetchColumn());
        self::assertNull((new CaseKeyService())->findCase($key), 'her key opens nothing');
        self::assertNull(SurvivorSession::caseId());
    }
}

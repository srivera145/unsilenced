<?php

declare(strict_types=1);

namespace Tests\Feature;

use Keel\App\Jobs\ExpireShareLinks;
use Keel\App\Models\EvidenceFile;
use Keel\App\Services\Survivor\ShareLinkService;
use Keel\Core\Database;
use Tests\Support\EvidenceFixtures;
use Tests\Support\SurvivorHelpers;
use Tests\TestCase;

/**
 * Share links: she makes one for chosen files; the person she sends it to
 * gets the originals with each file's SHA-256 and upload time; she sees when
 * it was last opened and can turn it off at once.
 */
class ShareLinkFeatureTest extends TestCase
{
    use SurvivorHelpers;

    private array $fileIds = [];
    private string $jpeg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importFixtures();
        $this->enableSubmissions();

        $this->jpeg = EvidenceFixtures::jpegWithGps();
        $key = (string) $this->submitReport([], [
            [EvidenceFixtures::write($this->jpeg, '.jpg'), 'front door.jpg'],
            [EvidenceFixtures::write("What he texted me.\n", '.txt'), 'texts.txt'],
        ])['key'];
        $this->openReport($key);
        $this->fileIds = array_map('intval', Database::connection()->query('SELECT id FROM evidence_files ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** Makes a link from /my-report and returns its token, read from the page shown once. */
    private function makeLink(array $fields = []): string
    {
        $page = $this->post('/my-report/share-links', $fields + ['_csrf' => $this->csrfToken(), 'files' => $this->fileIds, 'expiry' => '7d', 'label' => 'my attorney'])->body;
        self::assertMatchesRegularExpression('#value="http://localhost/share\#([A-Za-z0-9_-]{43})"#', $page);
        preg_match('#/share\#([A-Za-z0-9_-]{43})"#', $page, $match);

        return $match[1];
    }

    private function openLink(string $token, string $passcode = ''): \Tests\Support\TestResponse
    {
        return $this->post('/share/open', ['_csrf' => $this->csrfToken(), 'token' => $token, 'passcode' => $passcode]);
    }

    public function testTheLinkIsShownOnceAndOnlyItsHashIsStored(): void
    {
        $token = $this->makeLink();

        $row = Database::connection()->query('SELECT * FROM share_links')->fetch();
        self::assertSame(hash('sha256', $token), $row['token_hash']);
        self::assertStringNotContainsString($token, json_encode($row));
        self::assertStringNotContainsString('my attorney', json_encode($row));
        self::assertSame('my attorney', ShareLinkService::label($row));

        $page = $this->get('/my-report')->body;
        self::assertStringContainsString('my attorney', $page);
        self::assertStringContainsString('not opened yet', $page);
        self::assertStringNotContainsString($token, $page, 'never shown again');
    }

    public function testTheRecipientGetsTheOriginalsWithFingerprintsAndTimes(): void
    {
        $token = $this->makeLink();

        // The page itself carries no token: it arrives in the fragment.
        $landing = $this->get('/share');
        self::assertStringContainsString('<title>Files · Unsilenced</title>', $landing->body);
        self::assertStringContainsString('/js/share.js', $landing->body);

        self::assertSame('/share/files', $this->openLink('http://localhost/share#' . $token)->header('Location'), 'the whole pasted link works too');
        $files = $this->get('/share/files')->body;

        $jpeg = EvidenceFile::find($this->fileIds[0]);
        self::assertStringContainsString('front door.jpg', $files);
        self::assertStringContainsString(hash('sha256', $this->jpeg), $files);
        self::assertStringContainsString($jpeg['uploaded_at'] . ' UTC', $files);
        self::assertNotNull(Database::connection()->query('SELECT last_opened_at FROM share_links')->fetchColumn(), 'only a time is recorded');

        $download = $this->get('/share/files/' . $this->fileIds[0]);
        self::assertSame($this->jpeg, $download->body, 'the original, metadata included');
        self::assertSame(hash('sha256', $this->jpeg), hash('sha256', $download->body), 'its SHA-256 matches the one shown');
        self::assertStringContainsString('attachment;', (string) $download->header('Content-Disposition'));
        self::assertArrayHasKey('GPS', EvidenceFixtures::exif($download->body));

        $zip = $this->get('/share/download');
        self::assertSame('application/zip', $zip->header('Content-Type'));
        $path = EvidenceFixtures::write($zip->body, '.zip');
        $archive = new \ZipArchive();
        self::assertTrue($archive->open($path, \ZipArchive::CHECKCONS));
        self::assertSame($this->jpeg, $archive->getFromName('front door.jpg'));
        self::assertSame("What he texted me.\n", $archive->getFromName('texts.txt'));
        $manifest = (string) $archive->getFromName('manifest.txt');
        self::assertStringContainsString('SHA-256:   ' . hash('sha256', $this->jpeg), $manifest);
        self::assertStringContainsString('Uploaded:  ' . $jpeg['uploaded_at'] . ' UTC', $manifest);
        $archive->close();
    }

    public function testAPasscodeIsCheckedAndTooManyWrongOnesLockTheLink(): void
    {
        \Keel\App\Support\Config::set('survivor_reports.share_passcode_max_attempts', 3);
        $token = $this->makeLink(['passcode' => 'blue-river-7']);

        $ask = $this->openLink($token);
        self::assertStringContainsString('name="passcode"', $ask->body);

        self::assertSame(422, $this->openLink($token, 'wrong-one')->status);
        self::assertSame(302, $this->openLink($token, 'blue-river-7')->status);
        self::assertSame(200, $this->get('/share/files')->status);

        $this->post('/share/close', ['_csrf' => $this->csrfToken()]);
        $this->openLink($token, 'wrong-two');
        $locked = $this->openLink($token, 'wrong-three');
        self::assertSame(404, $locked->status);
        self::assertSame(404, $this->openLink($token, 'blue-river-7')->status, 'locked, even with the right passcode');
        self::assertStringContainsString('stopped after too many wrong passcodes', $this->get('/my-report')->body);
    }

    public function testRevokingWorksAtOnceEvenForSomeoneAlreadyLookingAtIt(): void
    {
        $token = $this->makeLink();
        $this->openLink($token);
        self::assertSame(200, $this->get('/share/files')->status);

        $linkId = (int) Database::connection()->query('SELECT id FROM share_links')->fetchColumn();
        $this->post('/my-report/share-links/' . $linkId . '/revoke', ['_csrf' => $this->csrfToken()]);

        self::assertSame(0, (int) Database::connection()->query('SELECT COUNT(*) FROM share_links')->fetchColumn());
        self::assertSame(302, $this->get('/share/files')->status);
        self::assertSame(302, $this->get('/share/files/' . $this->fileIds[0])->status);
        self::assertSame(404, $this->openLink($token)->status);
    }

    public function testAnExpiredLinkOpensNothingAndIsThenDeleted(): void
    {
        $token = $this->makeLink(['expiry' => '24h']);
        Database::connection()->exec("UPDATE share_links SET expires_at = '2000-01-01 00:00:00'");

        $page = $this->openLink($token);
        self::assertSame(404, $page->status);
        self::assertStringContainsString('This link is not available', $page->body);
        self::assertStringContainsString('expired', $this->get('/my-report')->body);

        self::assertSame(1, (new ExpireShareLinks())->run());
        self::assertSame(0, (int) Database::connection()->query('SELECT COUNT(*) FROM share_links')->fetchColumn());
    }

    public function testAQuarantinedOrDeletedFileLeavesTheLink(): void
    {
        $token = $this->makeLink();
        EvidenceFile::quarantine($this->fileIds[0], null);
        $this->openLink($token);

        $files = $this->get('/share/files')->body;
        self::assertStringNotContainsString('front door.jpg', $files);
        self::assertStringContainsString('texts.txt', $files);
        self::assertSame(404, $this->get('/share/files/' . $this->fileIds[0])->status);
        self::assertSame(404, $this->get('/my-report/evidence/' . $this->fileIds[0])->status, 'she cannot open it either');
    }

    public function testEveryFailureLooksTheSame(): void
    {
        foreach (['', 'short', str_repeat('A', 43), 'http://localhost/share#' . str_repeat('b', 43)] as $token) {
            $response = $this->openLink($token);
            self::assertSame(404, $response->status, $token);
            self::assertStringContainsString('This link is not available', $response->body);
        }
    }
}

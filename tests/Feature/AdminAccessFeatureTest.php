<?php

declare(strict_types=1);

namespace Tests\Feature;

use Keel\App\Models\AccountabilityItem;
use Keel\App\Models\School;
use Keel\Core\Database;
use Keel\Core\Router;
use Tests\TestCase;

class AdminAccessFeatureTest extends TestCase
{
    private const ADMIN_PAGES = [
        '/admin',
        '/admin/schools',
        '/admin/schools/new',
        '/admin/accountability',
        '/admin/accountability/new',
        '/admin/resources',
        '/admin/resources/new',
        '/admin/states',
        '/admin/states/new',
        '/admin/imports',
    ];

    public function testSignedOutVisitorsAreSentToSignIn(): void
    {
        foreach (self::ADMIN_PAGES as $path) {
            $response = $this->get($path);
            self::assertSame(302, $response->status, $path);
            self::assertSame('/login', $response->header('Location'), $path);
        }
    }

    public function testSignedInNonAdminsAreRefused(): void
    {
        $this->actingAs();

        foreach (self::ADMIN_PAGES as $path) {
            self::assertSame(403, $this->get($path)->status, $path);
        }

        $post = $this->post('/admin/schools', ['_csrf' => $this->csrfToken(), 'unitid' => '1', 'name' => 'X', 'state' => 'NY']);
        self::assertSame(403, $post->status);
        self::assertSame(0, (int) Database::connection()->query('SELECT COUNT(*) FROM schools')->fetchColumn());
    }

    public function testAdminsCanOpenEveryAdminPage(): void
    {
        $this->importFixtures();
        $this->actingAsAdmin();

        foreach (self::ADMIN_PAGES as $path) {
            $response = $this->get($path);
            self::assertSame(200, $response->status, $path);
            self::assertStringContainsString('id="quick-exit"', $response->body, $path);
            self::assertStringContainsString('noindex', $response->body, $path);
        }

        $school = School::findByUnitid(990001);
        self::assertSame(200, $this->get('/admin/schools/' . $school['id'] . '/edit')->status);
        self::assertSame(200, $this->get('/admin/states/1/edit')->status);
        self::assertSame(200, $this->get('/admin/resources/1/edit')->status);

        $run = (int) Database::connection()->query('SELECT MAX(id) FROM import_runs')->fetchColumn();
        self::assertSame(0, $run, 'importFixtures() calls the importers directly, without runs');
    }

    public function testEveryAdminFormPostRequiresCsrf(): void
    {
        $this->actingAsAdmin();

        foreach ($this->routes() as $route) {
            if ($route['method'] !== 'POST' || !str_starts_with($route['uri'], '/admin')) {
                continue;
            }

            $uri = str_replace('{id}', '1', $route['uri']);
            self::assertSame(419, $this->post($uri, [])->status, $uri);
        }
    }

    public function testAdminCanCreateUpdateAndDeleteASchool(): void
    {
        $this->actingAsAdmin();
        $csrf = $this->csrfToken();

        $create = $this->post('/admin/schools', ['_csrf' => $csrf, 'unitid' => '123456', 'name' => 'Hand Entered College', 'city' => 'Burlington', 'state' => 'VT', 'control' => 'public', 'enrollment' => '1200', 'enrollment_year' => '2023']);
        self::assertSame(302, $create->status);

        $school = School::findByUnitid(123456);
        self::assertSame('hand-entered-college', $school['slug']);
        self::assertSame(200, $this->get('/schools/vt/hand-entered-college')->status);

        $invalid = $this->post('/admin/schools/' . $school['id'], ['_csrf' => $csrf, 'unitid' => '123456', 'name' => '', 'state' => 'VT', 'enrollment' => '900']);
        self::assertSame(422, $invalid->status);
        self::assertStringContainsString('Enter the school name.', $invalid->body);
        self::assertStringContainsString('Say which IPEDS year the enrollment figure is from.', $invalid->body);

        $this->post('/admin/schools/' . $school['id'], ['_csrf' => $csrf, 'unitid' => '123456', 'name' => 'Renamed College', 'slug' => 'hand-entered-college', 'state' => 'VT']);
        self::assertSame('Renamed College', School::find((int) $school['id'])['name']);

        $delete = $this->post('/admin/schools/' . $school['id'] . '/delete', ['_csrf' => $csrf]);
        self::assertSame(302, $delete->status);
        self::assertNull(School::find((int) $school['id']));
    }

    public function testSummaryThatLooksLikeANameIsHeldUntilTheAdminConfirms(): void
    {
        $this->importFixtures();
        $admin = $this->actingAsAdmin();
        $csrf = $this->csrfToken();
        $fields = [
            '_csrf' => $csrf,
            'unitid' => '990001',
            'type' => 'lawsuit',
            'item_date' => '2025-02-10',
            'status' => 'open',
            'source_name' => 'County court records',
            'source_url' => 'https://example.org/case',
            'is_published' => '1',
        ];

        $flagged = $this->post('/admin/accountability', $fields + ['summary' => 'A lawsuit says Jordan Avery Smith was not disciplined by Fixture State University.']);
        self::assertSame(422, $flagged->status);
        self::assertStringContainsString('may contain a person', $flagged->body);
        self::assertStringContainsString('<span class="flagged-phrase">Jordan Avery Smith</span>', $flagged->body);
        self::assertStringNotContainsString('flagged-phrase">Fixture State University', $flagged->body, "the school's own name is not a person");
        self::assertSame(0, AccountabilityItem::count());

        $confirmed = $this->post('/admin/accountability', $fields + ['summary' => 'Coverage by the Ithaca Voice of the chapter suspension.', 'confirm_no_names' => '1']);
        self::assertSame(302, $confirmed->status);
        $item = Database::connection()->query('SELECT * FROM accountability_items ORDER BY id DESC LIMIT 1')->fetch();
        self::assertSame((int) $admin['id'], (int) $item['name_check_confirmed_by']);
        self::assertNotNull($item['name_check_confirmed_at']);

        $clean = $this->post('/admin/accountability', $fields + ['summary' => 'A federal investigation was opened into how the university handled a complaint.']);
        self::assertSame(302, $clean->status);
        $item = Database::connection()->query('SELECT * FROM accountability_items ORDER BY id DESC LIMIT 1')->fetch();
        self::assertNull($item['name_check_confirmed_by']);
    }

    public function testAccountabilityItemRequiresAnHttpSourceAndKnownSchool(): void
    {
        $this->importFixtures();
        $this->actingAsAdmin();

        $response = $this->post('/admin/accountability', [
            '_csrf' => $this->csrfToken(), 'unitid' => '999999', 'type' => 'nope', 'item_date' => '2025-13-40',
            'summary' => '', 'status' => 'open', 'source_name' => '', 'source_url' => 'javascript:alert(1)',
        ]);

        self::assertSame(422, $response->status);
        foreach (['Enter the UNITID of a school', 'Choose a type.', 'Enter a date as YYYY-MM-DD.', 'Write a short summary', 'Name the source', 'starting with https://'] as $message) {
            self::assertStringContainsString($message, $response->body);
        }
    }

    public function testSignInIsOnlyForAdminsAndDoesNotRevealWhoHasAccess(): void
    {
        $headers = ['X-CSRF-Token' => $this->csrfToken()];

        $stranger = $this->postJson('/auth/otp/request', ['email' => 'stranger@example.test'], $headers);
        self::assertSame(200, $stranger->status);
        self::assertTrue((bool) ($stranger->json()['success'] ?? false));
        self::assertSame('', $this->latestMailLog(), 'no code is sent to a non-admin');
        self::assertSame(0, (int) Database::connection()->query("SELECT COUNT(*) FROM users WHERE email = 'stranger@example.test'")->fetchColumn(), 'no account is created');

        $this->createUser(['email' => 'member@example.test']);
        $member = $this->postJson('/auth/otp/request', ['email' => 'member@example.test'], $headers);
        self::assertSame($stranger->json(), $member->json(), 'a non-admin user gets the identical response');
        self::assertSame('', $this->latestMailLog());
    }

    private function routes(): array
    {
        $router = new Router();
        require self::$basePath . '/routes/web.php';

        return $router->registeredRoutes();
    }
}

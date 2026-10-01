<?php

declare(strict_types=1);

namespace Tests\Feature;

use Keel\App\Models\StatePage;
use Keel\Core\Database;
use Tests\TestCase;

class StatePagesFeatureTest extends TestCase
{
    private ?array $original = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->original = StatePage::findByCode('VT');
    }

    protected function tearDown(): void
    {
        // state_pages is seeded by a migration and not truncated: put VT back.
        if ($this->original !== null) {
            StatePage::update((int) $this->original['id'], array_intersect_key($this->original, array_flip(StatePage::FILLABLE)));
        }

        parent::tearDown();
    }

    public function testEveryStateAndDcIsSeededAwaitingLegalReviewWithNoLegalText(): void
    {
        $rows = Database::connection()->query('SELECT status, statute_of_limitations, resources FROM state_pages')->fetchAll();

        self::assertCount(51, $rows);
        foreach ($rows as $row) {
            self::assertSame('needs_legal_review', $row['status']);
            self::assertNull($row['statute_of_limitations']);
            self::assertNull($row['resources']);
        }
    }

    public function testPageAwaitingReviewShowsComingSoonAndHotlineButNeverItsLegalFields(): void
    {
        // A draft entered by an admin but not yet reviewed.
        StatePage::update((int) $this->original['id'], [
            'statute_of_limitations' => 'DRAFT-STATUTE-TEXT not reviewed',
            'resources' => 'DRAFT-RESOURCES-TEXT not reviewed',
        ]);

        $response = $this->get('/states/vt');

        self::assertSame(200, $response->status);
        self::assertStringContainsString('Information coming soon', $response->body);
        self::assertStringContainsString('1-800-656-4673', $response->body);
        self::assertStringNotContainsString('DRAFT-STATUTE-TEXT', $response->body);
        self::assertStringNotContainsString('DRAFT-RESOURCES-TEXT', $response->body);
        self::assertStringNotContainsString('Statute of limitations</h2>', $response->body);
    }

    public function testPublishedPageShowsItsReviewedText(): void
    {
        StatePage::update((int) $this->original['id'], [
            'status' => 'published',
            'statute_of_limitations' => 'REVIEWED-STATUTE-TEXT',
            'resources' => '- REVIEWED-RESOURCE',
            'legal_reviewed_by' => 'Test reviewer',
            'legal_reviewed_on' => '2026-09-01',
        ]);

        $body = $this->get('/states/vt')->body;

        self::assertStringContainsString('REVIEWED-STATUTE-TEXT', $body);
        self::assertStringContainsString('<li>REVIEWED-RESOURCE</li>', $body);
        self::assertStringNotContainsString('Information coming soon', $body);
    }

    public function testAdminCannotPublishWithoutRecordingLegalReview(): void
    {
        $this->actingAsAdmin();

        $response = $this->post('/admin/states/' . (int) $this->original['id'], [
            '_csrf' => $this->csrfToken(),
            'code' => 'VT',
            'name' => 'Vermont',
            'status' => 'published',
            'statute_of_limitations' => 'Some text',
        ]);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Add the reviewed state resources before publishing.', $response->body);
        self::assertStringContainsString('Record who completed the legal review before publishing.', $response->body);
        self::assertSame('needs_legal_review', StatePage::findByCode('VT')['status']);
    }

    public function testUnknownStateIs404(): void
    {
        self::assertSame(404, $this->get('/states/zz')->status);
        self::assertSame(404, $this->get('/states/VT')->status);
    }
}

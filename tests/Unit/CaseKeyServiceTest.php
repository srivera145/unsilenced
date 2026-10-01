<?php

declare(strict_types=1);

namespace Tests\Unit;

use Keel\App\Services\Survivor\CaseKeyService;
use PHPUnit\Framework\TestCase;

/**
 * Case keys: six words from the built-in list, at least 70 bits, never
 * stored. Only an HMAC lookup id and an Argon2id hash are. Finding a case by
 * key is in SurvivorReportFlowFeatureTest.
 */
class CaseKeyServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV['VAULT_MASTER_KEY'] = base64_encode(str_repeat("\x07", 32));
    }

    protected function tearDown(): void
    {
        unset($_ENV['VAULT_MASTER_KEY']);
    }

    public function testTheWordlistGivesSixWordKeysAtLeastSeventyBits(): void
    {
        $words = CaseKeyService::words();

        self::assertGreaterThanOrEqual(3251, count($words), '6 words need 3,251 or more for 70 bits');
        self::assertGreaterThanOrEqual(70.0, CaseKeyService::entropyBits());
        self::assertSame(count($words), count(array_unique($words)));

        foreach ($words as $word) {
            self::assertMatchesRegularExpression('/^[a-z]{3,9}$/', $word);
        }
    }

    public function testTheWordlistLeavesOutWordsASurvivorShouldNotBeHanded(): void
    {
        $words = array_flip(CaseKeyService::words());

        foreach (['rape', 'assault', 'victim', 'survivor', 'consent', 'blood', 'naked', 'drunk', 'asleep', 'police', 'sigma', 'delta', 'statutory', 'willing', 'refusal', 'evidence', 'grape'] as $word) {
            self::assertArrayNotHasKey($word, $words, $word);
        }
    }

    public function testGeneratedKeysAreSixWordsFromTheList(): void
    {
        $service = new CaseKeyService();
        $words = array_flip(CaseKeyService::words());
        $keys = [];

        for ($i = 0; $i < 50; $i++) {
            $key = $service->generate();
            $parts = explode(' ', $key);
            self::assertCount(6, $parts);
            foreach ($parts as $part) {
                self::assertArrayHasKey($part, $words);
            }
            $keys[] = $key;
        }

        self::assertCount(50, array_unique($keys));
    }

    public function testKeysAreComparedAfterNormalizing(): void
    {
        self::assertSame('maple otter brisk clam fable oval', CaseKeyService::normalize("  Maple-Otter\tBRISK, clam  fable.oval "));
    }

    public function testTheLookupIdIsAnHmacThatNeverContainsTheKey(): void
    {
        $service = new CaseKeyService();
        $key = 'maple otter brisk clam fable oval';
        $id = $service->lookupId($key);

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $id);
        self::assertSame($id, $service->lookupId('MAPLE-otter brisk clam fable oval'));
        self::assertNotSame($id, $service->lookupId('maple otter brisk clam fable ovals'));
        self::assertNotSame($id, hash('sha256', $key), 'keyed: useless without VAULT_MASTER_KEY');
        self::assertStringNotContainsString('maple', $id);

        $_ENV['VAULT_MASTER_KEY'] = base64_encode(str_repeat("\x08", 32));
        self::assertNotSame($id, $service->lookupId($key), 'a different master key gives a different id');
    }

    public function testTheHashIsArgon2idAndVerifiesOnlyTheRightKey(): void
    {
        $service = new CaseKeyService();
        $hash = $service->hash('maple otter brisk clam fable oval');

        self::assertStringStartsWith('$argon2id$', $hash);
        self::assertTrue($service->verify('Maple Otter Brisk Clam Fable Oval', $hash));
        self::assertFalse($service->verify('maple otter brisk clam fable ovals', $hash));
        self::assertStringNotContainsString('maple', $hash);
    }

    public function testUnknownWordsAreNamedForAGentlerMessage(): void
    {
        $known = CaseKeyService::words()[0];

        self::assertSame(['zzzq'], CaseKeyService::unknownWords($known . ' zzzq'));
        self::assertSame([], CaseKeyService::unknownWords($known));
    }
}

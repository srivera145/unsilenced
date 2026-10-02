<?php

namespace Keel\App\Services\Survivor;

use Keel\Core\Env;

/**
 * The vault's keys, all derived from VAULT_MASTER_KEY (32 random bytes,
 * base64, from `php database/console.php vault:keygen`).
 *
 * One secret in .env, separate keys per purpose (HKDF-SHA256), so a key used
 * for one job can never decrypt another's data:
 *
 *   file-kek   wraps each evidence file's own random key
 *   text       account text, notes, file names, labels, email
 *   lookup     HMAC that turns a case key into its lookup id
 *
 * The master key is never in the database or a backup.
 *
 * After a rotation (`vault:rotate`, KeyRotationService) VAULT_LOOKUP_KEY holds
 * the lookup key derived from the previous master: case keys are never stored,
 * so their lookup ids cannot be recomputed under a new one. The lookup key
 * decrypts nothing; it only finds a row for a key someone already has.
 */
final class VaultKeys
{
    private static ?self $current = null;
    private static ?string $currentSource = null;

    private function __construct(private readonly string $master, private readonly ?string $lookupOverride = null)
    {
    }

    /** The keys from VAULT_MASTER_KEY (and VAULT_LOOKUP_KEY), or null when the master is missing or not 32 bytes. */
    public static function current(): ?self
    {
        $master = trim((string) Env::get('VAULT_MASTER_KEY', ''));
        $lookup = trim((string) Env::get('VAULT_LOOKUP_KEY', ''));
        $source = $master . '|' . $lookup;

        if ($source !== self::$currentSource) {
            self::$currentSource = $source;
            self::$current = self::fromString($master, $lookup === '' ? null : $lookup);
        }

        return self::$current;
    }

    public static function require(): self
    {
        return self::current() ?? throw new \RuntimeException('VAULT_MASTER_KEY is missing or is not 32 bytes of base64.');
    }

    /**
     * @param ?string $lookup VAULT_LOOKUP_KEY, base64; when given but not 32
     *        bytes the keys are refused rather than silently falling back to
     *        a lookup key that would open nothing.
     */
    public static function fromString(string $value, ?string $lookup = null): ?self
    {
        $master = self::decode($value);
        if ($master === null) {
            return null;
        }

        if ($lookup === null) {
            return new self($master);
        }

        $lookupKey = self::decode($lookup);

        return $lookupKey === null ? null : new self($master, $lookupKey);
    }

    /** A new master key, base64, for .env. */
    public static function generate(): string
    {
        return base64_encode(random_bytes(32));
    }

    public function fileKek(): string
    {
        return $this->derive('file-kek');
    }

    public function text(): string
    {
        return $this->derive('text');
    }

    public function lookup(): string
    {
        return $this->lookupOverride ?? $this->derive('lookup');
    }

    /** The lookup key as VAULT_LOOKUP_KEY carries it, for the end of a rotation. */
    public function lookupForEnv(): string
    {
        return base64_encode($this->lookup());
    }

    /** True when both hold the same master key. */
    public function sameMasterAs(self $other): bool
    {
        return hash_equals($this->master, $other->master);
    }

    private function derive(string $purpose): string
    {
        return hash_hkdf('sha256', $this->master, 32, 'unsilenced:' . $purpose . ':v1');
    }

    private static function decode(string $value): ?string
    {
        $value = trim($value);
        if (str_starts_with($value, 'base64:')) {
            $value = substr($value, 7);
        }

        if ($value === '') {
            return null;
        }

        $decoded = base64_decode($value, true);

        return is_string($decoded) && strlen($decoded) === 32 ? $decoded : null;
    }
}

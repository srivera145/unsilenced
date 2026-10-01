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
 * The master key is never in the database or a backup. Changing it makes
 * every existing file, account and case key unusable; there is no re-key
 * command yet (docs/SURVIVOR-REPORTS.md).
 */
final class VaultKeys
{
    private static ?self $current = null;
    private static ?string $currentSource = null;

    private function __construct(private readonly string $master)
    {
    }

    /** The keys from VAULT_MASTER_KEY, or null when it is missing or not 32 bytes. */
    public static function current(): ?self
    {
        $source = trim((string) Env::get('VAULT_MASTER_KEY', ''));

        if ($source !== self::$currentSource) {
            self::$currentSource = $source;
            self::$current = self::fromString($source);
        }

        return self::$current;
    }

    public static function require(): self
    {
        return self::current() ?? throw new \RuntimeException('VAULT_MASTER_KEY is missing or is not 32 bytes of base64.');
    }

    public static function fromString(string $value): ?self
    {
        $value = trim($value);
        if (str_starts_with($value, 'base64:')) {
            $value = substr($value, 7);
        }

        if ($value === '') {
            return null;
        }

        $decoded = base64_decode($value, true);

        return is_string($decoded) && strlen($decoded) === 32 ? new self($decoded) : null;
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
        return $this->derive('lookup');
    }

    private function derive(string $purpose): string
    {
        return hash_hkdf('sha256', $this->master, 32, 'unsilenced:' . $purpose . ':v1');
    }
}

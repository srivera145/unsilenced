<?php

namespace Keel\App\Services\Survivor;

use Keel\App\Support\Config;
use Keel\Core\Env;

/**
 * The evidence vault: encrypted files on disk, outside public_html.
 *
 * On upload:
 *   1. The file's type is decided from its bytes (FileInspector).
 *   2. SHA-256 of the ORIGINAL bytes and the server's UTC time are recorded:
 *      the fingerprint a share page shows, so anyone can check a copy.
 *   3. The original is encrypted with a new random key, using libsodium's
 *      secretstream (XChaCha20-Poly1305, in 64 KiB chunks, with an end tag so
 *      a cut-short file fails to decrypt instead of decrypting short). The
 *      key is wrapped with the master key and stored in the database.
 *   4. A second copy with the metadata removed (MetadataStripper) is
 *      encrypted the same way, under its own key. Admins only ever see this
 *      copy; the original goes out only through the survivor's share links.
 *
 * Deleting the database row destroys the wrapped key, so even a stray copy of
 * the file on disk or in a backup is unreadable afterwards.
 */
final class VaultService
{
    private const MAGIC = 'USV1';
    private const CHUNK = 65536;
    private const STREAM_AD = 'unsilenced-evidence-v1';

    private string $root;

    public function __construct(?string $root = null)
    {
        $this->root = $root ?? self::root();
    }

    /** VAULT_PATH, or storage/vault. A relative path is relative to the project. */
    public static function root(): string
    {
        $project = dirname(__DIR__, 4);
        $configured = trim((string) Env::get('VAULT_PATH', ''));
        $path = $configured === '' ? $project . '/storage/vault' : $configured;

        if (!preg_match('#^(?:[A-Za-z]:[\\\\/]|[\\\\/])#', $path)) {
            $path = $project . '/' . $path;
        }

        return rtrim(str_replace('\\', '/', $path), '/');
    }

    /** False when the vault would be inside the web root, where files could be served. */
    public static function rootIsSafe(?string $root = null): bool
    {
        $root = str_replace('\\', '/', strtolower($root ?? self::root()));
        $public = str_replace('\\', '/', strtolower(dirname(__DIR__, 4) . '/public_html'));

        return $root !== $public && !str_starts_with($root . '/', $public . '/');
    }

    /**
     * Checks and encrypts an uploaded file.
     *
     * @return array<string, mixed> evidence_files columns, without report_id
     * @throws EvidenceRejected when the file may not go in the vault
     */
    public function store(string $sourcePath, string $originalName): array
    {
        $maxBytes = (int) Config::get('evidence.max_file_bytes', 20 * 1024 * 1024);
        $size = @filesize($sourcePath);

        if ($size === false) {
            throw new EvidenceRejected('This file could not be read. Try adding it again.');
        }
        if ($size > $maxBytes) {
            throw new EvidenceRejected('This file is larger than ' . (int) round($maxBytes / 1048576) . ' MB, so it cannot be added.');
        }

        $bytes = (string) file_get_contents($sourcePath);
        $kind = FileInspector::detect($bytes);
        $type = (array) Config::get('evidence.types.' . $kind);

        $sha256 = hash('sha256', $bytes);
        $uploadedAt = gmdate('Y-m-d H:i:s');

        $original = $this->writeBlob($bytes);

        try {
            $stripped = MetadataStripper::strip($kind, $bytes);
            unset($bytes);
            $admin = $stripped['bytes'] !== null ? $this->writeBlob($stripped['bytes']) : null;
        } catch (\Throwable $exception) {
            $this->deleteBlob($original['name']);
            throw $exception;
        }

        return [
            'kind' => $kind,
            'mime_type' => (string) $type['mime'],
            'size_bytes' => $size,
            'sha256' => $sha256,
            'uploaded_at' => $uploadedAt,
            'name_encrypted' => SealedText::seal(self::cleanName($originalName, (string) $type['extension']), 'evidence_files.name'),
            'blob_name' => $original['name'],
            'blob_key' => $original['key'],
            'admin_blob_name' => $admin['name'] ?? null,
            'admin_blob_key' => $admin['key'] ?? null,
            'admin_copy' => $stripped['status'],
            'orientation' => $stripped['orientation'],
        ];
    }

    /** Writes the decrypted original, chunk by chunk, to $write. */
    public function streamOriginal(array $file, callable $write): void
    {
        $this->streamBlob((string) $file['blob_name'], (string) $file['blob_key'], $write);
    }

    /** Writes the decrypted admin copy (metadata removed). */
    public function streamAdminCopy(array $file, callable $write): void
    {
        if (empty($file['admin_blob_name']) || empty($file['admin_blob_key'])) {
            throw new \RuntimeException('This file has no admin copy.');
        }

        $this->streamBlob((string) $file['admin_blob_name'], (string) $file['admin_blob_key'], $write);
    }

    public function readOriginal(array $file): string
    {
        $buffer = '';
        $this->streamOriginal($file, static function (string $chunk) use (&$buffer): void {
            $buffer .= $chunk;
        });

        return $buffer;
    }

    public function readAdminCopy(array $file): string
    {
        $buffer = '';
        $this->streamAdminCopy($file, static function (string $chunk) use (&$buffer): void {
            $buffer .= $chunk;
        });

        return $buffer;
    }

    /** Removes both encrypted copies from disk. */
    public function deleteFiles(array $file): void
    {
        foreach (['blob_name', 'admin_blob_name'] as $column) {
            if (!empty($file[$column])) {
                $this->deleteBlob((string) $file[$column]);
            }
        }
    }

    public function blobPath(string $name): string
    {
        if (!preg_match('/^[0-9a-f]{32}$/', $name)) {
            throw new \InvalidArgumentException('Not a vault file name.');
        }

        return $this->root . '/' . substr($name, 0, 2) . '/' . $name;
    }

    /** @return array{name: string, key: string} */
    private function writeBlob(string $bytes): array
    {
        if (!self::rootIsSafe($this->root)) {
            throw new \RuntimeException('VAULT_PATH is inside public_html. Move it outside the web root.');
        }

        $name = bin2hex(random_bytes(16));
        $path = $this->blobPath($name);
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('The vault directory could not be created.');
        }

        $fileKey = sodium_crypto_secretstream_xchacha20poly1305_keygen();
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($fileKey);

        $partial = $path . '.part';
        $handle = fopen($partial, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('The vault file could not be written.');
        }

        try {
            fwrite($handle, self::MAGIC . $header);
            $length = strlen($bytes);
            $offset = 0;

            do {
                $chunk = substr($bytes, $offset, self::CHUNK);
                $offset += self::CHUNK;
                $tag = $offset >= $length
                    ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
                    : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;

                if (fwrite($handle, sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, self::STREAM_AD, $tag)) === false) {
                    throw new \RuntimeException('The vault file could not be written.');
                }
            } while ($offset < $length);
        } finally {
            fclose($handle);
        }

        if (!rename($partial, $path)) {
            @unlink($partial);
            throw new \RuntimeException('The vault file could not be saved.');
        }

        $wrapped = $this->wrapKey($fileKey, $name);
        sodium_memzero($fileKey);

        return ['name' => $name, 'key' => $wrapped];
    }

    private function streamBlob(string $name, string $wrappedKey, callable $write): void
    {
        $fileKey = $this->unwrapKey($wrappedKey, $name);
        $handle = @fopen($this->blobPath($name), 'rb');

        if ($handle === false) {
            sodium_memzero($fileKey);
            throw new \RuntimeException('The vault file is missing.');
        }

        try {
            $magic = fread($handle, strlen(self::MAGIC));
            $header = fread($handle, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            if ($magic !== self::MAGIC || strlen((string) $header) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES) {
                throw new \RuntimeException('The vault file is damaged.');
            }

            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $fileKey);
            $finished = false;

            while (!$finished) {
                $chunk = fread($handle, self::CHUNK + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES);
                if ($chunk === false || $chunk === '') {
                    throw new \RuntimeException('The vault file ends early.');
                }

                $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $chunk, self::STREAM_AD);
                if ($result === false) {
                    throw new \RuntimeException('The vault file could not be decrypted.');
                }

                [$plaintext, $tag] = $result;
                $write($plaintext);
                $finished = $tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
            }

            if (fread($handle, 1) !== '') {
                throw new \RuntimeException('The vault file has data after its end.');
            }
        } finally {
            fclose($handle);
            sodium_memzero($fileKey);
        }
    }

    private function wrapKey(string $fileKey, string $name): string
    {
        $kek = VaultKeys::require()->fileKek();
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $wrapped = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($fileKey, 'evidence-key:' . $name, $nonce, $kek);
        sodium_memzero($kek);

        return base64_encode($nonce . $wrapped);
    }

    private function unwrapKey(string $wrapped, string $name): string
    {
        $raw = base64_decode($wrapped, true);
        $nonceLength = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if ($raw === false || strlen($raw) <= $nonceLength) {
            throw new \RuntimeException('The file key is damaged.');
        }

        $kek = VaultKeys::require()->fileKek();
        $fileKey = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($raw, $nonceLength), 'evidence-key:' . $name, substr($raw, 0, $nonceLength), $kek);
        sodium_memzero($kek);

        if ($fileKey === false) {
            throw new \RuntimeException('The file key could not be unwrapped with this VAULT_MASTER_KEY.');
        }

        return $fileKey;
    }

    private function deleteBlob(string $name): void
    {
        $path = $this->blobPath($name);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * The name as she will see it in her list and on her share page: no
     * folders, no control characters, at most 120 characters, and something
     * when nothing is left.
     */
    public static function cleanName(string $name, string $extension): string
    {
        $name = str_replace('\\', '/', $name);
        $name = basename($name);
        $name = (string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $name);
        $name = trim(mb_substr(mb_check_encoding($name, 'UTF-8') ? $name : '', 0, 120));

        return $name === '' || $name === '.' || $name === '..' ? 'file.' . $extension : $name;
    }
}

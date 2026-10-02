<?php

namespace Keel\App\Services\Survivor;

/**
 * Short text encrypted at rest: accounts, the published version, admin notes,
 * evidence file names, share-link labels, email addresses.
 *
 * XChaCha20-Poly1305 with a random 24-byte nonce, under the vault's text key.
 * The context ("survivor_reports.account") is the associated data, so a value
 * copied into another column fails to decrypt rather than showing up in the
 * wrong place. Stored as "v1:" + base64(nonce || ciphertext).
 *
 * $keys is for key rotation, which needs the old and new keys at once;
 * everything else uses the configured VAULT_MASTER_KEY.
 */
final class SealedText
{
    private const PREFIX = 'v1:';

    /** Every encrypted text column and its context, for key rotation. */
    public const COLUMNS = [
        ['survivor_cases', 'email_encrypted', 'survivor_cases.email'],
        ['survivor_reports', 'account_encrypted', 'survivor_reports.account'],
        ['survivor_reports', 'published_encrypted', 'survivor_reports.published'],
        ['survivor_reports', 'admin_note_encrypted', 'survivor_reports.admin_note'],
        ['evidence_files', 'name_encrypted', 'evidence_files.name'],
        ['share_links', 'label_encrypted', 'share_links.label'],
    ];

    public static function seal(?string $plaintext, string $context, ?VaultKeys $keys = null): ?string
    {
        if ($plaintext === null) {
            return null;
        }

        $key = ($keys ?? VaultKeys::require())->text();
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $context, $nonce, $key);
        sodium_memzero($key);

        return self::PREFIX . base64_encode($nonce . $ciphertext);
    }

    public static function open(?string $sealed, string $context, ?VaultKeys $keys = null): ?string
    {
        if ($sealed === null || $sealed === '') {
            return null;
        }

        if (!str_starts_with($sealed, self::PREFIX)) {
            throw new \RuntimeException('Sealed text has an unknown format.');
        }

        $raw = base64_decode(substr($sealed, strlen(self::PREFIX)), true);
        $nonceLength = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if ($raw === false || strlen($raw) < $nonceLength + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES) {
            throw new \RuntimeException('Sealed text is damaged.');
        }

        $key = ($keys ?? VaultKeys::require())->text();
        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($raw, $nonceLength), $context, substr($raw, 0, $nonceLength), $key);
        sodium_memzero($key);

        if ($plaintext === false) {
            throw new \RuntimeException('Sealed text could not be decrypted with this VAULT_MASTER_KEY.');
        }

        return $plaintext;
    }

    /** Whether $sealed opens under $keys (rotation uses it to skip what is already done). */
    public static function opensWith(string $sealed, string $context, VaultKeys $keys): bool
    {
        try {
            self::open($sealed, $context, $keys);

            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }
}

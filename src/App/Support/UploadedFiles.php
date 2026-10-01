<?php

namespace Keel\App\Support;

/**
 * $_FILES for one multiple-file input, as a flat list, with PHP's upload
 * errors in plain words. Only files PHP itself received are accepted
 * (is_uploaded_file); the test harness turns that check off to hand in files
 * from disk.
 */
final class UploadedFiles
{
    public static bool $trustLocalFilesForTests = false;

    /**
     * @return list<array{name: string, tmp_name: string, size: int, error: ?string}>
     *         error is a message for her, or null
     */
    public static function from(string $field): array
    {
        $raw = $_FILES[$field] ?? null;
        if (!is_array($raw) || !isset($raw['name'])) {
            return [];
        }

        $names = (array) $raw['name'];
        $files = [];

        foreach (array_keys($names) as $index) {
            $error = (int) ((array) $raw['error'])[$index];
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $tmp = (string) ((array) $raw['tmp_name'])[$index];
            $message = self::message($error);
            if ($message === null && !self::$trustLocalFilesForTests && !is_uploaded_file($tmp)) {
                $message = 'This file could not be received. Try adding it again.';
            }

            $files[] = [
                'name' => (string) $names[$index],
                'tmp_name' => $tmp,
                'size' => (int) ((array) $raw['size'])[$index],
                'error' => $message,
            ];
        }

        return $files;
    }

    private static function message(int $error): ?string
    {
        $maxMb = (int) round((int) Config::get('evidence.max_file_bytes', 20971520) / 1048576);

        return match ($error) {
            UPLOAD_ERR_OK => null,
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "This file is larger than {$maxMb} MB, so it cannot be added.",
            UPLOAD_ERR_PARTIAL => 'Only part of this file arrived. Try adding it again.',
            default => 'This file could not be received. Try adding it again.',
        };
    }

    /**
     * True when a POST was larger than post_max_size: PHP then drops the whole
     * body, files and fields, before the app sees it.
     */
    public static function postTooLarge(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && $_POST === [] && $_FILES === []
            && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
    }

    /** post_max_size in bytes, for the form to warn before sending too much at once. */
    public static function postMaxBytes(): int
    {
        $value = trim((string) ini_get('post_max_size'));
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1073741824,
            'm' => $number * 1048576,
            'k' => $number * 1024,
            default => $number,
        };
    }
}

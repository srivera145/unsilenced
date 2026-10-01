<?php

namespace Keel\App\Models;

use Keel\Core\Database;

class ImportRun
{
    /** Error messages kept per run. The count keeps going past this. */
    public const MAX_STORED_ERRORS = 200;

    public static function create(array $attributes): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO import_runs (kind, file_name, file_path, file_sha256, data_year, location, status)
             VALUES (:kind, :file_name, :file_path, :file_sha256, :data_year, :location, :status)'
        );
        $statement->execute([
            'kind' => $attributes['kind'],
            'file_name' => $attributes['file_name'],
            'file_path' => $attributes['file_path'],
            'file_sha256' => $attributes['file_sha256'],
            'data_year' => $attributes['data_year'] ?? null,
            'location' => $attributes['location'] ?? null,
            'status' => 'queued',
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM import_runs WHERE id = ? LIMIT 1');
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    public static function recent(int $limit = 100): array
    {
        $statement = Database::connection()->prepare('SELECT * FROM import_runs ORDER BY id DESC LIMIT ?');
        $statement->bindValue(1, max(1, $limit), \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /** Back to a clean slate each time the job runs, so a retry never double counts. */
    public static function markRunning(int $id): void
    {
        $statement = Database::connection()->prepare(
            "UPDATE import_runs
             SET status = 'running', started_at = NOW(), finished_at = NULL, message = NULL,
                 rows_read = 0, rows_added = 0, rows_updated = 0, rows_unchanged = 0, rows_skipped = 0,
                 error_count = 0, errors = NULL, columns_found = NULL
             WHERE id = ?"
        );
        $statement->execute([$id]);
    }

    /**
     * @param array{rows_read:int, rows_added:int, rows_updated:int, rows_unchanged:int, rows_skipped:int, errors:list<string>, error_count:int, columns_found:array, message:?string} $result
     */
    public static function finish(int $id, string $status, array $result): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE import_runs
             SET status = :status, finished_at = NOW(),
                 rows_read = :rows_read, rows_added = :rows_added, rows_updated = :rows_updated,
                 rows_unchanged = :rows_unchanged, rows_skipped = :rows_skipped,
                 error_count = :error_count, errors = :errors, columns_found = :columns_found, message = :message
             WHERE id = :id'
        );

        $errors = array_slice($result['errors'] ?? [], 0, self::MAX_STORED_ERRORS);

        $statement->execute([
            'status' => $status,
            'rows_read' => (int) ($result['rows_read'] ?? 0),
            'rows_added' => (int) ($result['rows_added'] ?? 0),
            'rows_updated' => (int) ($result['rows_updated'] ?? 0),
            'rows_unchanged' => (int) ($result['rows_unchanged'] ?? 0),
            'rows_skipped' => (int) ($result['rows_skipped'] ?? 0),
            'error_count' => (int) ($result['error_count'] ?? count($errors)),
            'errors' => $errors === [] ? null : json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'columns_found' => empty($result['columns_found']) ? null : json_encode($result['columns_found'], JSON_UNESCAPED_SLASHES),
            'message' => isset($result['message']) ? mb_substr((string) $result['message'], 0, 1000) : null,
            'id' => $id,
        ]);
    }

    public static function fail(int $id, string $message): void
    {
        $statement = Database::connection()->prepare(
            "UPDATE import_runs SET status = 'failed', finished_at = NOW(), message = ? WHERE id = ?"
        );
        $statement->execute([mb_substr($message, 0, 1000), $id]);
    }

    /** @return list<string> */
    public static function errors(array $run): array
    {
        $decoded = json_decode((string) ($run['errors'] ?? ''), true);

        return is_array($decoded) ? array_map('strval', $decoded) : [];
    }

    public static function columnsFound(array $run): array
    {
        $decoded = json_decode((string) ($run['columns_found'] ?? ''), true);

        return is_array($decoded) ? $decoded : [];
    }
}

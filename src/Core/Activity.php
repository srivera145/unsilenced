<?php

namespace Keel\Core;

class Activity
{
    public static function log(
        string $action,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $metadata = []
    ): void {
        try {
            $userId = Auth::id();
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $metadataJson = $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR);

            $statement = Database::connection()->prepare(
                'INSERT INTO activity_log (user_id, action, subject_type, subject_id, metadata, ip_address)
                 VALUES (:user_id, :action, :subject_type, :subject_id, :metadata, :ip_address)'
            );

            $statement->bindValue(':user_id', self::nullableInt($userId), self::nullableIntType($userId));
            $statement->bindValue(':action', $action);
            $statement->bindValue(':subject_type', $subjectType);
            $statement->bindValue(':subject_id', self::nullableInt($subjectId), self::nullableIntType($subjectId));
            $statement->bindValue(':metadata', $metadataJson);
            $statement->bindValue(':ip_address', $ipAddress);

            $statement->execute();
        } catch (\Throwable $exception) {
            error_log('[Keel] Activity log failed: ' . ErrorHandler::scrub($exception->getMessage()));
        }
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private static function nullableIntType(mixed $value): int
    {
        return ($value === null || $value === '') ? \PDO::PARAM_NULL : \PDO::PARAM_INT;
    }
}

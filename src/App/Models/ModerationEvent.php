<?php

namespace Keel\App\Models;

use Keel\Core\Database;

/**
 * A report's history. details holds keys and ids only (the approval
 * checklist, which file), never anything she or an admin wrote.
 */
class ModerationEvent
{
    public static function record(int $reportId, string $actor, ?int $adminId, string $event, ?string $fromStatus = null, ?string $toStatus = null, array $details = []): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO moderation_events (report_id, actor, admin_id, event, from_status, to_status, details, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $reportId,
            $actor,
            $adminId,
            $event,
            $fromStatus,
            $toStatus,
            $details === [] ? null : json_encode($details, JSON_THROW_ON_ERROR),
            gmdate('Y-m-d H:i:s'),
        ]);
    }

    public static function forReport(int $reportId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT m.*, u.email AS admin_email FROM moderation_events m LEFT JOIN users u ON u.id = m.admin_id
             WHERE m.report_id = ? ORDER BY m.id DESC'
        );
        $statement->execute([$reportId]);

        return $statement->fetchAll();
    }
}

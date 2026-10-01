<?php

namespace Keel\App\Models;

use Keel\App\Services\Survivor\SealedText;
use Keel\Core\Database;

/**
 * A survivor's report: structured answers, her account (encrypted), the
 * admin's published version (encrypted), and where it is in moderation.
 *
 * Status flow: submitted → in_review → approved | changes_requested |
 * rejected. changes_requested goes back to submitted when she edits.
 * private (consent c) never enters the queue and is never counted.
 */
class SurvivorReport
{
    /** She can still change her answers and account. */
    public const EDITABLE = ['private', 'submitted', 'in_review', 'changes_requested'];

    /** What the admin queue shows. Private reports are not there: no admin reads them. */
    public const QUEUE = ['submitted', 'in_review', 'changes_requested'];

    /** Consents that let a report count in a school's figures. */
    public const COUNTED_CONSENTS = ['stats', 'stats_and_account'];

    private const ANSWER_COLUMNS = [
        'school_id', 'consent', 'incident_year', 'incident_season', 'setting', 'perpetrator', 'reported_to_school',
        'school_channels', 'reported_to_police', 'not_reported_reasons', 'school_outcomes', 'response_rating',
        'name_scan_confirmed',
    ];

    private const ACCOUNT = 'survivor_reports.account';
    private const PUBLISHED = 'survivor_reports.published';
    private const NOTE = 'survivor_reports.admin_note';

    /** @param array $answers ReportInput values, with 'account' in plain text */
    public static function create(int $caseId, array $answers, string $status): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $columns = self::answerColumns($answers) + [
            'case_id' => $caseId,
            'status' => $status,
            'account_encrypted' => SealedText::seal($answers['account'] ?? null, self::ACCOUNT),
            'submitted_at' => $now,
            'updated_at' => $now,
        ];

        $names = array_keys($columns);
        $statement = Database::connection()->prepare(
            'INSERT INTO survivor_reports (' . implode(', ', $names) . ') VALUES (' . implode(', ', array_fill(0, count($names), '?')) . ')'
        );
        $statement->execute(array_values($columns));

        return (int) Database::connection()->lastInsertId();
    }

    /** Her edit: new answers and account. The published version no longer matches, so it is cleared. */
    public static function updateAnswers(int $id, array $answers, string $status): void
    {
        $columns = self::answerColumns($answers) + [
            'status' => $status,
            'account_encrypted' => SealedText::seal($answers['account'] ?? null, self::ACCOUNT),
            'published_encrypted' => null,
            'published_by' => null,
            'published_saved_at' => null,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];

        self::write($id, $columns);
    }

    public static function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT r.*, s.name AS school_name, s.state AS school_state, s.slug AS school_slug, s.unitid AS school_unitid
             FROM survivor_reports r JOIN schools s ON s.id = r.school_id
             WHERE r.id = ? LIMIT 1'
        );
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    public static function findByCase(int $caseId): ?array
    {
        $statement = Database::connection()->prepare('SELECT id FROM survivor_reports WHERE case_id = ? LIMIT 1');
        $statement->execute([$caseId]);
        $id = $statement->fetchColumn();

        return $id === false ? null : self::find((int) $id);
    }

    /** @param array<string, mixed> $columns */
    public static function write(int $id, array $columns): void
    {
        if ($columns === []) {
            return;
        }

        $columns['updated_at'] ??= gmdate('Y-m-d H:i:s');
        $sets = implode(', ', array_map(static fn (string $column): string => $column . ' = ?', array_keys($columns)));
        $statement = Database::connection()->prepare("UPDATE survivor_reports SET {$sets} WHERE id = ?");
        $statement->execute([...array_values($columns), $id]);
    }

    public static function account(array $report): ?string
    {
        return SealedText::open($report['account_encrypted'] ?? null, self::ACCOUNT);
    }

    public static function published(array $report): ?string
    {
        return SealedText::open($report['published_encrypted'] ?? null, self::PUBLISHED);
    }

    public static function savePublished(int $id, string $text, int $adminId): void
    {
        self::write($id, [
            'published_encrypted' => SealedText::seal($text, self::PUBLISHED),
            'published_by' => $adminId,
            'published_saved_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public static function adminNote(array $report): ?string
    {
        return SealedText::open($report['admin_note_encrypted'] ?? null, self::NOTE);
    }

    public static function sealNote(?string $note): ?string
    {
        return $note === null || trim($note) === '' ? null : SealedText::seal($note, self::NOTE);
    }

    /** "a,b" → ['a', 'b'] */
    public static function listValue(?string $value): array
    {
        return $value === null || $value === '' ? [] : explode(',', $value);
    }

    /**
     * The admin queue, newest first, with each report's school and how many
     * evidence files it has.
     */
    public static function queue(?string $status): array
    {
        $statuses = $status !== null && in_array($status, [...self::QUEUE, 'approved', 'rejected'], true) ? [$status] : self::QUEUE;
        $placeholders = implode(', ', array_fill(0, count($statuses), '?'));

        $statement = Database::connection()->prepare(
            "SELECT r.id, r.status, r.consent, r.incident_year, r.submitted_at, r.updated_at, r.approved_at, r.rejected_at,
                    s.name AS school_name, s.state AS school_state,
                    (SELECT COUNT(*) FROM evidence_files e WHERE e.report_id = r.id) AS evidence_count
             FROM survivor_reports r JOIN schools s ON s.id = r.school_id
             WHERE r.status IN ({$placeholders})
             ORDER BY r.submitted_at DESC, r.id DESC
             LIMIT 200"
        );
        $statement->execute($statuses);

        return $statement->fetchAll();
    }

    public static function countForSchool(int $schoolId): int
    {
        $statement = Database::connection()->prepare('SELECT COUNT(*) FROM survivor_reports WHERE school_id = ?');
        $statement->execute([$schoolId]);

        return (int) $statement->fetchColumn();
    }

    /** @return array<string, int> status => reports */
    public static function countsByStatus(): array
    {
        $counts = array_fill_keys(['private', ...self::QUEUE, 'approved', 'rejected'], 0);
        foreach (Database::connection()->query('SELECT status, COUNT(*) AS total FROM survivor_reports GROUP BY status')->fetchAll() as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    private static function answerColumns(array $answers): array
    {
        $columns = [];
        foreach (self::ANSWER_COLUMNS as $column) {
            $value = $answers[$column] ?? null;
            $columns[$column] = is_array($value) ? ($value === [] ? null : implode(',', $value)) : $value;
        }

        // The two yes/no flags are never null.
        $columns['reported_to_school'] = (int) ($columns['reported_to_school'] ?? 0);
        $columns['name_scan_confirmed'] = (int) ($columns['name_scan_confirmed'] ?? 0);

        return $columns;
    }
}

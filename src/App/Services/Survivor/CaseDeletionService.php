<?php

namespace Keel\App\Services\Survivor;

use Keel\App\Models\EvidenceFile;
use Keel\App\Models\SurvivorReport;
use Keel\App\Support\Config;
use Keel\Core\Database;

/**
 * Withdrawal, and the 30-day purge of rejected reports: deletes a case and
 * everything that belongs to it.
 *
 *   - the case row (key hash, lookup id, email) and, through the foreign
 *     keys, her report, every evidence row, every share link and the
 *     report's moderation history;
 *   - both encrypted copies of every evidence file, from disk;
 *   - the links from the admin activity log: entries about this report or its
 *     files keep the admin, the action and the time (the audit trail of what
 *     admins did) but lose the report and file ids, so nothing left points to
 *     her.
 *
 * She drops out of every statistic at once: figures are counted from the
 * reports table each time a page is shown.
 *
 * The one exception: a file an admin quarantined as illegal content is kept,
 * encrypted and detached from the case (report_id NULL), when
 * evidence.preserve_quarantined is true, because the law may require it to be
 * preserved (docs/ILLEGAL-CONTENT.md). That setting is for the lawyer to
 * decide.
 *
 * Rows go first, in one transaction, then files. If deleting a file fails,
 * what is left on disk is unreadable: its key went with its row.
 */
final class CaseDeletionService
{
    public function __construct(private readonly VaultService $vault = new VaultService())
    {
    }

    /** @return array{files_deleted: int, files_preserved: int} */
    public function delete(int $caseId): array
    {
        $report = SurvivorReport::findByCase($caseId);
        $files = $report !== null ? EvidenceFile::forReport((int) $report['id']) : [];
        $preserveQuarantined = (bool) Config::get('evidence.preserve_quarantined', true);

        $toDelete = [];
        $toPreserve = [];
        foreach ($files as $file) {
            if ($preserveQuarantined && EvidenceFile::isQuarantined($file)) {
                $toPreserve[] = (int) $file['id'];
            } else {
                $toDelete[] = $file;
            }
        }

        $connection = Database::connection();
        $connection->beginTransaction();

        try {
            if ($report !== null) {
                $this->detachActivityLog((int) $report['id'], array_map(static fn (array $file): int => (int) $file['id'], $files));
            }

            if ($toPreserve !== []) {
                $placeholders = implode(', ', array_fill(0, count($toPreserve), '?'));
                $connection->prepare("DELETE FROM share_link_files WHERE evidence_file_id IN ({$placeholders})")->execute($toPreserve);
                $connection->prepare("UPDATE evidence_files SET report_id = NULL, name_encrypted = '' WHERE id IN ({$placeholders})")->execute($toPreserve);
            }

            $connection->prepare('DELETE FROM survivor_cases WHERE id = ?')->execute([$caseId]);
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        foreach ($toDelete as $file) {
            $this->vault->deleteFiles($file);
        }

        return ['files_deleted' => count($toDelete), 'files_preserved' => count($toPreserve)];
    }

    /** Case ids of reports rejected more than rejected_retention_days ago. */
    public static function expiredRejections(): array
    {
        $days = max(1, (int) Config::get('survivor_reports.rejected_retention_days', 30));
        $statement = Database::connection()->prepare("SELECT case_id FROM survivor_reports WHERE status = 'rejected' AND rejected_at <= ?");
        $statement->execute([gmdate('Y-m-d H:i:s', time() - $days * 86400)]);

        return array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    private function detachActivityLog(int $reportId, array $fileIds): void
    {
        $connection = Database::connection();
        $connection->prepare(
            "UPDATE activity_log SET subject_id = NULL, metadata = NULL WHERE subject_type = 'SurvivorReport' AND subject_id = ?"
        )->execute([$reportId]);

        if ($fileIds !== []) {
            $placeholders = implode(', ', array_fill(0, count($fileIds), '?'));
            $connection->prepare(
                "UPDATE activity_log SET subject_id = NULL, metadata = NULL WHERE subject_type = 'EvidenceFile' AND subject_id IN ({$placeholders})"
            )->execute($fileIds);
        }
    }
}

<?php

namespace Keel\App\Services\Survivor;

use Keel\Core\Database;

/**
 * Moves everything the vault holds from one master key to another, for
 * `php database/console.php vault:rotate` when VAULT_MASTER_KEY may have
 * leaked.
 *
 *   - Every evidence file, both copies, is decrypted under the old key and
 *     written again with a new file key under the new one (a new name on
 *     disk); the row is switched over, then the old files are deleted.
 *   - Every encrypted text column (SealedText::COLUMNS) is re-sealed.
 *   - Case-key lookup ids cannot be recomputed (the keys are never stored),
 *     so the old lookup key carries on as VAULT_LOOKUP_KEY. It decrypts
 *     nothing.
 *
 * It can stop part-way and be run again: anything that already opens under
 * the new key is skipped. While it runs, the site cannot read what has moved,
 * so submissions are off and moderation waits (the command insists on the
 * first).
 *
 * What it cannot do: make a copy someone already took readable only to them.
 * Backups made before the rotation still open with the old key.
 */
final class KeyRotationService
{
    public function __construct(
        private readonly VaultKeys $old,
        private readonly VaultKeys $new,
        private readonly VaultService $vault = new VaultService(),
    ) {
    }

    /** @return array{files_moved: int, files_already: int, texts_moved: int, texts_already: int} */
    public function run(?callable $progress = null): array
    {
        if ($this->old->sameMasterAs($this->new)) {
            throw new \InvalidArgumentException('The new key is the same as the current one.');
        }

        $counts = ['files_moved' => 0, 'files_already' => 0, 'texts_moved' => 0, 'texts_already' => 0];
        $connection = Database::connection();

        foreach ($connection->query('SELECT * FROM evidence_files ORDER BY id')->fetchAll() as $file) {
            if ($this->vault->opensWith($file, $this->new)) {
                $counts['files_already']++;
                continue;
            }

            $moved = $this->vault->reencrypt($file, $this->old, $this->new);
            $update = $connection->prepare(
                'UPDATE evidence_files SET blob_name = ?, blob_key = ?, admin_blob_name = ?, admin_blob_key = ? WHERE id = ? AND blob_name = ?'
            );
            $update->execute([$moved['blob_name'], $moved['blob_key'], $moved['admin_blob_name'], $moved['admin_blob_key'], (int) $file['id'], $file['blob_name']]);

            if ($update->rowCount() === 1) {
                $this->vault->deleteFiles($file);
                $counts['files_moved']++;
            } else {
                $this->vault->deleteFiles($moved); // the row changed meanwhile: keep it as it is
            }

            if ($progress !== null) {
                $progress('file', (int) $file['id']);
            }
        }

        foreach (SealedText::COLUMNS as [$table, $column, $context]) {
            $rows = $connection->query("SELECT id, {$column} AS sealed FROM {$table} WHERE {$column} IS NOT NULL AND {$column} <> ''")->fetchAll();
            $update = $connection->prepare("UPDATE {$table} SET {$column} = ? WHERE id = ? AND {$column} = ?");

            foreach ($rows as $row) {
                if (SealedText::opensWith((string) $row['sealed'], $context, $this->new)) {
                    $counts['texts_already']++;
                    continue;
                }

                $plaintext = SealedText::open((string) $row['sealed'], $context, $this->old);
                $update->execute([SealedText::seal($plaintext, $context, $this->new), (int) $row['id'], $row['sealed']]);
                $counts['texts_moved'] += $update->rowCount();
            }
        }

        return $counts;
    }
}

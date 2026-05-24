<?php
/**
 * Migrator — runs versioned .sql files from /db/migrations/.
 *
 * Each file is tracked in `schema_migrations` (file + sha256 checksum).
 * Running a migration twice is a no-op. If a file is modified after
 * being applied, the runner flags a checksum drift but does NOT re-run
 * (to avoid surprising the DBA).
 *
 * Usage:
 *   $m = new Migrator();
 *   $pending = $m->pending();
 *   $m->apply('2026-05-fundhub-isins.sql', $userId);
 */
declare(strict_types=1);

namespace Mori;

use PDO;
use RuntimeException;

final class Migrator
{
    private string $dir;
    private Database $db;

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?? (dirname(__DIR__) . '/db/migrations');
        $this->db  = Database::instance();
        $this->ensureTable();
    }

    /** Create schema_migrations table if it doesn't exist yet. */
    private function ensureTable(): void
    {
        try {
            $this->db->pdo()->exec('
                CREATE TABLE IF NOT EXISTS schema_migrations (
                    id          INT AUTO_INCREMENT PRIMARY KEY,
                    file        VARCHAR(190) NOT NULL UNIQUE,
                    checksum    VARCHAR(64)  NOT NULL,
                    applied_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    applied_by  INT NULL,
                    notes       TEXT NULL,
                    INDEX idx_file (file)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ');
        } catch (\Throwable $e) {
            // Table might already exist — ignore
        }
    }

    /** All .sql files in the migrations directory, sorted by filename. */
    public function discover(): array
    {
        if (!is_dir($this->dir)) return [];
        $files = [];
        foreach (scandir($this->dir) as $f) {
            if ($f === '.' || $f === '..') continue;
            if (!str_ends_with($f, '.sql')) continue;
            if (str_starts_with($f, '_')) continue;          // _schema_migrations.sql etc. are internal
            $files[] = $f;
        }
        sort($files);
        return $files;
    }

    public function applied(): array
    {
        try {
            $rows = $this->db->fetchAll('SELECT * FROM schema_migrations ORDER BY id');
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) $out[$r['file']] = $r;
        return $out;
    }

    public function pending(): array
    {
        $all = $this->discover();
        $done = $this->applied();
        return array_values(array_filter($all, fn($f) => !isset($done[$f])));
    }

    public function drift(): array
    {
        $done  = $this->applied();
        $drift = [];
        foreach ($done as $file => $row) {
            $path = $this->dir . '/' . $file;
            if (!is_readable($path)) {
                $drift[$file] = 'missing on disk';
                continue;
            }
            $current = hash('sha256', (string)file_get_contents($path));
            if (!hash_equals($row['checksum'], $current)) {
                $drift[$file] = 'modified after apply';
            }
        }
        return $drift;
    }

    public function preview(string $file): string
    {
        $path = $this->dir . '/' . $file;
        if (!is_readable($path)) throw new RuntimeException("Migration not found: $file");
        return (string) file_get_contents($path);
    }

    public function apply(string $file, ?int $userId = null, ?string $notes = null): array
    {
        $path = $this->dir . '/' . $file;
        if (!is_readable($path)) throw new RuntimeException("Migration not found: $file");
        $sql  = (string) file_get_contents($path);
        $hash = hash('sha256', $sql);

        // Already applied?
        $existing = $this->db->fetchOne('SELECT * FROM schema_migrations WHERE file = :f', ['f' => $file]);
        if ($existing) {
            return ['skipped' => true, 'reason' => 'already applied'];
        }

        // Split statements (strip /* */ + -- comments, then split on `;\n` or EOF)
        $sqlClean = preg_replace('!/\*.*?\*/!s', '', $sql);
        $cleaned  = [];
        foreach (preg_split("/\r?\n/", (string)$sqlClean) as $line) {
            if (str_starts_with(ltrim($line), '--')) continue;
            $cleaned[] = $line;
        }
        $statements = preg_split('/;\s*(?:\r?\n|$)/', implode("\n", $cleaned));

        $count = 0;
        $pdo = $this->db->pdo();
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '') continue;
            $pdo->exec($stmt);
            $count++;
        }

        $this->db->insert('schema_migrations', [
            'file'       => $file,
            'checksum'   => $hash,
            'applied_by' => $userId,
            'notes'      => $notes,
        ]);

        return ['applied' => true, 'statements' => $count];
    }

    public function applyAll(?int $userId = null): array
    {
        $results = [];
        foreach ($this->pending() as $f) {
            $results[$f] = $this->apply($f, $userId, 'batch run');
        }
        return $results;
    }
}

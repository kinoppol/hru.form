<?php
declare(strict_types=1);

/**
 * Simple file-based migration runner.
 *
 * Migration files live in /migrations, named:
 *   0001_create_admin_users_table.php
 * Each file must return an array with 'up' and optionally 'down' closures
 * that receive a PDO connection:
 *   return [
 *       'up' => function (PDO $pdo) { ... },
 *       'down' => function (PDO $pdo) { ... },
 *   ];
 */
final class Migrator
{
    private PDO $pdo;
    private string $path;

    public function __construct(PDO $pdo, string $path)
    {
        $this->pdo = $pdo;
        $this->path = rtrim($path, '/');
        $this->ensureMigrationsTable();
    }

    private function ensureMigrationsTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(191) NOT NULL UNIQUE,
                batch INT UNSIGNED NOT NULL,
                applied_at DATETIME NOT NULL,
                KEY migrations_batch_idx (batch)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /** @return string[] migration filenames (without .php) sorted ascending */
    public function allMigrationFiles(): array
    {
        $files = glob($this->path . '/*.php') ?: [];
        sort($files, SORT_STRING);
        return array_map(static fn ($f) => basename($f, '.php'), $files);
    }

    /** @return string[] migration names already applied, in applied order */
    public function appliedMigrations(): array
    {
        $stmt = $this->pdo->query('SELECT migration FROM migrations ORDER BY id ASC');
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function pendingMigrations(): array
    {
        $applied = $this->appliedMigrations();
        return array_values(array_diff($this->allMigrationFiles(), $applied));
    }

    private function nextBatchNumber(): int
    {
        $max = (int) $this->pdo->query('SELECT COALESCE(MAX(batch), 0) FROM migrations')->fetchColumn();
        return $max + 1;
    }

    private function loadMigration(string $name): array
    {
        $file = $this->path . '/' . $name . '.php';
        if (!file_exists($file)) {
            throw new RuntimeException("Migration file not found: {$name}");
        }
        $definition = require $file;
        if (!is_array($definition) || !isset($definition['up']) || !is_callable($definition['up'])) {
            throw new RuntimeException("Migration {$name} must return an array with an 'up' callable.");
        }
        return $definition;
    }

    /** @return string[] names of migrations that were run */
    public function migrate(): array
    {
        $pending = $this->pendingMigrations();
        if (empty($pending)) {
            return [];
        }

        $batch = $this->nextBatchNumber();
        $ran = [];

        foreach ($pending as $name) {
            $definition = $this->loadMigration($name);
            try {
                // Not wrapped in a transaction: DDL statements (CREATE TABLE, etc.)
                // cause an implicit commit in MySQL/MariaDB, which would silently
                // end any transaction started here anyway.
                ($definition['up'])($this->pdo);
                $stmt = $this->pdo->prepare('INSERT INTO migrations (migration, batch, applied_at) VALUES (?, ?, NOW())');
                $stmt->execute([$name, $batch]);
                $ran[] = $name;
            } catch (Throwable $e) {
                throw new RuntimeException("Migration {$name} failed: " . $e->getMessage(), 0, $e);
            }
        }

        return $ran;
    }

    /** Roll back the most recent batch. @return string[] names rolled back */
    public function rollbackLastBatch(): array
    {
        $lastBatch = (int) $this->pdo->query('SELECT COALESCE(MAX(batch), 0) FROM migrations')->fetchColumn();
        if ($lastBatch === 0) {
            return [];
        }

        $stmt = $this->pdo->prepare('SELECT migration FROM migrations WHERE batch = ? ORDER BY id DESC');
        $stmt->execute([$lastBatch]);
        $names = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $rolledBack = [];
        foreach ($names as $name) {
            $definition = $this->loadMigration($name);
            try {
                if (isset($definition['down']) && is_callable($definition['down'])) {
                    ($definition['down'])($this->pdo);
                }
                $del = $this->pdo->prepare('DELETE FROM migrations WHERE migration = ?');
                $del->execute([$name]);
                $rolledBack[] = $name;
            } catch (Throwable $e) {
                throw new RuntimeException("Rollback of {$name} failed: " . $e->getMessage(), 0, $e);
            }
        }

        return $rolledBack;
    }

    /** @return array<int, array{migration:string, batch:?int, applied_at:?string, status:string}> */
    public function status(): array
    {
        $applied = [];
        foreach ($this->pdo->query('SELECT migration, batch, applied_at FROM migrations') as $row) {
            $applied[$row['migration']] = $row;
        }

        $rows = [];
        foreach ($this->allMigrationFiles() as $name) {
            if (isset($applied[$name])) {
                $rows[] = [
                    'migration' => $name,
                    'batch' => (int) $applied[$name]['batch'],
                    'applied_at' => $applied[$name]['applied_at'],
                    'status' => 'applied',
                ];
            } else {
                $rows[] = [
                    'migration' => $name,
                    'batch' => null,
                    'applied_at' => null,
                    'status' => 'pending',
                ];
            }
        }

        return $rows;
    }
}

<?php

declare(strict_types=1);

$basePath = dirname(__DIR__, 2);
require_once $basePath . '/app/bootstrap.php';

use App\Core\Database;

$command = ltrim($argv[1] ?? 'run', '-');

switch ($command) {
    case 'run':
        runMigrations();
        break;
    case 'rollback':
        rollbackLastBatch();
        break;
    case 'status':
        showStatus();
        break;
    default:
        echo "Usage: php app/console/migrate.php [run|rollback|status]\n";
        exit(1);
}

function getDsnWithoutDb(): string
{
    $config = require base_path('config/database.php');
    return "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4";
}

function ensureDatabase(): void
{
    $config = require base_path('config/database.php');
    $dsn = getDsnWithoutDb();
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $db = $config['database'];
    $charset = $config['charset'] ?? 'utf8mb4';
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET {$charset} COLLATE {$charset}_unicode_ci");
    $pdo = null;
}

function ensureMigrationsTable(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        migration VARCHAR(190) NOT NULL,
        batch INT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_migration (migration)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function getMigrationFiles(): array
{
    $dir = base_path('database/migrations');
    if (!is_dir($dir)) {
        return [];
    }

    $files = glob($dir . '/*.php');
    $files = array_filter($files, fn($f) => is_file($f));
    sort($files);

    return $files;
}

function getAppliedMigrations(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT migration FROM migrations ORDER BY id ASC");
    $applied = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $applied[] = $row['migration'];
    }
    return $applied;
}

function runMigrations(): void
{
    ensureDatabase();

    $config = require base_path('config/database.php');
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ]);

    ensureMigrationsTable($pdo);

    $files = getMigrationFiles();
    $applied = getAppliedMigrations($pdo);
    $pending = [];

    foreach ($files as $file) {
        $name = basename($file);
        if (!in_array($name, $applied)) {
            $pending[] = $file;
        }
    }

    if (empty($pending)) {
        echo "Nothing to migrate. All migrations already applied.\n";
        return;
    }

    $stmt = $pdo->query("SELECT MAX(batch) as mb FROM migrations");
    $row = $stmt->fetch();
    $maxBatch = $row ? (int)$row['mb'] : 0;
    $batch = $maxBatch + 1;

    $appliedCount = 0;
    $failedFile = null;

    foreach ($pending as $file) {
        $name = basename($file);
        $migration = include $file;

        if (!isset($migration['up']) || !is_callable($migration['up'])) {
            echo "ERROR: Migration {$name} missing 'up' callable.\n";
            $failedFile = $name;
            break;
        }

        try {
            $migration['up']($pdo);
            $pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)")->execute([$name, $batch]);
            echo "  Migrated: {$name}\n";
            $appliedCount++;
        } catch (\Throwable $e) {
            echo "  FAILED: {$name}\n";
            echo "  Error: {$e->getMessage()}\n";
            $failedFile = $name;
            break;
        }
    }

    if ($failedFile) {
        echo "\nMigration failed at: {$failedFile}\n";
        echo "Applied {$appliedCount} migration(s) in this batch before failure.\n";
        exit(1);
    }

    echo "\nDone. Applied {$appliedCount} migration(s) in batch {$batch}.\n";
}

function rollbackLastBatch(): void
{
    ensureDatabase();

    $config = require base_path('config/database.php');
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ]);

    ensureMigrationsTable($pdo);

    $stmt = $pdo->query("SELECT MAX(batch) as mb FROM migrations");
    $row = $stmt->fetch();
    $lastBatch = $row ? (int)$row['mb'] : 0;

    if ($lastBatch === 0) {
        echo "Nothing to rollback. No migrations have been applied.\n";
        return;
    }

    $stmt = $pdo->prepare("SELECT migration FROM migrations WHERE batch = ? ORDER BY id DESC");
    $stmt->execute([$lastBatch]);
    $migrations = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Rolling back batch {$lastBatch} (" . count($migrations) . " migration(s))...\n";

    $rolledBack = 0;
    foreach ($migrations as $name) {
        $file = base_path('database/migrations/' . $name);
        if (!file_exists($file)) {
            echo "  WARNING: File not found: {$name} (skipping down)\n";
            $pdo->prepare("DELETE FROM migrations WHERE migration = ?")->execute([$name]);
            $rolledBack++;
            continue;
        }

        $migration = include $file;
        if (!isset($migration['down']) || !is_callable($migration['down'])) {
            echo "  WARNING: {$name} missing 'down' callable (skipping)\n";
            $pdo->prepare("DELETE FROM migrations WHERE migration = ?")->execute([$name]);
            $rolledBack++;
            continue;
        }

        try {
            $migration['down']($pdo);
            $pdo->prepare("DELETE FROM migrations WHERE migration = ?")->execute([$name]);
            echo "  Rolled back: {$name}\n";
            $rolledBack++;
        } catch (\Throwable $e) {
            echo "  FAILED to rollback: {$name}\n";
            echo "  Error: {$e->getMessage()}\n";
            echo "  Stopping rollback. {$rolledBack} migration(s) rolled back.\n";
            exit(1);
        }
    }

    echo "\nDone. Rolled back {$rolledBack} migration(s).\n";
}

function showStatus(): void
{
    ensureDatabase();

    $config = require base_path('config/database.php');
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ]);

    ensureMigrationsTable($pdo);

    $files = getMigrationFiles();

    $stmt = $pdo->query("SELECT migration, batch, created_at FROM migrations ORDER BY id ASC");
    $appliedDetails = [];
    while ($row = $stmt->fetch()) {
        $appliedDetails[$row['migration']] = $row;
    }

    echo str_repeat('-', 80) . "\n";
    echo sprintf("  %-50s %-10s %-18s\n", "Migration", "Batch", "Applied At");
    echo str_repeat('-', 80) . "\n";

    foreach ($files as $file) {
        $name = basename($file);
        if (isset($appliedDetails[$name])) {
            $d = $appliedDetails[$name];
            echo sprintf("  %-50s %-10s %-18s\n", $name, $d['batch'], $d['created_at']);
        } else {
            echo sprintf("  %-50s %-10s %-18s\n", $name, '-', 'NOT APPLIED');
        }
    }

    echo str_repeat('-', 80) . "\n";

    $total = count($files);
    $appliedCount = count($appliedDetails);
    $pendingCount = $total - $appliedCount;

    echo "  Total: {$total} | Applied: {$appliedCount} | Pending: {$pendingCount}\n\n";
}

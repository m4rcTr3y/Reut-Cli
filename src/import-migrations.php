<?php
declare(strict_types=1);

use Reut\DB\DataBase;
use Reut\DB\Exceptions\DatabaseConnectionException;
use Reut\DB\Exceptions\DatabaseQueryException;
use Reut\Support\ProjectPath;

require ProjectPath::resolve('vendor', 'autoload.php');
require ProjectPath::resolve('config.php');

/**
 * Import Migration History Command
 * 
 * Imports migration history from JSON or SQL file to sync migration state across environments.
 * 
 * Usage:
 *   php manage.php import-migrations migrations.json
 *   php manage.php import-migrations migrations.sql
 */

// DatabaseCreator passes command name as $argv[1], file path as $argv[2]
$filePath = $argv[2] ?? $argv[1] ?? null;

if (!$filePath) {
    echo "Usage: php manage.php import-migrations <file>\n";
    echo "  file: Path to JSON or SQL file containing migration history\n";
    exit(1);
}

// Resolve relative paths
if (!file_exists($filePath)) {
    // Try relative to current working directory
    $cwd = getcwd();
    $relativePath = $cwd . '/' . $filePath;
    if (file_exists($relativePath)) {
        $filePath = $relativePath;
    } elseif (file_exists(ProjectPath::resolve($filePath))) {
        $filePath = ProjectPath::resolve($filePath);
    } else {
        $originalPath = $argv[2] ?? $argv[1] ?? 'unknown';
        echo "Error: File '{$originalPath}' not found.\n";
        echo "Searched in: {$cwd}/{$originalPath}\n";
        echo "Searched in: " . ProjectPath::resolve($originalPath) . "\n";
        exit(1);
    }
}

$baseDb = new DataBase($config);
try {
    if (!$baseDb->connect()) {
        throw new DatabaseConnectionException(
            "Failed to connect to the database",
            0,
            null,
            $config
        );
    }

    // Ensure migrations table exists
    $migrationsTableSql = "
        CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL UNIQUE,
            sql_text TEXT NOT NULL,
            batch INT NOT NULL,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
    $baseDb->execute($migrationsTableSql);

    // Determine file format
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $content = file_get_contents($filePath);

    if ($extension === 'json' || json_decode($content) !== null) {
        // JSON format
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON file: " . json_last_error_msg());
        }

        if (!isset($data['migrations']) || !is_array($data['migrations'])) {
            throw new \Exception("Invalid JSON format: 'migrations' array not found");
        }

        $migrations = $data['migrations'];
        echo "Importing " . count($migrations) . " migration(s) from JSON...\n";

        foreach ($migrations as $migration) {
            $name = $migration['name'] ?? null;
            $sqlText = $migration['sql_text'] ?? null;
            $batch = $migration['batch'] ?? null;

            if (!$name || !$sqlText || $batch === null) {
                echo "⚠ Skipping invalid migration entry\n";
                continue;
            }

            // Use INSERT IGNORE to prevent duplicates
            $result = $baseDb->execute(
                "INSERT IGNORE INTO migrations (name, sql_text, batch, applied_at) VALUES (:name, :sql_text, :batch, :applied_at)",
                [
                    'name' => $name,
                    'sql_text' => $sqlText,
                    'batch' => (int)$batch,
                    'applied_at' => $migration['applied_at'] ?? null
                ]
            );

            if ($result) {
                echo "✓ Imported: {$name}\n";
            } else {
                echo "⚠ Skipped (already exists): {$name}\n";
            }
        }
    } elseif ($extension === 'sql' || strpos($content, 'INSERT INTO migrations') !== false) {
        // SQL format - execute SQL statements
        echo "Importing migrations from SQL file...\n";
        
        // Split SQL file into statements
        $statements = array_filter(
            array_map('trim', explode(';', $content)),
            fn($stmt) => !empty($stmt) && !preg_match('/^--/', $stmt)
        );

        $imported = 0;
        foreach ($statements as $statement) {
            if (preg_match('/INSERT\s+INTO\s+migrations/i', $statement)) {
                try {
                    $baseDb->execute($statement);
                    $imported++;
                    // Extract migration name from INSERT statement for output
                    // Pattern: INSERT INTO migrations (name, sql_text, batch, applied_at) VALUES ('migration_name', ...)
                    // The name is the first value after VALUES
                    if (preg_match("/VALUES\s*\(\s*'([^']+)'/", $statement, $matches)) {
                        $migrationName = $matches[1] ?? null;
                        if ($migrationName) {
                            echo "✓ Imported: {$migrationName}\n";
                        }
                    }
                } catch (\Exception $e) {
                    // Ignore duplicate key errors (migration already exists)
                    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                        // Extract migration name for skip message
                        if (preg_match("/VALUES\s*\(\s*'([^']+)'/", $statement, $matches)) {
                            $migrationName = $matches[1] ?? null;
                            if ($migrationName) {
                                echo "⚠ Skipped (already exists): {$migrationName}\n";
                            }
                        }
                    } else {
                        echo "⚠ Error executing SQL: " . $e->getMessage() . "\n";
                    }
                }
            }
        }

        echo "\n✓ Imported {$imported} migration(s) from SQL.\n";
    } else {
        throw new \Exception("Unsupported file format. Use JSON or SQL.");
    }

    echo "\n✓ Migration import completed successfully!\n";
} catch (DatabaseConnectionException $e) {
    echo "Database Connection Error: " . $e->getFormattedMessage() . "\n";
    exit(1);
} catch (DatabaseQueryException $e) {
    echo "Database Query Error: " . $e->getFormattedMessage() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}


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

if (count($argv) < 2) {
    echo "Usage: php manage.php import-migrations <file>\n";
    echo "  file: Path to JSON or SQL file containing migration history\n";
    exit(1);
}

$filePath = $argv[1];

if (!file_exists($filePath)) {
    echo "Error: File '{$filePath}' not found.\n";
    exit(1);
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
                } catch (\Exception $e) {
                    // Ignore duplicate key errors (migration already exists)
                    if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                        echo "⚠ Error executing SQL: " . $e->getMessage() . "\n";
                    }
                }
            }
        }

        echo "✓ Imported {$imported} migration(s) from SQL.\n";
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


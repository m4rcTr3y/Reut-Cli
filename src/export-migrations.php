<?php
declare(strict_types=1);

use Reut\DB\DataBase;
use Reut\DB\Exceptions\DatabaseConnectionException;
use Reut\DB\Exceptions\DatabaseQueryException;
use Reut\Support\ProjectPath;

require ProjectPath::resolve('vendor', 'autoload.php');
require ProjectPath::resolve('config.php');

/**
 * Export Migration History Command
 * 
 * Exports migration history to JSON or SQL format for syncing across environments.
 * 
 * Usage:
 *   php manage.php export-migrations              # Output to stdout (JSON)
 *   php manage.php export-migrations > migrations.json
 *   php manage.php export-migrations --format=sql > migrations.sql
 */

$options = parseExportOptions($argv ?? []);

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

    // Check if migrations table exists
    if (!$baseDb->tableExists('migrations')) {
        echo "No migrations table found. Nothing to export.\n";
        exit(0);
    }

    // Get all migrations
    $migrations = $baseDb->sqlQuery(
        "SELECT id, name, sql_text, batch, applied_at FROM migrations ORDER BY batch, id"
    );

    if (empty($migrations)) {
        echo "No migrations found. Nothing to export.\n";
        exit(0);
    }

    $format = $options['format'] ?? 'json';

    if ($format === 'sql') {
        // Export as SQL INSERT statements
        echo "-- Migration History Export\n";
        echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        echo "-- Total Migrations: " . count($migrations) . "\n\n";
        
        echo "CREATE TABLE IF NOT EXISTS migrations (\n";
        echo "    id INT AUTO_INCREMENT PRIMARY KEY,\n";
        echo "    name VARCHAR(255) NOT NULL UNIQUE,\n";
        echo "    sql_text TEXT NOT NULL,\n";
        echo "    batch INT NOT NULL,\n";
        echo "    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP\n";
        echo ");\n\n";

        foreach ($migrations as $migration) {
            $name = addslashes($migration['name']);
            $sqlText = addslashes($migration['sql_text']);
            $batch = (int)$migration['batch'];
            $appliedAt = $migration['applied_at'] ?? 'NULL';
            
            if ($appliedAt !== 'NULL') {
                $appliedAt = "'" . addslashes($appliedAt) . "'";
            }

            echo "INSERT INTO migrations (name, sql_text, batch, applied_at) VALUES (\n";
            echo "    '{$name}',\n";
            echo "    '{$sqlText}',\n";
            echo "    {$batch},\n";
            echo "    {$appliedAt}\n";
            echo ");\n\n";
        }
    } else {
        // Export as JSON (default)
        $export = [
            'exported_at' => date('c'),
            'total_migrations' => count($migrations),
            'migrations' => $migrations,
        ];

        echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }
} catch (DatabaseConnectionException $e) {
    fwrite(STDERR, "Database Connection Error: " . $e->getFormattedMessage() . "\n");
    exit(1);
} catch (DatabaseQueryException $e) {
    fwrite(STDERR, "Database Query Error: " . $e->getFormattedMessage() . "\n");
    exit(1);
} catch (\Exception $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}

/**
 * Parse command line options for export command
 */
function parseExportOptions(array $argv): array
{
    $options = [
        'format' => 'json',
    ];

    foreach ($argv as $arg) {
        if (strpos($arg, '--format=') === 0) {
            $format = substr($arg, 9);
            if (in_array($format, ['json', 'sql'], true)) {
                $options['format'] = $format;
            }
        }
    }

    return $options;
}


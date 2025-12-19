<?php
declare(strict_types=1);

namespace Reut\Utils;

use Reut\DB\DataBase;
use Reut\DB\Exceptions\DatabaseQueryException;
use Reut\Support\ProjectPath;

/**
 * MigrationHelper - Shared utilities for migration commands
 * 
 * This class extracts common code used across migration commands to reduce duplication
 * and ensure consistent behavior.
 */
class MigrationHelper
{
    /**
     * Load model classes from the models directory
     * 
     * @param array $config Database configuration
     * @return array Array of table instances, separated by whether they have relationships
     */
    public static function loadModels(array $config): array
    {
        $modelsDirectory = rtrim(ProjectPath::resolve('models'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $modelFiles = is_dir($modelsDirectory)
            ? array_filter(array_diff(scandir($modelsDirectory), ['.', '..']), fn($f) => str_ends_with($f, '.php'))
            : [];

        // Register autoloader for models
        spl_autoload_register(function ($class) use ($modelsDirectory) {
            $prefix = 'Reut\\Models\\';
            $baseDir = $modelsDirectory;

            if (strpos($class, $prefix) === 0) {
                $relativeClass = substr($class, strlen($prefix));
                $file = realpath($baseDir . str_replace('\\', '/', $relativeClass) . '.php');
                if (file_exists($file)) {
                    require_once $file;
                }
            }
        });

        $noRelations = [];
        $withRelations = [];

        foreach ($modelFiles as $fileName) {
            $className = 'Reut\\Models\\' . pathinfo($fileName, PATHINFO_FILENAME);

            if (class_exists($className)) {
                $tableInstance = new $className($config);
                if (method_exists($tableInstance, 'hasRelationships') && $tableInstance->hasRelationships()) {
                    $withRelations[] = $tableInstance;
                } else {
                    $noRelations[] = $tableInstance;
                }
            }
        }

        // Sort relations by relationship count
        usort($withRelations, fn($a, $b) => $a->getRelationshipCount() <=> $b->getRelationshipCount());

        return [
            'noRelations' => $noRelations,
            'withRelations' => $withRelations,
        ];
    }

    /**
     * Ensure migrations table exists
     * 
     * @param DataBase $baseDb Database instance
     * @return void
     */
    public static function ensureMigrationsTable(DataBase $baseDb): void
    {
        $migrationsTableSql = "
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL UNIQUE,
                sql_text TEXT NOT NULL,
                batch INT NOT NULL,
                applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
        $baseDb->execute($migrationsTableSql);
    }

    /**
     * Get the next batch number
     * 
     * @param DataBase $baseDb Database instance
     * @return int Next batch number
     */
    public static function getNextBatch(DataBase $baseDb): int
    {
        $batchQuery = $baseDb->sqlQuery("SELECT MAX(batch) as max_batch FROM migrations");
        $maxBatch = 0;
        if (is_array($batchQuery) && isset($batchQuery[0]['max_batch'])) {
            $maxBatch = (int) $batchQuery[0]['max_batch'];
        }
        return $maxBatch + 1;
    }

    /**
     * Generate migration name with timestamp
     * 
     * @param string $action Action type (create, add, drop)
     * @param string $tableName Table name
     * @param string|null $columnName Optional column name
     * @return string Migration name
     */
    public static function generateMigrationName(string $action, string $tableName, ?string $columnName = null): string
    {
        // Use microtime for better uniqueness (prevents collisions)
        $microtime = microtime(true);
        $timestamp = date('YmdHis', (int)$microtime);
        $microseconds = substr(str_replace('.', '', (string)$microtime), -6); // Last 6 digits
        
        switch ($action) {
            case 'create':
                return "create_{$tableName}_table_{$timestamp}_{$microseconds}";
            case 'add':
                return "add_{$columnName}_to_{$tableName}_table_{$timestamp}_{$microseconds}";
            case 'drop':
                return "drop_{$columnName}_from_{$tableName}_table_{$timestamp}_{$microseconds}";
            default:
                return "{$action}_{$tableName}_table_{$timestamp}_{$microseconds}";
        }
    }

    /**
     * Record a migration in the migrations table
     * 
     * @param DataBase $baseDb Database instance
     * @param string $migrationName Migration name
     * @param string $sql SQL statement
     * @param int $batch Batch number
     * @return bool Success status
     */
    public static function recordMigration(DataBase $baseDb, string $migrationName, string $sql, int $batch): bool
    {
        try {
            // Check if migration already exists
            $existing = $baseDb->sqlQuery(
                "SELECT id FROM migrations WHERE name = :name LIMIT 1",
                ['name' => $migrationName]
            );
            
            if (!empty($existing)) {
                return false; // Migration already exists
            }
            
            // Insert the migration
            $result = $baseDb->execute(
                "INSERT INTO migrations (name, sql_text, batch) VALUES (:name, :sql_text, :batch)",
                ['name' => $migrationName, 'sql_text' => $sql, 'batch' => $batch]
            );
            return $result;
        } catch (\PDOException $e) {
            // Check if it's a duplicate key error (error code 23000)
            if ($e->getCode() == '23000' || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                return false; // Migration already exists, not an error
            }
            // Re-throw as DatabaseQueryException for better error reporting
            $errorInfo = $e->errorInfo ?? ['', $e->getCode(), $e->getMessage()];
            throw new DatabaseQueryException(
                "Failed to record migration '{$migrationName}': " . $e->getMessage(),
                (int)$e->getCode(),
                $e,
                "INSERT INTO migrations (name, sql_text, batch) VALUES (:name, :sql_text, :batch)",
                ['name' => $migrationName, 'sql_text' => $sql, 'batch' => $batch],
                $errorInfo
            );
        }
    }

    /**
     * Check if a migration exists for a given action and table/column
     * 
     * @param DataBase $baseDb Database instance
     * @param string $action Action type (create, add, drop)
     * @param string $tableName Table name
     * @param string|null $columnName Optional column name
     * @return bool True if migration exists
     */
    public static function hasMigration(DataBase $baseDb, string $action, string $tableName, ?string $columnName = null): bool
    {
        $escapedTable = preg_quote($tableName, '/');
        $pattern = null;

        if ($action === 'create') {
            $pattern = "/create_{$escapedTable}_table/";
        } elseif ($action === 'add' && $columnName) {
            $escapedColumn = preg_quote($columnName, '/');
            $pattern = "/add_{$escapedColumn}_to_{$escapedTable}_table/";
        } elseif ($action === 'drop' && $columnName) {
            $escapedColumn = preg_quote($columnName, '/');
            $pattern = "/drop_{$escapedColumn}_from_{$escapedTable}_table/";
        }

        if ($pattern === null) {
            return false;
        }

        // Query existing migrations for this table
        $existingMigrations = $baseDb->sqlQuery("SELECT name FROM migrations WHERE name LIKE :pattern", [
            'pattern' => '%' . $tableName . '%'
        ]);

        if (empty($existingMigrations)) {
            return false;
        }

        foreach ($existingMigrations as $migration) {
            if (preg_match($pattern, $migration['name'])) {
                return true;
            }
        }

        return false;
    }
}


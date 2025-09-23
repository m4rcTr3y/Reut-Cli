<?php
// Updated migrate.php
// Changes:
// - Generate unique migration names with timestamp for table creation.
// - Removed check for existing migration name; apply if table doesn't exist.
// - Normalized table names to lowercase.

require __DIR__ . "/../vendor/autoload.php";
require __DIR__ . "/../config.php";

use Reut\DB\DataBase;
use Reut\DB\Exceptions\ConnectionError;

// Autoload models dynamically
spl_autoload_register(function ($class) {
    $prefix = 'Reut\\Models\\';
    $baseDir = __DIR__ . '/../models/';

    if (strpos($class, $prefix) === 0) {
        $relativeClass = substr($class, strlen($prefix));
        $file = realpath($baseDir . str_replace('\\', '/', $relativeClass) . '.php');
        if (file_exists($file)) {
            echo "Loading class: $file\n";
            require_once $file;
        }
    }
});

// Create database
$baseDb = new DataBase($config);
if ($baseDb->createDatabase($config['dbname'])) {
    echo "{$config['dbname']} Database created successfully.\n";
}

// Connect to the database
try {
    $baseDb->connect();

    // Create migrations table
    $migrationsTableSql = "
        CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL UNIQUE,
            sql_text TEXT NOT NULL,
            batch INT NOT NULL,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
    $baseDb->sqlQuery($migrationsTableSql);

    // Get current max batch and increment
    $batchQuery = $baseDb->sqlQuery("SELECT MAX(batch) as max_batch FROM migrations");
    $currentBatch = ($batchQuery['max_batch'] ?? 0) + 1;

    echo "Getting tables ...\n";

    // Get model files
    $modelFiles = array_diff(scandir(__DIR__ . '/../models/'), ['.', '..']);

    $noRelations = [];
    $withRelations = [];

    foreach ($modelFiles as $fileName) {
        echo "Loading class: $fileName\n";
        $className = 'Reut\\Models\\' . pathinfo($fileName, PATHINFO_FILENAME);

        if (class_exists($className)) {
            $tableInstance = new $className($config);
            if (property_exists($tableInstance, 'hasRelationships') && $tableInstance->hasRelationships) {
                $withRelations[] = $tableInstance;
            } else {
                $noRelations[] = $tableInstance;
            }
        } else {
            echo "Class $className does not exist.\n";
        }
    }

    usort($withRelations, fn($a, $b) => $a->relationships <=> $b->relationships);

    // Function to apply migration
    function applyMigration($baseDb, $tableInstance, $currentBatch): void
    {
        $tableName = $tableInstance->tableName;
        $timestamp = date('YmdHis');
        $migrationName = 'create_' . $tableName . '_table_' . $timestamp;

        // Get SQL from columns
        $sql = $tableInstance->genSQL();
        if ($sql === false) {
            throw new Exception("Failed to generate SQL for {$tableName}.");
        }

        // Execute table creation
        if ($tableInstance->createTable()) {
            $baseDb->sqlQuery(
                "INSERT INTO migrations (name, sql_text, batch) VALUES (:name, :sql_text, :batch)",
                ['name' => $migrationName, 'sql_text' => $sql, 'batch' => $currentBatch]
            );
            echo get_class($tableInstance) . " table created and migration recorded ({$migrationName}).\n";
        } else {
            throw new Exception("Error creating " . get_class($tableInstance) . " table.");
        }
    }

    // Create tables without relations
    foreach ($noRelations as $tableInstance) {
        $tableName = $tableInstance->tableName;
        if ($tableInstance->tableExists($tableName)) {
            echo get_class($tableInstance) . " already exists.\n";
        } else {
            applyMigration($baseDb, $tableInstance, $currentBatch);
        }
    }

    // Create tables with relations
    foreach ($withRelations as $tableInstance) {
        $tableName = $tableInstance->tableName;
        if ($tableInstance->tableExists($tableName)) {
            echo get_class($tableInstance) . " already exists.\n";
        } else {
            applyMigration($baseDb, $tableInstance, $currentBatch);
        }
    }

    echo "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
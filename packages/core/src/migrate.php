<?php
declare(strict_types=1);

// Updated migrate.php
// Changes:
// - Generate unique migration names with timestamp for table creation and column changes.
// - Check table and column schema to avoid re-running migrations for existing fields.
// - Apply create_table, add_column, and drop_column migrations as needed.
// - Use INSERT IGNORE to prevent duplicate migration records.
// - Normalized table names to lowercase.
// - Added check to skip recording migrations if table and columns already match model schema.
use Reut\DB\DataBase;
use Reut\DB\Exceptions\ConnectionError;
use Reut\Support\ProjectPath;

require ProjectPath::resolve('vendor', 'autoload.php');
require ProjectPath::resolve('config.php');

// Autoload models dynamically
spl_autoload_register(function ($class) {
    $prefix = 'Reut\\Models\\';
    $baseDir = rtrim(ProjectPath::resolve('models'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

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
    $baseDb->execute($migrationsTableSql);

    // Get current max batch and increment
    $batchQuery = $baseDb->sqlQuery("SELECT MAX(batch) as max_batch FROM migrations");
    $maxBatch = 0;
    if (is_array($batchQuery) && isset($batchQuery[0]['max_batch'])) {
        $maxBatch = (int) $batchQuery[0]['max_batch'];
    }
    $currentBatch = $maxBatch + 1;

    echo "Getting tables ...\n";

    // Get model files
    $modelsDirectory = rtrim(ProjectPath::resolve('models'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $modelFiles = is_dir($modelsDirectory)
        ? array_diff(scandir($modelsDirectory), ['.', '..'])
        : [];

    $noRelations = [];
    $withRelations = [];

    foreach ($modelFiles as $fileName) {
        echo "Loading class: $fileName\n";
        $className = 'Reut\\Models\\' . pathinfo($fileName, PATHINFO_FILENAME);

        if (class_exists($className)) {
            $tableInstance = new $className($config);
            if (method_exists($tableInstance, 'hasRelationships') && $tableInstance->hasRelationships()) {
                $withRelations[] = $tableInstance;
            } else {
                $noRelations[] = $tableInstance;
            }
        } else {
            echo "Class $className does not exist.\n";
        }
    }

    usort($withRelations, fn($a, $b) => $a->getRelationshipCount() <=> $b->getRelationshipCount());

    // Function to apply migrations for a table
    function applyMigration($baseDb, $tableInstance, $currentBatch): bool
    {
        $tableName = $tableInstance->tableName;
        $timestamp = date('YmdHis');

        // Query existing migrations for this table
        $existingMigrations = $baseDb->sqlQuery("SELECT name FROM migrations WHERE name LIKE '%$tableName%'");

        // Helper function to check if a migration exists
        $hasMigration = function ($action, $column = null) use ($existingMigrations, $tableName) {
            $escapedTable = preg_quote($tableName, '/');
            foreach ($existingMigrations as $migration) {
                if ($column) {
                    // Match column-specific migrations (add/drop)
                    $escapedColumn = preg_quote($column, '/');
                    if (preg_match("/{$action}_{$escapedColumn}_(to|from)_{$escapedTable}_table/", $migration['name'])) {
                        return true;
                    }
                } else {
                    // Match table creation
                    if (preg_match("/create_{$escapedTable}_table/", $migration['name'])) {
                        return true;
                    }
                }
            }
            return false;
        };

        $migrationsApplied = false;

        // Check if table creation is needed
        if (!$tableInstance->tableExists($tableName)) {
            if (!$hasMigration('create')) {
                $sql = $tableInstance->genSQL();
                if ($sql === false) {
                    throw new Exception("Failed to generate SQL for {$tableName}.");
                }
                $migrationName = 'create_' . $tableName . '_table_' . $timestamp;
                if ($tableInstance->createTable()) {
                    $insertResult = $baseDb->execute(
                        "INSERT IGNORE INTO migrations (name, sql_text, batch) VALUES (:name, :sql_text, :batch)",
                        ['name' => $migrationName, 'sql_text' => $sql, 'batch' => $currentBatch]
                    );
                    if ($insertResult) {
                        echo get_class($tableInstance) . " table created and migration recorded ({$migrationName}).\n";
                        $migrationsApplied = true;
                    } else {
                        echo "Warning: Table created but failed to record migration for " . get_class($tableInstance) . "\n";
                    }
                } else {
                    throw new Exception("Error creating " . get_class($tableInstance) . " table.");
                }
            } else {
                echo get_class($tableInstance) . " table creation migration already recorded.\n";
            }
        } else {
            // Check if table schema matches model
            $dbColumns = $tableInstance->getTableSchema($tableName);
            $modelColumns = array_filter($tableInstance->columns, fn($key) => strpos($key, 'FOREIGN KEY') === false, ARRAY_FILTER_USE_KEY);
            $modelColumnNames = array_keys($modelColumns);
            $missingColumns = array_diff($modelColumnNames, $dbColumns);
            $protected = $tableInstance->protectedColumns ?? [];
            $columnsToDrop = array_filter(
                array_diff($dbColumns, $modelColumnNames),
                fn($column) => !in_array($column, $protected, true)
            );

            // If no missing or extra columns, skip migration
            if (empty($missingColumns) && empty($columnsToDrop)) {
                echo get_class($tableInstance) . " table and columns fully match model, no migrations needed.\n";
                return false;
            }

            echo get_class($tableInstance) . " table exists, checking columns...\n";

            // Add missing columns
            foreach ($missingColumns as $column) {
                if (!$hasMigration('add', $column)) {
                    $definition = $tableInstance->columns[$column];
                    $migrationName = 'add_' . $column . '_to_' . $tableName . '_table_' . $timestamp;
                    $sql = $tableInstance->getAddColumnSQL($column, $definition);
                    $baseDb->execute($sql);
                    $insertResult = $baseDb->execute(
                        "INSERT IGNORE INTO migrations (name, sql_text, batch) VALUES (:name, :sql_text, :batch)",
                        ['name' => $migrationName, 'sql_text' => $sql, 'batch' => $currentBatch]
                    );
                    if ($insertResult) {
                        echo "Added column {$column} to {$tableName} and recorded migration ({$migrationName}).\n";
                        $migrationsApplied = true;
                    } else {
                        echo "Warning: Column {$column} added but failed to record migration for {$tableName}.\n";
                    }
                } else {
                    echo "Column {$column} add migration already recorded for {$tableName}.\n";
                }
            }

            // Drop extra columns
            foreach ($columnsToDrop as $column) {
                if (!$hasMigration('drop', $column)) {
                    $migrationName = 'drop_' . $column . '_from_' . $tableName . '_table_' . $timestamp;
                    $sql = $tableInstance->getDropColumnSQL($column);
                    $baseDb->execute($sql);
                    $insertResult = $baseDb->execute(
                        "INSERT IGNORE INTO migrations (name, sql_text, batch) VALUES (:name, :sql_text, :batch)",
                        ['name' => $migrationName, 'sql_text' => $sql, 'batch' => $currentBatch]
                    );
                    if ($insertResult) {
                        echo "Dropped column {$column} from {$tableName} and recorded migration ({$migrationName}).\n";
                        $migrationsApplied = true;
                    } else {
                        echo "Warning: Column {$column} dropped but failed to record migration for {$tableName}.\n";
                    }
                } else {
                    echo "Column {$column} drop migration already recorded for {$tableName}.\n";
                }
            }
        }

        return $migrationsApplied;
    }

    $migrationsApplied = false;

    // Apply migrations for tables without relations
    foreach ($noRelations as $tableInstance) {
        if (applyMigration($baseDb, $tableInstance, $currentBatch)) {
            $migrationsApplied = true;
        }
    }

    // Apply migrations for tables with relations
    foreach ($withRelations as $tableInstance) {
        if (applyMigration($baseDb, $tableInstance, $currentBatch)) {
            $migrationsApplied = true;
        }
    }

    if ($migrationsApplied) {
        echo "\nAll migrations applied successfully!\n";
    } else {
        echo "\nNo new migrations were needed.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
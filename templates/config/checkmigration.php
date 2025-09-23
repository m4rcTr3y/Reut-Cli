<?php


require __DIR__ . "/../vendor/autoload.php";
require __DIR__ . "/../config.php";
require __DIR__.'/utils/ascii_table.php';

use Reut\DB\DataBase;

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

$baseDb = new DataBase($config);
try {
    if (!$baseDb->connect()) {
        throw new Exception("Failed to connect to the database. Check your config or MySQL availability.");
    }

    // Check if migrations table exists
    if (!$baseDb->tableExists('migrations')) {
        echo "No migrations table found. Run migrate.php to create it.\n";
        exit;
    }

    // List applied migrations
    $migrationsQuery = $baseDb->sqlQuery("SELECT id, name, sql_text, batch, applied_at FROM migrations ORDER BY batch, id");
    $migrations = $migrationsQuery;
    if (empty($migrations)) {
        echo "No migrations have been applied.\n";
    } else {
      displayTable($migrations,"Applied Migrations");
    }

    // Check for pending migrations
    echo "\n=== Re-checking Models ===\n";
    $modelFiles = array_diff(scandir(__DIR__ . '/../models/'), ['.', '..']);
    $noRelations = [];
    $withRelations = [];

    // Load model classes
    foreach ($modelFiles as $fileName) {
        echo "Checking class: $fileName\n";
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

    $pendingMigrations = [];

    // Function to check pending migrations for a table
    function checkPendingMigration($baseDb, $tableInstance, &$pendingMigrations): void
    {
        $tableName = $tableInstance->tableName;
        $timestamp = date('YmdHis');

        // Check if table creation is pending
        if (!$tableInstance->tableExists($tableName)) {
            $sql = $tableInstance->genSQL();
            if ($sql !== false) {
                $migrationName = 'create_' . $tableName . '_table_' . $timestamp;
                $pendingMigrations[] = [
                    'name' => $migrationName,
                    'sql' => $sql,
                    'type' => 'create_table',
                    'class' => get_class($tableInstance)
                ];
            }
        }
        
        if($tableInstance->tableExists($tableName)){
            // Check for missing columns (to add)
            $dbColumns = $tableInstance->getTableSchema($tableName);
            $modelColumns = array_filter($tableInstance->columns, fn($key) => strpos($key, 'FOREIGN KEY') === false, ARRAY_FILTER_USE_KEY);
            $modelColumnNames = array_keys($modelColumns);
            $missingColumns = array_diff($modelColumnNames, $dbColumns);

            foreach ($missingColumns as $column) {
                $definition = $tableInstance->columns[$column];
                $migrationName = 'add_' . $column . '_to_' . $tableName . '_table_' . $timestamp;
                $sql = $tableInstance->getAddColumnSQL($column, $definition);
                $pendingMigrations[] = [
                    'name' => $migrationName,
                    'sql' => $sql,
                    'type' => 'add_column',
                    'class' => get_class($tableInstance)
                ];
            }

            // Check for columns to drop (in DB but not in model)
            $columnsToDrop = array_diff($dbColumns, $modelColumnNames);
            foreach ($columnsToDrop as $column) {
                $migrationName = 'drop_' . $column . '_from_' . $tableName . '_table_' . $timestamp;
                $sql = $tableInstance->getDropColumnSQL($column);
                $pendingMigrations[] = [
                    'name' => $migrationName,
                    'sql' => $sql,
                    'type' => 'drop_column',
                    'class' => get_class($tableInstance)
                ];
            }
        }
    }

    // Check tables without relations
    foreach ($noRelations as $tableInstance) {
        checkPendingMigration($baseDb, $tableInstance, $pendingMigrations);
    }

    // Check tables with relations
    foreach ($withRelations as $tableInstance) {
        checkPendingMigration($baseDb, $tableInstance, $pendingMigrations);
    }

    // Display pending migrations
    if (empty($pendingMigrations)) {
        echo "No pending migrations found.\n";
    } else {
        echo "Found " . count($pendingMigrations) . " pending migration(s):\n";
        displayTable($pendingMigrations,"Pending MIgrations");
        echo "\n Run `php manage.php migrate` to apply create/add migrations\n";
    }

    echo "\n";
} catch (PDOException $e) {
    echo "PDOException: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
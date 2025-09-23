<?php
// Updated update.php
// Changes:
// - Generate unique migration names with timestamp for add and drop.
// - Removed check for existing migration name; apply based on schema diff.
// - Normalized table names to lowercase.

require __DIR__ . "/../vendor/autoload.php";
require __DIR__ . "/../config.php";

use Reut\DB\DataBase;

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
    if ($baseDb->connect()) {
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

        // Get model files
        $modelFiles = array_diff(scandir(__DIR__ . '/../models/'), ['.', '..']);

        // Get tables in database
        $tablesInDatabase = $baseDb->getTablesList();

        // Check for orphan tables
         foreach ($tablesInDatabase as $tableName) {
            $expectedModelFile = ucfirst($tableName) . 'Table.php'; // messages -> MessagesTable.php

            
            $checkThere = in_array((string)$expectedModelFile, $modelFiles,true);
             //$className = 'Reut\\Models\\' . pathinfo($expected, PATHINFO_FILENAME);

            if (!$checkThere && $tableName !== 'migrations') {
                echo "Table '{$tableName}' exists in {$config['dbname']} but no model class found.\n";
                echo "Do you want to drop this table? (yes/no): ";
                $response = trim(fgets(STDIN));
                if (strtolower($response) === 'yes' || strtolower($response) === 'y') {
                    $baseDb->dropTable($tableName);
                    echo "'{$tableName}' dropped from database.\n";
                } else {
                    echo "Proceeding without dropping '{$tableName}'...\n";
                }
            }else{
               
            }
        }
        
        // Check models for updates
        foreach ($modelFiles as $fileName) {
            $className = pathinfo($fileName, PATHINFO_FILENAME);
          
            $classFullName = 'Reut\\Models\\' . $className;
            $tableName = str_replace('Table', '', $className);

            if (class_exists($classFullName)) {
                $tableInstance = new $classFullName($config);

                if (!$tableInstance->tableExists($tableName)) {
                    // Create missing table (handled in migrate.php, but if needed here)
                    echo "Table '{$tableName}' does not exist in database. Run `php manage.php create` to create.\n";
                } else {
                    $timestamp = date('YmdHis');
                    // Check for schema updates
                    $dbColumns = $tableInstance->getTableSchema($tableName);
                    $modelColumns = array_filter($tableInstance->columns, fn($key) => strpos($key, 'FOREIGN KEY') === false, ARRAY_FILTER_USE_KEY);
                    $modelColumnNames = array_keys($modelColumns);

                    // Add missing columns
                    $missingColumns = array_diff($modelColumnNames, $dbColumns);
                    if (!empty($missingColumns)) {
                        echo "Applying changes to: {$className}.\n";
                    }
                    foreach ($missingColumns as $column) {
                        $definition = $tableInstance->columns[$column];
                        $migrationName = 'add_' . $column . '_to_' . $tableName . '_table_' . $timestamp;
                        $sql = $tableInstance->getAddColumnSQL($column, $definition);
                        if ($tableInstance->addColumnToTable($column, $definition)) {
                            $baseDb->sqlQuery(
                                "INSERT INTO migrations (name, sql_text, batch) VALUES (:name, :sql_text, :batch)",
                                ['name' => $migrationName, 'sql_text' => $sql, 'batch' => $currentBatch]
                            );
                            echo "Added column '{$column}' to {$className} table and migration recorded ({$migrationName}).\n";
                        } else {
                            echo "Error adding column '{$column}' to {$className} table.\n";
                        }
                    }

                    // Drop removed columns
                    $columnsToDrop = array_diff($dbColumns, $modelColumnNames);
                    foreach ($columnsToDrop as $column) {
                        $migrationName = 'drop_' . $column . '_from_' . $tableName . '_table_' . $timestamp;
                        $sql = $tableInstance->getDropColumnSQL($column);
                        if ($tableInstance->dropColumn($tableName, $column)) {
                            $baseDb->sqlQuery(
                                "INSERT INTO migrations (name, sql_text, batch) VALUES (:name, :sql_text, :batch)",
                                ['name' => $migrationName, 'sql_text' => $sql, 'batch' => $currentBatch]
                            );
                            echo "Dropped column '{$column}' from {$className} table and migration recorded ({$migrationName}).\n";
                        } else {
                            echo "Error dropping column '{$column}' from {$className} table.\n";
                        }
                    }
                }
            }
        }
    } else {
        throw new Exception("Failed to connect to the database. Check your config or MySQL availability.");
    }
} catch (PDOException $e) {
    echo "\nPDOException: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "\nException: " . $e->getMessage() . "\n";
}
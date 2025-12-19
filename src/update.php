<?php
declare(strict_types=1);

// Updated update.php
// Changes:
// - Generate unique migration names with timestamp for add and drop.
// - Removed check for existing migration name; apply based on schema diff.
// - Normalized table names to lowercase.
use Reut\DB\DataBase;
use Reut\DB\Exceptions\DatabaseConnectionException;
use Reut\DB\Exceptions\DatabaseQueryException;
use Reut\DB\Exceptions\DatabaseMigrationException;
use Reut\Support\ProjectPath;

require ProjectPath::resolve('vendor', 'autoload.php');
require ProjectPath::resolve('config.php');

// Parse command line options
$options = parseSyncOptions($argv ?? []);
$dryRun = $options['dry-run'] ?? false;

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
        $baseDb->execute($migrationsTableSql);

        // Get current max batch and increment
        $batchQuery = $baseDb->sqlQuery("SELECT MAX(batch) as max_batch FROM migrations");
        $maxBatch = 0;
        if (is_array($batchQuery) && isset($batchQuery[0]['max_batch'])) {
            $maxBatch = (int) $batchQuery[0]['max_batch'];
        }
        $currentBatch = $maxBatch + 1;

        // Get model files
        $modelsDirectory = rtrim(ProjectPath::resolve('models'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $modelFiles = is_dir($modelsDirectory)
            ? array_filter(array_diff(scandir($modelsDirectory), ['.', '..']), fn($f) => str_ends_with($f, '.php'))
            : [];

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
                $response = readInputWithTimeout(30, 3); // 30 second timeout, 3 retries
                if ($response === false) {
                    echo "Input timeout or invalid. Skipping table '{$tableName}'.\n";
                    continue;
                }
                if (strtolower($response) === 'yes' || strtolower($response) === 'y') {
                    if (!$dryRun) {
                        $baseDb->dropTable($tableName);
                        echo "'{$tableName}' dropped from database.\n";
                    } else {
                        echo "[DRY-RUN] Would drop table '{$tableName}'.\n";
                    }
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

            if (class_exists($classFullName)) {
                $tableInstance = new $classFullName($config);
                $tableName = $tableInstance->tableName;

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
                        if ($dryRun) {
                            echo "[DRY-RUN] Would add column '{$column}' to {$className} table\n";
                            echo "[DRY-RUN] SQL: {$sql}\n";
                            echo "[DRY-RUN] Migration name: {$migrationName}\n";
                        } elseif ($tableInstance->addColumnToTable($column, $definition)) {
                            $baseDb->execute(
                                "INSERT IGNORE INTO migrations (name, sql_text, batch) VALUES (:name, :sql_text, :batch)",
                                ['name' => $migrationName, 'sql_text' => $sql, 'batch' => $currentBatch]
                            );
                            echo "Added column '{$column}' to {$className} table and migration recorded ({$migrationName}).\n";
                        } else {
                            echo "Error adding column '{$column}' to {$className} table.\n";
                        }
                    }

                    // Drop removed columns
                    $columnsToDrop = array_diff($dbColumns, $modelColumnNames);
                    
                    // Check for protected columns that will be dropped
                    $protected = $tableInstance->protectedColumns ?? [];
                    $protectedToDrop = array_intersect($columnsToDrop, $protected);
                    
                    if (!empty($protectedToDrop)) {
                        echo "\n⚠️  WARNING: About to drop protected columns from {$className} table:\n";
                        foreach ($protectedToDrop as $col) {
                            echo "   - {$col}\n";
                        }
                        echo "These columns are typically important (e.g., created_at, updated_at).\n";
                        echo "Do you want to continue? (yes/no): ";
                        $response = readInputWithTimeout(30, 3); // 30 second timeout, 3 retries
                        if ($response === false) {
                            echo "Input timeout or invalid. Skipping protected columns drop for {$className} table.\n";
                            $columnsToDrop = array_diff($columnsToDrop, $protectedToDrop);
                        } elseif (strtolower($response) !== 'yes' && strtolower($response) !== 'y') {
                            echo "Skipping protected columns drop for {$className} table.\n";
                            $columnsToDrop = array_diff($columnsToDrop, $protectedToDrop);
                        } else {
                            echo "Proceeding with dropping protected columns...\n";
                        }
                    }
                    
                    foreach ($columnsToDrop as $column) {
                        $migrationName = 'drop_' . $column . '_from_' . $tableName . '_table_' . $timestamp;
                        $sql = $tableInstance->getDropColumnSQL($column);
                        if ($dryRun) {
                            echo "[DRY-RUN] Would drop column '{$column}' from {$className} table\n";
                            echo "[DRY-RUN] SQL: {$sql}\n";
                            echo "[DRY-RUN] Migration name: {$migrationName}\n";
                        } elseif ($tableInstance->dropColumn($tableName, $column)) {
                            $baseDb->execute(
                                "INSERT IGNORE INTO migrations (name, sql_text, batch) VALUES (:name, :sql_text, :batch)",
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
        
        if ($dryRun) {
            echo "\n=== DRY-RUN MODE ===\n";
            echo "[DRY-RUN] No changes were made. Remove --dry-run to execute sync.\n";
        }
    } else {
        throw new DatabaseConnectionException(
            "Failed to connect to the database. Check your config or MySQL availability.",
            0,
            null,
            $config
        );
    }
} catch (DatabaseConnectionException $e) {
    echo "\nDatabase Connection Error: " . $e->getFormattedMessage() . "\n";
    echo "Please check your database configuration in config.php or .env file.\n";
    exit(1);
} catch (DatabaseQueryException $e) {
    echo "\nDatabase Query Error: " . $e->getFormattedMessage() . "\n";
    exit(1);
} catch (DatabaseMigrationException $e) {
    echo "\nMigration Error: " . $e->getFormattedMessage() . "\n";
    exit(1);
} catch (\PDOException $e) {
    // Fallback for unhandled PDO exceptions
    $errorInfo = $e->errorInfo ?? ['', $e->getCode(), $e->getMessage()];
    $queryException = new DatabaseQueryException(
        "Database Error: " . $e->getMessage(),
        (int)$e->getCode(),
        $e,
        null,
        [],
        $errorInfo
    );
    echo "\n" . $queryException->getFormattedMessage() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "\nError: " . $e->getMessage() . "\n";
    if ($e->getCode() !== 0) {
        echo "Error Code: " . $e->getCode() . "\n";
    }
    exit(1);
}

/**
 * Parse command line options for sync command
 */
function parseSyncOptions(array $argv): array
{
    $options = [
        'dry-run' => false,
    ];

    foreach ($argv as $arg) {
        if ($arg === '--dry-run') {
            $options['dry-run'] = true;
        }
    }

    return $options;
}

/**
 * Read input from STDIN with timeout and retry limit
 * 
 * @param int $timeoutSeconds Timeout in seconds
 * @param int $maxRetries Maximum number of retries
 * @return string|false Input string or false on timeout/invalid
 */
function readInputWithTimeout(int $timeoutSeconds = 30, int $maxRetries = 3): string|false
{
    // Check if running in non-interactive mode (CI/CD)
    if (!stream_isatty(STDIN)) {
        // Non-interactive mode - return default 'no' for safety
        return 'no';
    }
    
    $retries = 0;
    while ($retries < $maxRetries) {
        // Set stream blocking mode
        stream_set_blocking(STDIN, false);
        
        $startTime = time();
        $input = '';
        
        while ((time() - $startTime) < $timeoutSeconds) {
            $char = fgetc(STDIN);
            if ($char === false) {
                usleep(100000); // Wait 100ms
                continue;
            }
            
            if ($char === "\n" || $char === "\r") {
                break;
            }
            
            $input .= $char;
        }
        
        // Restore blocking mode
        stream_set_blocking(STDIN, true);
        
        $input = trim($input);
        
        // Validate input format
        if (!empty($input) && preg_match('/^(yes|no|y|n)$/i', $input)) {
            return $input;
        }
        
        if (empty($input) && $retries < $maxRetries - 1) {
            echo "Invalid input. Please enter 'yes' or 'no': ";
            $retries++;
            continue;
        }
        
        if (empty($input)) {
            return false; // Timeout
        }
        
        $retries++;
        if ($retries < $maxRetries) {
            echo "Invalid input. Please enter 'yes' or 'no': ";
        }
    }
    
    return false;
}
<?php
// Updated migrate.php
// Changes:
// - Generate unique migration names with timestamp for table creation and column changes.
// - Check table and column schema to avoid re-running migrations for existing fields.
// - Apply create_table, add_column, and drop_column migrations as needed.
// - Properly handle duplicate migration records with explicit error checking.
// - Improved error handling and reporting throughout migration process.
// - Better migration name matching with regex patterns that handle timestamps correctly.

require __DIR__ . "/../vendor/autoload.php";
require __DIR__ . "/../config.php";

use Reut\DB\DataBase;
use Reut\DB\Exceptions\ConnectionError;
use Reut\DB\Exceptions\DatabaseConnectionException;
use Reut\DB\Exceptions\DatabaseMigrationException;
use Reut\DB\Exceptions\DatabaseQueryException;

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
    $baseDb->execute($migrationsTableSql);

    // Get current max batch and increment
    try {
        $batchQuery = $baseDb->sqlQuery("SELECT MAX(batch) as max_batch FROM migrations");
        $maxBatch = 0;
        if (is_array($batchQuery) && isset($batchQuery[0]['max_batch'])) {
            $maxBatch = (int) $batchQuery[0]['max_batch'];
        }
        $currentBatch = $maxBatch + 1;
        echo "Using migration batch: {$currentBatch}\n";
    } catch (\Exception $e) {
        echo "Warning: Could not determine batch number, using batch 1. Error: " . $e->getMessage() . "\n";
        $currentBatch = 1;
    }

    echo "Getting tables ...\n";

    // Get model files
    $modelFiles = array_filter(array_diff(scandir(__DIR__ . '/../models/'), ['.', '..']), fn($f) => str_ends_with($f, '.php'));

    $noRelations = [];
    $withRelations = [];

    foreach ($modelFiles as $fileName) {
        // Skip non-PHP files
        if (pathinfo($fileName, PATHINFO_EXTENSION) !== 'php') {
            continue;
        }
        
        echo "Loading class: $fileName\n";
        $className = 'Reut\\Models\\' . pathinfo($fileName, PATHINFO_FILENAME);

        try {
            if (class_exists($className)) {
                $tableInstance = new $className($config);
                if (method_exists($tableInstance, 'hasRelationships') && $tableInstance->hasRelationships()) {
                    $withRelations[] = $tableInstance;
                    echo "  -> Table '{$tableInstance->tableName}' has relationships.\n";
                } else {
                    $noRelations[] = $tableInstance;
                }
            } else {
                echo "Warning: Class $className does not exist or could not be loaded.\n";
            }
        } catch (\Exception $e) {
            echo "Error: Failed to instantiate {$className}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "Found " . count($noRelations) . " tables without relations and " . count($withRelations) . " tables with relations.\n";

    usort($withRelations, fn($a, $b) => $a->getRelationshipCount() <=> $b->getRelationshipCount());

    // Helper function to record a migration
    function recordMigration($baseDb, string $migrationName, string $sql, int $batch): bool
    {
        try {
            // Check if migration already exists
            $existing = $baseDb->sqlQuery(
                "SELECT id FROM migrations WHERE name = :name LIMIT 1",
                ['name' => $migrationName]
            );
            
            if (!empty($existing) && is_array($existing)) {
                return false; // Migration already exists
            }

            // Use regular INSERT (not INSERT IGNORE) to properly detect duplicates
            $stmt = $baseDb->pdo->prepare(
                "INSERT INTO migrations (name, sql_text, batch) VALUES (:name, :sql_text, :batch)"
            );
            $result = $stmt->execute([
                'name' => $migrationName,
                'sql_text' => $sql,
                'batch' => $batch
            ]);

            // Verify the insert was successful by checking affected rows
            if ($result && $stmt->rowCount() > 0) {
                return true;
            }
            
            return false;
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

    // Function to apply migrations for a table
    function applyMigration($baseDb, $tableInstance, $currentBatch): bool
    {
        $tableName = $tableInstance->tableName;
        $timestamp = date('YmdHis');

        // Query existing migrations for this table
        $existingMigrations = $baseDb->sqlQuery("SELECT name FROM migrations WHERE name LIKE :pattern", [
            'pattern' => '%' . $tableName . '%'
        ]);

        // Helper function to check if a migration exists
        $hasMigration = function ($action, $column = null) use ($existingMigrations, $tableName) {
            $escapedTable = preg_quote($tableName, '/');
            foreach ($existingMigrations as $migration) {
                $migrationName = $migration['name'] ?? '';
                if ($column) {
                    // Match column-specific migrations (add/drop) - handle timestamps in pattern
                    $escapedColumn = preg_quote($column, '/');
                    // Pattern: action_column_(to|from)_table_table_YYYYMMDDHHMMSS
                    if (preg_match("/^{$action}_{$escapedColumn}_(to|from)_{$escapedTable}_table_\d{14}$/", $migrationName)) {
                        return true;
                    }
                } else {
                    // Match table creation - handle timestamps in pattern
                    // Pattern: create_table_table_YYYYMMDDHHMMSS
                    if (preg_match("/^create_{$escapedTable}_table_\d{14}$/", $migrationName)) {
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
                    throw new DatabaseMigrationException(
                        "Failed to generate SQL for table '{$tableName}'",
                        0,
                        null,
                        null,
                        $tableName,
                        null
                    );
                }
                $migrationName = 'create_' . $tableName . '_table_' . $timestamp;
                if ($tableInstance->createTable()) {
                    try {
                        $insertResult = recordMigration($baseDb, $migrationName, $sql, $currentBatch);
                        if ($insertResult) {
                            echo get_class($tableInstance) . " table created and migration recorded ({$migrationName}).\n";
                            $migrationsApplied = true;
                        } else {
                            echo "Warning: Table created but migration '{$migrationName}' already exists or failed to record.\n";
                        }
                    } catch (DatabaseQueryException $e) {
                        throw new DatabaseMigrationException(
                            "Failed to record migration for table creation: " . $e->getMessage(),
                            $e->getCode(),
                            $e,
                            $migrationName,
                            $tableName,
                            $sql
                        );
                    } catch (\Exception $e) {
                        throw new DatabaseMigrationException(
                            "Failed to record migration: " . $e->getMessage(),
                            0,
                            $e,
                            $migrationName,
                            $tableName,
                            $sql
                        );
                    }
                } else {
                    throw new DatabaseMigrationException(
                        "Failed to create table: " . get_class($tableInstance),
                        0,
                        null,
                        $migrationName,
                        $tableName,
                        $sql
                    );
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
                    try {
                        $baseDb->execute($sql);
                        $insertResult = recordMigration($baseDb, $migrationName, $sql, $currentBatch);
                        if ($insertResult) {
                            echo "Added column {$column} to {$tableName} and recorded migration ({$migrationName}).\n";
                            $migrationsApplied = true;
                        } else {
                            echo "Warning: Column {$column} added but migration '{$migrationName}' already exists or failed to record.\n";
                        }
                    } catch (DatabaseQueryException $e) {
                        throw new DatabaseMigrationException(
                            "Failed to add column '{$column}' to table '{$tableName}': " . $e->getMessage(),
                            $e->getCode(),
                            $e,
                            $migrationName,
                            $tableName,
                            $sql
                        );
                    } catch (\Exception $e) {
                        throw new DatabaseMigrationException(
                            "Failed to add column or record migration: " . $e->getMessage(),
                            0,
                            $e,
                            $migrationName,
                            $tableName,
                            $sql
                        );
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
                    try {
                        $baseDb->execute($sql);
                        $insertResult = recordMigration($baseDb, $migrationName, $sql, $currentBatch);
                        if ($insertResult) {
                            echo "Dropped column {$column} from {$tableName} and recorded migration ({$migrationName}).\n";
                            $migrationsApplied = true;
                        } else {
                            echo "Warning: Column {$column} dropped but migration '{$migrationName}' already exists or failed to record.\n";
                        }
                    } catch (\Exception $e) {
                        echo "Error: Failed to drop column {$column} or record migration: " . $e->getMessage() . "\n";
                        throw $e;
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
    
    // Check for auth setup file and create test user if needed
    if (!$dryRun) {
        $authSetupFile = ProjectPath::resolve('.auth-setup.json');
        if (file_exists($authSetupFile)) {
            try {
                // Try to load createAuthUser function from Reut CLI source or project
                $createAuthUserPath = __DIR__ . '/createAuthUser.php';
                if (!file_exists($createAuthUserPath)) {
                    // Try relative to project root (if migrate.php was copied to config/)
                    $createAuthUserPath = ProjectPath::resolve('..', 'src', 'createAuthUser.php');
                }
                if (file_exists($createAuthUserPath)) {
                    require_once $createAuthUserPath;
                } else {
                    // Fallback: define function inline if file not found
                    if (!function_exists('createAuthUser')) {
                        function createAuthUser(string $identifier, string $password, array $config, array $authConfig): array {
                            try {
                                $tableName = $authConfig['table'];
                                $identifierField = $authConfig['fields']['identifier'];
                                $passwordField = $authConfig['fields']['password'];
                                
                                $modelClass = "Reut\\Models\\{$tableName}Table";
                                if (!class_exists($modelClass)) {
                                    $modelsDir = rtrim(ProjectPath::resolve('models'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                                    $modelFile = $modelsDir . $tableName . 'Table.php';
                                    if (file_exists($modelFile)) {
                                        require_once $modelFile;
                                    }
                                }
                                
                                if (!class_exists($modelClass)) {
                                    return ['success' => false, 'message' => "Auth model class {$modelClass} not found."];
                                }
                                
                                $authModel = new $modelClass($config);
                                $existing = $authModel->findOne([$identifierField => $identifier]);
                                if ($existing && $existing->results) {
                                    return ['success' => false, 'message' => "User with {$identifierField} '{$identifier}' already exists."];
                                }
                                
                                if (strlen($password) < 6) {
                                    return ['success' => false, 'message' => 'Password must be at least 6 characters long.'];
                                }
                                
                                $userData = [
                                    $identifierField => $identifier,
                                    $passwordField => password_hash($password, PASSWORD_DEFAULT)
                                ];
                                
                                $result = $authModel->addOne($userData);
                                
                                if ($result === true) {
                                    return ['success' => true, 'message' => "Test user '{$identifier}' created successfully."];
                                } else {
                                    $errorMsg = is_string($result) ? $result : 'Unknown error occurred';
                                    return ['success' => false, 'message' => "Failed to create user: {$errorMsg}"];
                                }
                            } catch (\Exception $e) {
                                return ['success' => false, 'message' => "Error creating user: " . $e->getMessage()];
                            }
                        }
                    }
                }
                
                $authSetupData = json_decode(file_get_contents($authSetupFile), true);
                
                if (is_array($authSetupData) && isset($authSetupData['identifier']) && isset($authSetupData['password'])) {
                    // Load auth config
                    $authConfigPath = ProjectPath::resolve('auth.php');
                    $authConfig = file_exists($authConfigPath) ? require $authConfigPath : [];
                    
                    echo "\nCreating test user for authentication...\n";
                    $result = createAuthUser(
                        $authSetupData['identifier'],
                        $authSetupData['password'],
                        $config,
                        $authConfig
                    );
                    
                    if ($result['success']) {
                        echo $result['message'] . "\n";
                        // Delete the setup file after successful creation
                        unlink($authSetupFile);
                        echo "\nYou can now login at POST /auth/login with:\n";
                        $identifierField = $authConfig['fields']['identifier'] ?? 'email';
                        echo "  - {$identifierField}: {$authSetupData['identifier']}\n";
                    } else {
                        echo "Warning: " . $result['message'] . "\n";
                        echo "You can create a user later via POST /auth/register\n";
                    }
                }
            } catch (\Exception $e) {
                echo "Warning: Could not create test user: " . $e->getMessage() . "\n";
                echo "You can create a user later via POST /auth/register\n";
            }
        }
    }
} catch (ConnectionError $e) {
    echo "Database Connection Error: " . $e->getMessage() . "\n";
    echo "Please check your database configuration in config.php or .env file.\n";
    exit(1);
} catch (\PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
    if ($e->getCode() == '42S02') {
        echo "Hint: The migrations table may not exist. This should be created automatically.\n";
    }
    exit(1);
} catch (Exception $e) {
    echo "Migration Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
?>
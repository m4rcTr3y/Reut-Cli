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
use Reut\DB\Exceptions\DatabaseConnectionException;
use Reut\DB\Exceptions\DatabaseQueryException;
use Reut\DB\Exceptions\DatabaseMigrationException;
use Reut\Support\ProjectPath;

require ProjectPath::resolve('vendor', 'autoload.php');
require ProjectPath::resolve('config.php');

// Parse command line options
$options = parseMigrateOptions($argv ?? []);
$dryRun = $options['dry-run'] ?? false;

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
    // Ensure database is selected
    if (isset($config['dbname'])) {
        $baseDb->execute("USE `{$config['dbname']}`");
    }

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
        ? array_filter(array_diff(scandir($modelsDirectory), ['.', '..']), fn($f) => str_ends_with($f, '.php'))
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

    // Validate relationships before migration
    echo "Validating relationships...\n";
    $allTableInstances = array_merge($noRelations, $withRelations);
    $validationErrors = [];
    
    foreach ($allTableInstances as $tableInstance) {
        if ($tableInstance->hasRelationships()) {
            try {
                $errors = $tableInstance->validateForeignKeyRelationships($allTableInstances);
                if (!empty($errors)) {
                    $validationErrors = array_merge($validationErrors, $errors);
                }
            } catch (\Exception $e) {
                $validationErrors[] = get_class($tableInstance) . ": " . $e->getMessage();
            }
        }
    }
    
    // Check for circular dependencies
    $circularDeps = detectCircularDependencies($allTableInstances);
    if (!empty($circularDeps)) {
        $validationErrors[] = "Circular dependencies detected: " . implode(", ", $circularDeps);
    }
    
    if (!empty($validationErrors)) {
        echo "\n❌ Relationship validation failed:\n";
        foreach ($validationErrors as $error) {
            echo "  - {$error}\n";
        }
        echo "\nPlease fix these errors before running migrations.\n";
        exit(1);
    }
    
    echo "✓ Relationship validation passed.\n\n";

    // Function to apply migrations for a table
    function applyMigration($baseDb, $tableInstance, $currentBatch, $dryRun = false): bool
    {
        $tableName = $tableInstance->tableName;
        $timestamp = date('YmdHis');

        // Query existing migrations for this table
        $existingMigrations = $baseDb->sqlQuery(
            "SELECT name FROM migrations WHERE name LIKE :pattern",
            ['pattern' => "%{$tableName}%"]
        );

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
                if ($dryRun) {
                    echo "[DRY-RUN] Would create table: {$tableName}\n";
                    echo "[DRY-RUN] SQL: {$sql}\n";
                    echo "[DRY-RUN] Migration name: {$migrationName}\n";
                    $migrationsApplied = true;
                } else {
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
                    if ($dryRun) {
                        echo "[DRY-RUN] Would add column: {$column} to {$tableName}\n";
                        echo "[DRY-RUN] SQL: {$sql}\n";
                        echo "[DRY-RUN] Migration name: {$migrationName}\n";
                        $migrationsApplied = true;
                    } else {
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
                    if ($dryRun) {
                        echo "[DRY-RUN] Would drop column: {$column} from {$tableName}\n";
                        echo "[DRY-RUN] SQL: {$sql}\n";
                        echo "[DRY-RUN] Migration name: {$migrationName}\n";
                        $migrationsApplied = true;
                    } else {
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
        if (applyMigration($baseDb, $tableInstance, $currentBatch, $dryRun)) {
            $migrationsApplied = true;
        }
    }

    // Apply migrations for tables with relations
    foreach ($withRelations as $tableInstance) {
        if (applyMigration($baseDb, $tableInstance, $currentBatch, $dryRun)) {
            $migrationsApplied = true;
        }
    }

    if ($dryRun) {
        echo "\n[DRY-RUN] No changes were made. Remove --dry-run to execute migrations.\n";
    } elseif ($migrationsApplied) {
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
} catch (DatabaseConnectionException $e) {
    echo "Database Connection Error: " . $e->getFormattedMessage() . "\n";
    echo "Please check your database configuration in config.php or .env file.\n";
    exit(1);
} catch (DatabaseQueryException $e) {
    echo "Database Query Error: " . $e->getFormattedMessage() . "\n";
    exit(1);
} catch (DatabaseMigrationException $e) {
    echo "Migration Error: " . $e->getFormattedMessage() . "\n";
    exit(1);
} catch (ConnectionError $e) {
    // Legacy exception handling
    echo "Database Connection Error: " . $e->getMessage() . "\n";
    if (method_exists($e, 'getFormattedMessage')) {
        echo $e->getFormattedMessage() . "\n";
    } else {
        echo "Please check your database configuration in config.php or .env file.\n";
    }
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
    echo $queryException->getFormattedMessage() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    if ($e->getCode() !== 0) {
        echo "Error Code: " . $e->getCode() . "\n";
    }
    exit(1);
}

/**
 * Detect circular dependencies in foreign keys
 * 
 * @param array $tableInstances Array of DataBase instances
 * @return array Array of circular dependency descriptions
 */
function detectCircularDependencies(array $tableInstances): array
{
    $circularDeps = [];
    $graph = [];
    
    // Build dependency graph
    foreach ($tableInstances as $instance) {
        $tableName = $instance->tableName;
        $graph[$tableName] = [];
        
        foreach ($instance->getForeignKeys() as $fk) {
            $graph[$tableName][] = $fk['referenced_table'];
        }
    }
    
    // Detect cycles using DFS
    foreach ($graph as $startTable => $dependencies) {
        $visited = [];
        $recStack = [];
        $cycle = [];
        
        if (hasCycle($startTable, $graph, $visited, $recStack, $cycle)) {
            if (!empty($cycle)) {
                $circularDeps[] = implode(" -> ", $cycle) . " -> " . $cycle[0];
            }
        }
    }
    
    return array_unique($circularDeps);
}

/**
 * Check for cycles in dependency graph using DFS
 */
function hasCycle(string $node, array $graph, array &$visited, array &$recStack, array &$cycle): bool
{
    if (!isset($visited[$node])) {
        $visited[$node] = false;
    }
    if (!isset($recStack[$node])) {
        $recStack[$node] = false;
    }
    
    $visited[$node] = true;
    $recStack[$node] = true;
    $cycle[] = $node;
    
    if (isset($graph[$node])) {
        foreach ($graph[$node] as $neighbor) {
            if (!isset($visited[$neighbor]) || !$visited[$neighbor]) {
                if (hasCycle($neighbor, $graph, $visited, $recStack, $cycle)) {
                    return true;
                }
            } elseif (isset($recStack[$neighbor]) && $recStack[$neighbor]) {
                // Found a cycle - add the neighbor to complete the cycle
                $cycle[] = $neighbor;
                return true;
            }
        }
    }
    
    $recStack[$node] = false;
    array_pop($cycle);
    return false;
}

/**
 * Parse command line options for migrate command
 */
function parseMigrateOptions(array $argv): array
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
?>
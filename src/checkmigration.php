<?php
declare(strict_types=1);

use Reut\DB\DataBase;
use Reut\DB\Exceptions\DatabaseConnectionException;
use Reut\DB\Exceptions\DatabaseQueryException;
use Reut\Support\ProjectPath;

require ProjectPath::resolve('vendor', 'autoload.php');
require ProjectPath::resolve('config.php');
require __DIR__ . "/Utils/ascii_table.php";

// Parse command line options
$options = parseStatusOptions($argv ?? []);
$jsonMode = $options['json'] ?? false;

// Autoload models dynamically
spl_autoload_register(function ($class) use ($jsonMode) {
    $prefix = 'Reut\\Models\\';
    $baseDir = rtrim(ProjectPath::resolve('models'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    if (strpos($class, $prefix) === 0) {
        $relativeClass = substr($class, strlen($prefix));
        $file = realpath($baseDir . str_replace('\\', '/', $relativeClass) . '.php');
        if (file_exists($file)) {
            // Only echo loading message if not in JSON mode
            if (!$jsonMode) {
                echo "Loading class: $file\n";
            }
            require_once $file;
        }
    }
});

$baseDb = new DataBase($config);
try {
    try {
        $baseDb->connect();
    } catch (DatabaseConnectionException $e) {
        // Re-throw to be caught by outer catch block
        throw $e;
    } catch (\Exception $e) {
        // If connect() throws a different exception, wrap it
        throw new DatabaseConnectionException(
            "Failed to connect to the database: " . $e->getMessage(),
            $e->getCode(),
            $e,
            $config
        );
    }

    // Check if migrations table exists
    if (!$baseDb->tableExists('migrations')) {
        if ($jsonMode) {
            echo json_encode([
                'applied' => [],
                'pending' => [],
                'summary' => [
                    'total_applied' => 0,
                    'total_pending' => 0,
                    'total_batches' => 0,
                    'last_migration' => null,
                    'last_batch' => null,
                    'batches' => []
                ]
            ], JSON_PRETTY_PRINT) . "\n";
            exit(0);
        }
        echo "No migrations table found. Run migrate.php to create it.\n";
        exit(0);
    }

    // List applied migrations
    $migrationsQuery = "SELECT id, name, sql_text, batch, applied_at FROM migrations";
    
    // Filter by table if specified
    if (isset($options['table'])) {
        $tableName = $options['table'];
        $migrationsQuery .= " WHERE name LIKE :pattern ORDER BY batch, id";
        $migrations = $baseDb->sqlQuery($migrationsQuery, ['pattern' => "%{$tableName}%"]);
    } else {
        $migrationsQuery .= " ORDER BY batch, id";
        $migrations = $baseDb->sqlQuery($migrationsQuery);
    }
    
    if (empty($migrations)) {
        if (!$jsonMode) {
            echo "No migrations have been applied.\n";
        }
    } else {
        if (!$jsonMode) {
            if (isset($options['table'])) {
                displayTable($migrations, "Applied Migrations for table: {$options['table']}");
            } else {
                displayTable($migrations, "Applied Migrations");
            }
        }
    }

    // Check for pending migrations
    if (!$jsonMode) {
        echo "\n=== Re-checking Models ===\n";
    }
    $modelsDirectory = rtrim(ProjectPath::resolve('models'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $modelFiles = is_dir($modelsDirectory)
        ? array_filter(array_diff(scandir($modelsDirectory), ['.', '..']), fn($f) => str_ends_with($f, '.php'))
        : [];
    $noRelations = [];
    $withRelations = [];

    // Load model classes
    foreach ($modelFiles as $fileName) {
        if (!$jsonMode) {
            echo "Checking class: $fileName\n";
        }
        $className = 'Reut\\Models\\' . pathinfo($fileName, PATHINFO_FILENAME);

        if (class_exists($className)) {
            $tableInstance = new $className($config);
            if (method_exists($tableInstance, 'hasRelationships') && $tableInstance->hasRelationships()) {
                $withRelations[] = $tableInstance;
            } else {
                $noRelations[] = $tableInstance;
            }
        } else {
            if (!$jsonMode) {
                echo "Class $className does not exist.\n";
            }
        }
    }

    usort($withRelations, fn($a, $b) => $a->getRelationshipCount() <=> $b->getRelationshipCount());

    $pendingMigrations = [];

    // Function to check pending migrations for a table
    function checkPendingMigration($baseDb, $tableInstance, &$pendingMigrations): void
    {
        $tableName = $tableInstance->tableName;
        $timestamp = date('YmdHis');

        // Query the migrations table to check for existing migrations
        $existingMigrations = $baseDb->sqlQuery(
            "SELECT name FROM migrations WHERE name LIKE :pattern",
            ['pattern' => "%{$tableName}%"]
        );

        // Helper function to check if a migration for a specific action exists
        $hasMigration = function ($action) use ($existingMigrations, $tableName) {
            $escapedTable = preg_quote($tableName, '/');
            foreach ($existingMigrations as $migration) {
                // Match migration names without timestamp
                if (preg_match("/{$action}_{$escapedTable}_table/", $migration['name'])) {
                    return true;
                }
            }
            return false;
        };

        // Check if table creation is pending
        if (!$tableInstance->tableExists($tableName)) {
            $sql = $tableInstance->genSQL();
            if ($sql !== false && !$hasMigration('create')) {
                $migrationName = 'create_' . $tableName . '_table_' . $timestamp;
                $pendingMigrations[] = [
                    'name' => $migrationName,
                    'sql' => $sql,
                    'type' => 'create_table',
                    'class' => get_class($tableInstance)
                ];
            }
        }

        if ($tableInstance->tableExists($tableName)) {
            // Check for missing columns (to add)
            $dbColumns = $tableInstance->getTableSchema($tableName);
            $modelColumns = array_filter($tableInstance->columns, fn($key) => strpos($key, 'FOREIGN KEY') === false, ARRAY_FILTER_USE_KEY);
            $modelColumnNames = array_keys($modelColumns);
            $missingColumns = array_diff($modelColumnNames, $dbColumns);

            foreach ($missingColumns as $column) {
                // Check if an add_column migration already exists for this column
                $hasAddMigration = false;
                foreach ($existingMigrations as $migration) {
                    if (preg_match("/add_{$column}_to_{$tableName}_table/", $migration['name'])) {
                        $hasAddMigration = true;
                        break;
                    }
                }
                if (!$hasAddMigration) {
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
            }

            // Check for columns to drop (in DB but not in model)
            $columnsToDrop = array_diff($dbColumns, $modelColumnNames);
            foreach ($columnsToDrop as $column) {
                // Check if a drop_column migration already exists for this column
                $hasDropMigration = false;
                foreach ($existingMigrations as $migration) {
                    if (preg_match("/drop_{$column}_from_{$tableName}_table/", $migration['name'])) {
                        $hasDropMigration = true;
                        break;
                    }
                }
                if (!$hasDropMigration) {
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
    }

    // Check tables without relations
    foreach ($noRelations as $tableInstance) {
        checkPendingMigration($baseDb, $tableInstance, $pendingMigrations);
    }

    // Check tables with relations
    foreach ($withRelations as $tableInstance) {
        checkPendingMigration($baseDb, $tableInstance, $pendingMigrations);
    }

    // Update JSON output with pending migrations
    if ($jsonMode) {
        $batches = !empty($migrations) ? array_unique(array_column($migrations, 'batch')) : [];
        $status = [
            'applied' => $migrations,
            'pending' => $pendingMigrations,
            'summary' => [
                'total_applied' => count($migrations),
                'total_pending' => count($pendingMigrations),
                'total_batches' => count($batches),
                'last_migration' => !empty($migrations) ? end($migrations)['applied_at'] : null,
                'last_batch' => !empty($batches) ? max($batches) : null,
                'batches' => array_values($batches)
            ]
        ];
        
        echo json_encode($status, JSON_PRETTY_PRINT) . "\n";
        exit(0);
    }
    
    // Summary mode
    if ($options['summary'] ?? false) {
        $batches = !empty($migrations) ? array_unique(array_column($migrations, 'batch')) : [];
        $lastMigration = !empty($migrations) ? end($migrations) : null;
        
        echo "\n=== Migration Summary ===\n";
        echo "Total Applied Migrations: " . count($migrations) . "\n";
        echo "Total Pending Migrations: " . count($pendingMigrations) . "\n";
        echo "Total Batches: " . count($batches) . "\n";
        if ($lastMigration) {
            echo "Last Migration: {$lastMigration['name']}\n";
            echo "Last Applied: {$lastMigration['applied_at']}\n";
        }
        if (!empty($batches)) {
            echo "Latest Batch: " . max($batches) . "\n";
        }
        echo "\n";
        exit(0);
    }
    
    // Display pending migrations
    if (empty($pendingMigrations)) {
        echo "No pending migrations found.\n";
    } else {
        echo "Found " . count($pendingMigrations) . " pending migration(s):\n";
        displayTable($pendingMigrations, "Pending Migrations");
        echo "\n Run `php manage.php migrate` to apply create/add migrations\n";
    }

    echo "\n";
} catch (DatabaseConnectionException $e) {
    echo "Database Connection Error: " . $e->getFormattedMessage() . "\n";
    echo "Please check your database configuration in config.php or .env file.\n";
    exit(1);
} catch (DatabaseQueryException $e) {
    echo "Database Query Error: " . $e->getFormattedMessage() . "\n";
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
 * Parse command line options for status command
 */
function parseStatusOptions(array $argv): array
{
    $options = [
        'json' => false,
        'summary' => false,
        'table' => null,
    ];

    foreach ($argv as $arg) {
        if ($arg === '--json') {
            $options['json'] = true;
        } elseif ($arg === '--summary') {
            $options['summary'] = true;
        } elseif (strpos($arg, '--table=') === 0) {
            $options['table'] = substr($arg, 8);
        }
    }

    return $options;
}
?>
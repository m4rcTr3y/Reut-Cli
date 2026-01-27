<?php
declare(strict_types=1);

namespace Reut\CLI\Commands;

use Reut\DB\DataBase;
use Reut\DB\Exceptions\DatabaseConnectionException;
use Reut\DB\Exceptions\DatabaseQueryException;
use Reut\Support\ProjectPath;

/**
 * Migrate command - Apply migrations from model definitions
 */
class MigrateCommand extends Command
{
    public function getName(): string
    {
        return 'migrate';
    }

    public function getDescription(): string
    {
        return 'Apply migrations from model definitions (ensures tables exist)';
    }

    public function getUsage(): string
    {
        return 'migrate [--dry-run]';
    }

    public function getOptions(): array
    {
        return [
            '--dry-run' => 'Preview migrations without executing',
        ];
    }

    public function getExamples(): array
    {
        return [
            'Reut migrate',
            'Reut migrate --dry-run',
            'Reut create', // alias
        ];
    }

    public function execute(array $args = []): int
    {
        $dryRun = $this->hasOption('dry-run');
        
        if ($dryRun) {
            $this->section('🔍 Preview Mode - No changes will be made');
            $this->writeln();
        } else {
            $this->section('🚀 Applying Migrations');
            $this->writeln();
        }

        // Load config
        require ProjectPath::resolve('vendor', 'autoload.php');
        require ProjectPath::resolve('config.php');

        // Create database connection
        $baseDb = new DataBase($config);
        
        try {
            // Create database if it doesn't exist
            if ($baseDb->createDatabase($config['dbname'])) {
                $this->success("Database '{$config['dbname']}' created");
            }

            $baseDb->connect();
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

            // Get current batch
            $batchQuery = $baseDb->sqlQuery("SELECT MAX(batch) as max_batch FROM migrations");
            $maxBatch = 0;
            if (is_array($batchQuery) && isset($batchQuery[0]['max_batch'])) {
                $maxBatch = (int) $batchQuery[0]['max_batch'];
            }
            $currentBatch = $maxBatch + 1;

            // Discover models
            $this->info('🔍 Discovering models...');
            $modelsDirectory = rtrim(ProjectPath::resolve('models'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $modelFiles = is_dir($modelsDirectory)
                ? array_filter(array_diff(scandir($modelsDirectory), ['.', '..']), fn($f) => str_ends_with($f, '.php'))
                : [];

            if (empty($modelFiles)) {
                $this->warning('No models found in models/ directory');
                return 0;
            }

            // Autoload models
            spl_autoload_register(function ($class) use ($modelsDirectory) {
                $prefix = 'Reut\\Models\\';
                if (strpos($class, $prefix) === 0) {
                    $relativeClass = substr($class, strlen($prefix));
                    $file = realpath($modelsDirectory . str_replace('\\', '/', $relativeClass) . '.php');
                    if (file_exists($file)) {
                        require_once $file;
                    }
                }
            });

            // Load model instances
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

            usort($withRelations, fn($a, $b) => $a->getRelationshipCount() <=> $b->getRelationshipCount());
            $allTableInstances = array_merge($noRelations, $withRelations);

            $this->success('Found ' . count($allTableInstances) . ' model(s)');
            $this->writeln();

            // Validate relationships
            $this->info('🔗 Validating relationships...');
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

            if (!empty($validationErrors)) {
                $this->error('Relationship validation failed:');
                foreach ($validationErrors as $error) {
                    $this->writeln("  - {$error}");
                }
                return 1;
            }

            $this->success('Relationship validation passed');
            $this->writeln();

            // Apply migrations
            $this->section('📊 Migration Status');
            
            $statusTable = new \Reut\CLI\Output\Table($this->formatter);
            $statusTable->setHeaders(['Model', 'Status', 'Action']);
            
            $migrationsToApply = [];
            $totalMigrations = 0;

            foreach ($allTableInstances as $tableInstance) {
                $tableName = $tableInstance->tableName;
                $status = '✓ Synced';
                $action = 'No changes';
                
                // Check what migrations are needed
                $needsMigration = $this->checkMigrationNeeded($baseDb, $tableInstance);
                
                if ($needsMigration['needs_create']) {
                    $status = '⚠ Pending';
                    $action = 'Create table';
                    $migrationsToApply[] = ['type' => 'create', 'instance' => $tableInstance];
                    $totalMigrations++;
                } elseif ($needsMigration['needs_columns']) {
                    $status = '⚠ Pending';
                    $action = count($needsMigration['needs_columns']) . ' column(s)';
                    $migrationsToApply[] = ['type' => 'columns', 'instance' => $tableInstance, 'columns' => $needsMigration['needs_columns']];
                    $totalMigrations += count($needsMigration['needs_columns']);
                }
                
                $statusTable->addRow([$tableName, $status, $action]);
            }

            $statusTable->display();
            $this->writeln();

            if (empty($migrationsToApply)) {
                $this->success('All migrations are up to date!');
                return 0;
            }

            if ($dryRun) {
                $this->info("Would apply {$totalMigrations} migration(s)");
                $this->writeln();
                $this->comment('Remove --dry-run to execute migrations');
                return 0;
            }

            // Apply migrations with progress
            $this->section('🚀 Applying migrations...');
            $progress = $this->createProgressBar($totalMigrations);
            
            $appliedCount = 0;
            foreach ($migrationsToApply as $migration) {
                $tableInstance = $migration['instance'];
                $tableName = $tableInstance->tableName;
                
                if ($migration['type'] === 'create') {
                    $progress->setMessage("Creating {$tableName} table...");
                    $this->applyCreateTable($baseDb, $tableInstance, $currentBatch);
                    $progress->advance();
                    $appliedCount++;
                    $this->success("Created {$tableName} table");
                } elseif ($migration['type'] === 'columns') {
                    foreach ($migration['columns'] as $column) {
                        $progress->setMessage("Adding {$column} to {$tableName}...");
                        $this->applyAddColumn($baseDb, $tableInstance, $column, $currentBatch);
                        $progress->advance();
                        $appliedCount++;
                        $this->success("Added {$column} to {$tableName}");
                    }
                }
            }

            $progress->finish();
            $this->writeln();
            $this->success("Migration complete! {$appliedCount} migration(s) applied.");

            return 0;

        } catch (DatabaseConnectionException $e) {
            $this->error('Database connection failed: ' . $e->getMessage());
            return 1;
        } catch (DatabaseQueryException $e) {
            $this->error('Migration failed: ' . $e->getMessage());
            return 1;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }

    private function checkMigrationNeeded($baseDb, $tableInstance): array
    {
        $tableName = $tableInstance->tableName;
        $needsCreate = !$baseDb->tableExists($tableName);
        $needsColumns = [];

        if (!$needsCreate) {
            // Check which columns are missing
            $existingColumns = $baseDb->getColumns($tableName);
            foreach ($tableInstance->columns as $columnName => $columnType) {
                if (!in_array($columnName, $existingColumns, true)) {
                    $needsColumns[] = $columnName;
                }
            }
        }

        return [
            'needs_create' => $needsCreate,
            'needs_columns' => $needsColumns,
        ];
    }

    private function applyCreateTable($baseDb, $tableInstance, int $batch): void
    {
        $tableName = $tableInstance->tableName;
        $sql = $tableInstance->genSQL();
        $timestamp = date('YmdHis');
        $migrationName = "create_{$tableName}_table_{$timestamp}";

        // Check if migration already recorded
        $existing = $baseDb->sqlQuery(
            "SELECT id FROM migrations WHERE name = ?",
            [$migrationName]
        );

        if (empty($existing)) {
            $baseDb->execute($sql);
            $baseDb->execute(
                "INSERT INTO migrations (name, sql_text, batch) VALUES (?, ?, ?)",
                [$migrationName, $sql, $batch]
            );
        }
    }

    private function applyAddColumn($baseDb, $tableInstance, string $columnName, int $batch): void
    {
        $tableName = $tableInstance->tableName;
        $columnType = $tableInstance->columns[$columnName];
        $sql = $tableInstance->getAddColumnSQL($columnName, $columnType);
        $timestamp = date('YmdHis');
        $migrationName = "add_{$columnName}_to_{$tableName}_{$timestamp}";

        // Check if migration already recorded
        $existing = $baseDb->sqlQuery(
            "SELECT id FROM migrations WHERE name = ?",
            [$migrationName]
        );

        if (empty($existing)) {
            $baseDb->execute($sql);
            $baseDb->execute(
                "INSERT INTO migrations (name, sql_text, batch) VALUES (?, ?, ?)",
                [$migrationName, $sql, $batch]
            );
        }
    }
}



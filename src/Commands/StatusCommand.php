<?php
declare(strict_types=1);

namespace Reut\CLI\Commands;

use Reut\DB\DataBase;
use Reut\DB\Exceptions\DatabaseConnectionException;
use Reut\DB\Exceptions\DatabaseQueryException;
use Reut\Support\ProjectPath;

/**
 * Status command - Check migration status
 */
class StatusCommand extends Command
{
    public function getName(): string
    {
        return 'status';
    }

    public function getDescription(): string
    {
        return 'Check for pending migrations in the models';
    }

    public function getUsage(): string
    {
        return 'status [--json] [--summary] [--table=name]';
    }

    public function getOptions(): array
    {
        return [
            '--json' => 'Output as JSON',
            '--summary' => 'Show summary only',
            '--table' => 'Check status for specific table',
        ];
    }

    public function getExamples(): array
    {
        return [
            'Reut status',
            'Reut status --json',
            'Reut status --summary',
            'Reut status --table=users',
        ];
    }

    public function execute(array $args = []): int
    {
        $jsonMode = $this->hasOption('json');
        $summaryMode = $this->hasOption('summary');
        $tableFilter = $this->getOption('table');

        require ProjectPath::resolve('vendor', 'autoload.php');
        require ProjectPath::resolve('config.php');

        // Autoload models
        spl_autoload_register(function ($class) {
            $prefix = 'Reut\\Models\\';
            $baseDir = rtrim(ProjectPath::resolve('models'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (strpos($class, $prefix) === 0) {
                $relativeClass = substr($class, strlen($prefix));
                $file = realpath($baseDir . str_replace('\\', '/', $relativeClass) . '.php');
                if (file_exists($file)) {
                    require_once $file;
                }
            }
        });

        $baseDb = new DataBase($config);

        try {
            $baseDb->connect();

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
                        ]
                    ], JSON_PRETTY_PRINT) . "\n";
                    return 0;
                }
                $this->warning('No migrations table found. Run `Reut migrate` to create it.');
                return 0;
            }

            // Get applied migrations
            $migrationsQuery = "SELECT id, name, sql_text, batch, applied_at FROM migrations";
            if ($tableFilter) {
                $migrationsQuery .= " WHERE name LIKE :pattern ORDER BY batch, id";
                $appliedMigrations = $baseDb->sqlQuery($migrationsQuery, ['pattern' => "%{$tableFilter}%"]);
            } else {
                $migrationsQuery .= " ORDER BY batch, id";
                $appliedMigrations = $baseDb->sqlQuery($migrationsQuery);
            }

            // Get pending migrations
            $modelsDirectory = rtrim(ProjectPath::resolve('models'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $modelFiles = is_dir($modelsDirectory)
                ? array_filter(array_diff(scandir($modelsDirectory), ['.', '..']), fn($f) => str_ends_with($f, '.php'))
                : [];

            $pendingMigrations = [];
            $allModels = [];

            foreach ($modelFiles as $fileName) {
                $className = 'Reut\\Models\\' . pathinfo($fileName, PATHINFO_FILENAME);
                if (class_exists($className)) {
                    $tableInstance = new $className($config);
                    $allModels[] = $tableInstance;

                    $tableName = $tableInstance->tableName;
                    
                    if ($tableFilter && $tableName !== $tableFilter) {
                        continue;
                    }

                    // Check if table needs creation
                    if (!$baseDb->tableExists($tableName)) {
                        $pendingMigrations[] = [
                            'type' => 'create',
                            'table' => $tableName,
                            'description' => "Create {$tableName} table",
                        ];
                        continue;
                    }

                    // Check for missing columns
                    $existingColumns = $baseDb->getColumns($tableName);
                    foreach ($tableInstance->columns as $columnName => $columnType) {
                        if (!in_array($columnName, $existingColumns, true)) {
                            $pendingMigrations[] = [
                                'type' => 'add_column',
                                'table' => $tableName,
                                'column' => $columnName,
                                'description' => "Add {$columnName} to {$tableName}",
                            ];
                        }
                    }
                }
            }

            // Calculate summary
            $totalApplied = count($appliedMigrations);
            $totalPending = count($pendingMigrations);
            $batches = [];
            foreach ($appliedMigrations as $migration) {
                $batch = $migration['batch'] ?? 0;
                if (!isset($batches[$batch])) {
                    $batches[$batch] = 0;
                }
                $batches[$batch]++;
            }
            $totalBatches = count($batches);

            if ($jsonMode) {
                echo json_encode([
                    'applied' => $appliedMigrations,
                    'pending' => $pendingMigrations,
                    'summary' => [
                        'total_applied' => $totalApplied,
                        'total_pending' => $totalPending,
                        'total_batches' => $totalBatches,
                        'last_batch' => $totalBatches > 0 ? max(array_keys($batches)) : null,
                    ]
                ], JSON_PRETTY_PRINT) . "\n";
                return 0;
            }

            if ($summaryMode) {
                $this->section('📊 Migration Summary');
                $this->writeln("  Applied:  {$this->formatter->success((string)$totalApplied)}");
                $this->writeln("  Pending: " . ($totalPending > 0 ? $this->formatter->warning((string)$totalPending) : $this->formatter->success((string)$totalPending)));
                $this->writeln("  Batches:  {$totalBatches}");
                return 0;
            }

            // Display applied migrations
            if (!empty($appliedMigrations)) {
                $this->section('✅ Applied Migrations');
                $appliedTable = new \Reut\CLI\Output\Table($this->formatter);
                $appliedTable->setHeaders(['ID', 'Name', 'Batch', 'Applied At']);
                
                foreach ($appliedMigrations as $migration) {
                    $appliedTable->addRow([
                        $migration['id'] ?? '',
                        $migration['name'] ?? '',
                        $migration['batch'] ?? '',
                        $migration['applied_at'] ?? '',
                    ]);
                }
                $appliedTable->display();
                $this->writeln();
            } else {
                $this->info('No migrations have been applied yet.');
                $this->writeln();
            }

            // Display pending migrations
            if (!empty($pendingMigrations)) {
                $this->section('⚠️  Pending Migrations');
                $pendingTable = new \Reut\CLI\Output\Table($this->formatter);
                $pendingTable->setHeaders(['Table', 'Type', 'Description']);
                
                foreach ($pendingMigrations as $migration) {
                    $pendingTable->addRow([
                        $migration['table'] ?? '',
                        $migration['type'] ?? '',
                        $migration['description'] ?? '',
                    ]);
                }
                $pendingTable->display();
                $this->writeln();
                $this->info("Run `Reut migrate` to apply {$totalPending} pending migration(s).");
            } else {
                $this->success('All migrations are up to date!');
            }

            return 0;

        } catch (DatabaseConnectionException $e) {
            $this->error('Database connection failed: ' . $e->getMessage());
            return 1;
        } catch (DatabaseQueryException $e) {
            $this->error('Query failed: ' . $e->getMessage());
            return 1;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
}



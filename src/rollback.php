<?php
declare(strict_types=1);

use Reut\DB\DataBase;
use Reut\DB\Exceptions\DatabaseConnectionException;
use Reut\DB\Exceptions\DatabaseQueryException;
use Reut\DB\Exceptions\DatabaseMigrationException;
use Reut\Support\ProjectPath;

require ProjectPath::resolve('vendor', 'autoload.php');
require ProjectPath::resolve('config.php');
require __DIR__ . "/Utils/ascii_table.php";

/**
 * Migration Rollback Command
 * 
 * Usage:
 *   php manage.php rollback              # Rollback last batch
 *   php manage.php rollback --batch=5    # Rollback to batch 5
 *   php manage.php rollback --migration=create_users_table_20240101120000
 *   php manage.php rollback --dry-run    # Preview without executing
 */

$options = parseRollbackOptions($argv ?? []);

$baseDb = new DataBase($config);
try {
    if (!$baseDb->connect()) {
        throw new DatabaseConnectionException(
            "Failed to connect to the database",
            0,
            null,
            $config
        );
    }

    // Check if migrations table exists
    if (!$baseDb->tableExists('migrations')) {
        echo "No migrations table found. Nothing to rollback.\n";
        exit(0);
    }

    // Get all migrations ordered by batch (descending) and id (descending)
    $migrations = $baseDb->sqlQuery(
        "SELECT id, name, sql_text, batch, applied_at FROM migrations ORDER BY batch DESC, id DESC"
    );

    if (empty($migrations)) {
        echo "No migrations found. Nothing to rollback.\n";
        exit(0);
    }

    $migrationsToRollback = [];
    $dryRun = $options['dry-run'] ?? false;

    // Determine which migrations to rollback
    if (isset($options['migration'])) {
        // Rollback specific migration
        $migrationName = $options['migration'];
        foreach ($migrations as $migration) {
            if ($migration['name'] === $migrationName) {
                $migrationsToRollback = [$migration];
                break;
            }
        }
        if (empty($migrationsToRollback)) {
            echo "Migration '{$migrationName}' not found.\n";
            exit(1);
        }
    } elseif (isset($options['batch'])) {
        // Rollback to specific batch (rollback all migrations with batch >= specified)
        $targetBatch = (int)$options['batch'];
        foreach ($migrations as $migration) {
            if ((int)$migration['batch'] >= $targetBatch) {
                $migrationsToRollback[] = $migration;
            } else {
                break; // Migrations are ordered by batch DESC
            }
        }
        if (empty($migrationsToRollback)) {
            echo "No migrations found with batch >= {$targetBatch}.\n";
            exit(0);
        }
    } else {
        // Rollback last batch (default)
        $lastBatch = (int)$migrations[0]['batch'];
        foreach ($migrations as $migration) {
            if ((int)$migration['batch'] === $lastBatch) {
                $migrationsToRollback[] = $migration;
            } else {
                break; // Migrations are ordered by batch DESC
            }
        }
    }

    // Reverse order to rollback in correct sequence (oldest first)
    $migrationsToRollback = array_reverse($migrationsToRollback);

    // Display what will be rolled back
    echo "Migrations to rollback:\n";
    displayTable($migrationsToRollback, "Rollback Plan");

    if ($dryRun) {
        echo "\n[DRY-RUN] No changes will be made. Remove --dry-run to execute rollback.\n";
        exit(0);
    }

    // Confirm rollback
    echo "\n⚠️  WARNING: This will reverse the database changes made by these migrations.\n";
    echo "This action cannot be undone. Make sure you have a database backup.\n";
    echo "Do you want to continue? (yes/no): ";
    $response = trim(fgets(STDIN));
    if (strtolower($response) !== 'yes' && strtolower($response) !== 'y') {
        echo "Rollback cancelled.\n";
        exit(0);
    }

    // Execute rollback
    $rolledBack = 0;
    $errors = [];

    foreach ($migrationsToRollback as $migration) {
        try {
            // Generate reverse SQL from migration SQL
            $reverseSql = generateReverseSql($migration['sql_text'], $migration['name']);
            
            if ($reverseSql === null) {
                echo "⚠️  Warning: Cannot generate reverse SQL for '{$migration['name']}'. Skipping.\n";
                $errors[] = "Cannot reverse: {$migration['name']}";
                continue;
            }

            // Execute reverse SQL
            echo "Rolling back: {$migration['name']}...\n";
            $baseDb->execute($reverseSql);

            // Remove migration record
            $baseDb->execute(
                "DELETE FROM migrations WHERE id = :id",
                ['id' => $migration['id']]
            );

            echo "✓ Rolled back: {$migration['name']}\n";
            $rolledBack++;
        } catch (\Exception $e) {
            echo "✗ Error rolling back '{$migration['name']}': " . $e->getMessage() . "\n";
            $errors[] = "{$migration['name']}: " . $e->getMessage();
        }
    }

    // Summary
    echo "\n";
    if ($rolledBack > 0) {
        echo "Successfully rolled back {$rolledBack} migration(s).\n";
    }
    if (!empty($errors)) {
        echo "Errors occurred:\n";
        foreach ($errors as $error) {
            echo "  - {$error}\n";
        }
        exit(1);
    }
} catch (DatabaseConnectionException $e) {
    echo "Database Connection Error: " . $e->getFormattedMessage() . "\n";
    exit(1);
} catch (DatabaseQueryException $e) {
    echo "Database Query Error: " . $e->getFormattedMessage() . "\n";
    exit(1);
} catch (DatabaseMigrationException $e) {
    echo "Migration Error: " . $e->getFormattedMessage() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Parse command line options for rollback
 */
function parseRollbackOptions(array $argv): array
{
    $options = [
        'dry-run' => false,
        'batch' => null,
        'migration' => null,
    ];

    foreach ($argv as $arg) {
        if ($arg === '--dry-run') {
            $options['dry-run'] = true;
        } elseif (strpos($arg, '--batch=') === 0) {
            $options['batch'] = (int)substr($arg, 8);
        } elseif (strpos($arg, '--migration=') === 0) {
            $options['migration'] = substr($arg, 13);
        }
    }

    return $options;
}

/**
 * Generate reverse SQL from migration SQL
 * This is a simplified implementation - complex migrations may need manual rollback
 */
function generateReverseSql(string $sql, string $migrationName): ?string
{
    $sql = trim($sql);
    $sqlUpper = strtoupper($sql);

    // CREATE TABLE -> DROP TABLE (need to drop foreign keys first)
    if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i', $sql, $matches)) {
        $tableName = $matches[1];
        
        // Extract foreign key constraint names from the SQL
        $fkConstraints = [];
        if (preg_match_all('/CONSTRAINT\s+`?(\w+)`?\s+FOREIGN\s+KEY/i', $sql, $constraintMatches)) {
            $fkConstraints = $constraintMatches[1];
        }
        
        // Build DROP TABLE statement
        // MySQL will automatically drop foreign keys when dropping the table
        return "DROP TABLE IF EXISTS `{$tableName}`";
    }

    // ADD COLUMN -> DROP COLUMN
    if (preg_match('/ALTER\s+TABLE\s+`?(\w+)`?\s+ADD\s+`?(\w+)`?/i', $sql, $matches)) {
        $tableName = $matches[1];
        $columnName = $matches[2];
        
        // Check if this column has a foreign key constraint
        // If so, we need to drop the constraint first
        $baseDb = new DataBase($GLOBALS['config'] ?? []);
        try {
            $baseDb->connect();
            if ($baseDb->pdo) {
                // Check for foreign key constraints on this column
                $stmt = $baseDb->pdo->prepare("
                    SELECT CONSTRAINT_NAME 
                    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = :tableName 
                    AND COLUMN_NAME = :columnName 
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");
                $stmt->execute(['tableName' => $tableName, 'columnName' => $columnName]);
                $constraints = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                
                $dropStatements = [];
                foreach ($constraints as $constraint) {
                    $dropStatements[] = "ALTER TABLE `{$tableName}` DROP FOREIGN KEY `{$constraint}`";
                }
                
                if (!empty($dropStatements)) {
                    // Return multiple statements separated by semicolons
                    $dropStatements[] = "ALTER TABLE `{$tableName}` DROP COLUMN `{$columnName}`";
                    return implode("; ", $dropStatements);
                }
            }
        } catch (\Exception $e) {
            // If we can't check constraints, just drop the column
            // MySQL will handle constraint errors
        }
        
        return "ALTER TABLE `{$tableName}` DROP COLUMN `{$columnName}`";
    }

    // DROP COLUMN -> ADD COLUMN (requires original column definition)
    // This is complex - we'd need to store the original definition
    // For now, return null to indicate manual rollback needed
    if (preg_match('/ALTER\s+TABLE\s+`?(\w+)`?\s+DROP\s+COLUMN\s+`?(\w+)`?/i', $sql, $matches)) {
        // Cannot automatically reverse DROP COLUMN without original definition
        return null;
    }

    // Unknown migration type
    return null;
}


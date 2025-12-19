<?php
declare(strict_types=1);

use Reut\DB\DataBase;
use Reut\DB\Exceptions\DatabaseConnectionException;
use Reut\DB\Exceptions\DatabaseQueryException;
use Reut\Support\ProjectPath;

require ProjectPath::resolve('vendor', 'autoload.php');
require ProjectPath::resolve('config.php');

/**
 * Migration Validation Command
 * 
 * Validates migration SQL syntax, checks for potential data loss,
 * verifies migration dependencies, and checks for conflicts.
 * 
 * Usage:
 *   php manage.php validate-migrations
 */

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

    echo "=== Migration Validation ===\n\n";

    // Check if migrations table exists
    if (!$baseDb->tableExists('migrations')) {
        echo "✓ Migrations table does not exist yet (will be created on first migration).\n";
        exit(0);
    }

    // Get all migrations
    $migrations = $baseDb->sqlQuery(
        "SELECT id, name, sql_text, batch, applied_at FROM migrations ORDER BY batch, id"
    );

    if (empty($migrations)) {
        echo "✓ No migrations found. Nothing to validate.\n";
        exit(0);
    }

    $errors = [];
    $warnings = [];
    $validated = 0;

    echo "Validating " . count($migrations) . " migration(s)...\n\n";

    foreach ($migrations as $migration) {
        $migrationName = $migration['name'];
        $sql = $migration['sql_text'];
        
        // Validate SQL syntax
        $syntaxErrors = validateSqlSyntax($sql, $migrationName);
        if (!empty($syntaxErrors)) {
            $errors = array_merge($errors, $syntaxErrors);
            continue;
        }

        // Check for potential data loss
        $dataLossWarnings = checkDataLoss($sql, $migrationName);
        $warnings = array_merge($warnings, $dataLossWarnings);

        // Check for dangerous operations
        $dangerWarnings = checkDangerousOperations($sql, $migrationName);
        $warnings = array_merge($warnings, $dangerWarnings);

        $validated++;
    }

    // Check for migration conflicts
    $conflicts = checkMigrationConflicts($migrations);
    if (!empty($conflicts)) {
        $warnings = array_merge($warnings, $conflicts);
    }

    // Check for dependencies
    $dependencyErrors = checkDependencies($migrations);
    if (!empty($dependencyErrors)) {
        $errors = array_merge($errors, $dependencyErrors);
    }

    // Display results
    echo "Validation Results:\n";
    echo "  ✓ Validated: {$validated}\n";
    echo "  ✗ Errors: " . count($errors) . "\n";
    echo "  ⚠ Warnings: " . count($warnings) . "\n\n";

    if (!empty($errors)) {
        echo "Errors:\n";
        foreach ($errors as $error) {
            echo "  ✗ {$error}\n";
        }
        echo "\n";
    }

    if (!empty($warnings)) {
        echo "Warnings:\n";
        foreach ($warnings as $warning) {
            echo "  ⚠ {$warning}\n";
        }
        echo "\n";
    }

    if (empty($errors) && empty($warnings)) {
        echo "✓ All migrations are valid!\n";
        exit(0);
    } elseif (empty($errors)) {
        echo "⚠ Migrations have warnings but are valid.\n";
        exit(0);
    } else {
        echo "✗ Migration validation failed. Please fix errors before proceeding.\n";
        exit(1);
    }
} catch (DatabaseConnectionException $e) {
    echo "Database Connection Error: " . $e->getFormattedMessage() . "\n";
    exit(1);
} catch (DatabaseQueryException $e) {
    echo "Database Query Error: " . $e->getFormattedMessage() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Validate SQL syntax
 */
function validateSqlSyntax(string $sql, string $migrationName): array
{
    $errors = [];
    $sql = trim($sql);

    // Basic SQL syntax checks
    if (empty($sql)) {
        $errors[] = "{$migrationName}: SQL is empty";
        return $errors;
    }

    // Check for balanced parentheses
    if (substr_count($sql, '(') !== substr_count($sql, ')')) {
        $errors[] = "{$migrationName}: Unbalanced parentheses in SQL";
    }

    // Check for balanced backticks
    if (substr_count($sql, '`') % 2 !== 0) {
        $errors[] = "{$migrationName}: Unbalanced backticks in SQL";
    }

    // Check for common SQL injection patterns (basic check)
    $dangerousPatterns = [
        '/;\s*(DROP|DELETE|TRUNCATE)/i',
        '/--/',
        '/\/\*/',
    ];
    
    foreach ($dangerousPatterns as $pattern) {
        if (preg_match($pattern, $sql)) {
            // Allow if it's part of a comment or expected operation
            if (!preg_match('/CREATE|ALTER/i', $sql)) {
                $errors[] = "{$migrationName}: Potentially dangerous SQL pattern detected";
            }
        }
    }

    return $errors;
}

/**
 * Check for potential data loss
 */
function checkDataLoss(string $sql, string $migrationName): array
{
    $warnings = [];
    $sqlUpper = strtoupper($sql);

    // DROP TABLE without IF EXISTS
    if (preg_match('/DROP\s+TABLE\s+(?!IF\s+EXISTS)/i', $sql)) {
        $warnings[] = "{$migrationName}: DROP TABLE without IF EXISTS may cause errors if table doesn't exist";
    }

    // DROP COLUMN
    if (preg_match('/DROP\s+COLUMN/i', $sql)) {
        $warnings[] = "{$migrationName}: DROP COLUMN will permanently delete data in that column";
    }

    // ALTER TABLE with MODIFY (potential data loss if incompatible)
    if (preg_match('/ALTER\s+TABLE.*MODIFY/i', $sql)) {
        $warnings[] = "{$migrationName}: MODIFY COLUMN may cause data loss if types are incompatible";
    }

    return $warnings;
}

/**
 * Check for dangerous operations
 */
function checkDangerousOperations(string $sql, string $migrationName): array
{
    $warnings = [];
    $sqlUpper = strtoupper($sql);

    // TRUNCATE TABLE
    if (preg_match('/TRUNCATE/i', $sql)) {
        $warnings[] = "{$migrationName}: TRUNCATE TABLE will delete all data";
    }

    // DELETE without WHERE
    if (preg_match('/DELETE\s+FROM\s+\w+\s*(?!WHERE)/i', $sql)) {
        $warnings[] = "{$migrationName}: DELETE without WHERE clause will delete all rows";
    }

    return $warnings;
}

/**
 * Check for migration conflicts (same table/column modified multiple times)
 */
function checkMigrationConflicts(array $migrations): array
{
    $warnings = [];
    $tableOperations = [];

    foreach ($migrations as $migration) {
        $name = $migration['name'];
        $sql = $migration['sql_text'];

        // Extract table name
        if (preg_match('/(?:CREATE|ALTER|DROP)\s+TABLE\s+`?(\w+)`?/i', $sql, $matches)) {
            $tableName = $matches[1];
            if (!isset($tableOperations[$tableName])) {
                $tableOperations[$tableName] = [];
            }
            $tableOperations[$tableName][] = $name;
        }
    }

    // Check for multiple operations on same table in same batch
    foreach ($tableOperations as $table => $operations) {
        if (count($operations) > 1) {
            $warnings[] = "Table '{$table}' has multiple migrations: " . implode(', ', $operations);
        }
    }

    return $warnings;
}

/**
 * Check migration dependencies (e.g., ADD COLUMN before DROP COLUMN on same column)
 */
function checkDependencies(array $migrations): array
{
    $errors = [];
    $columnStates = []; // Track column state: 'exists' or 'dropped'

    foreach ($migrations as $migration) {
        $name = $migration['name'];
        $sql = $migration['sql_text'];

        // Check for ADD COLUMN
        if (preg_match('/ALTER\s+TABLE\s+`?(\w+)`?\s+ADD\s+`?(\w+)`?/i', $sql, $matches)) {
            $tableName = $matches[1];
            $columnName = $matches[2];
            $key = "{$tableName}.{$columnName}";
            
            if (isset($columnStates[$key]) && $columnStates[$key] === 'exists') {
                $errors[] = "{$name}: Column '{$columnName}' in table '{$tableName}' already exists (duplicate ADD)";
            }
            $columnStates[$key] = 'exists';
        }

        // Check for DROP COLUMN
        if (preg_match('/ALTER\s+TABLE\s+`?(\w+)`?\s+DROP\s+COLUMN\s+`?(\w+)`?/i', $sql, $matches)) {
            $tableName = $matches[1];
            $columnName = $matches[2];
            $key = "{$tableName}.{$columnName}";
            
            if (isset($columnStates[$key]) && $columnStates[$key] === 'dropped') {
                $errors[] = "{$name}: Column '{$columnName}' in table '{$tableName}' already dropped (duplicate DROP)";
            }
            $columnStates[$key] = 'dropped';
        }
    }

    return $errors;
}


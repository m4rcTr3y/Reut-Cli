# Migration Commands Security Fixes (v1.1)

This directory contains tests for critical security fixes and bug fixes applied to migration-related CLI commands in Reut CLI v1.1.

## Overview

This version addresses **6 critical bugs** and **2 security vulnerabilities** discovered in the migration command implementations:

1. SQL injection vulnerabilities in LIKE clauses
2. Batch query result access bug
3. Missing INSERT IGNORE protection
4. Incorrect method usage (sqlQuery vs execute)
5. Table name extraction bug
6. Regex pattern escaping issue

## Critical Fixes Applied

### 1. SQL Injection Protection in LIKE Clauses

**Files Fixed:**
- `src/migrate.php` (line 99)
- `src/checkmigration.php` (line 84)
- `packages/core/src/checkmigration.php`
- `templates/config/checkmigration.php`

**Issue:** Unescaped table names used directly in SQL LIKE clauses, allowing potential SQL injection.

**Fix:** Changed from:
```php
$existingMigrations = $baseDb->sqlQuery("SELECT name FROM migrations WHERE name LIKE '%$tableName%'");
```

To parameterized query:
```php
$existingMigrations = $baseDb->sqlQuery(
    "SELECT name FROM migrations WHERE name LIKE :pattern",
    ['pattern' => "%{$tableName}%"]
);
```

### 2. Batch Query Result Access Bug

**Files Fixed:**
- `src/update.php` (line 45)
- `packages/core/src/update.php`
- `templates/config/update.php`

**Issue:** Incorrectly accessed batch query result as `$batchQuery['max_batch']` when `sqlQuery()` returns an array of rows.

**Fix:** Changed from:
```php
$currentBatch = ($batchQuery['max_batch'] ?? 0) + 1;
```

To correct array access:
```php
$maxBatch = 0;
if (is_array($batchQuery) && isset($batchQuery[0]['max_batch'])) {
    $maxBatch = (int) $batchQuery[0]['max_batch'];
}
$currentBatch = $maxBatch + 1;
```

### 3. INSERT IGNORE Protection

**Files Fixed:**
- `src/update.php` (lines 110, 126)
- `packages/core/src/update.php`
- `templates/config/update.php`

**Issue:** Regular `INSERT` statements caused duplicate key errors when migrations were re-run.

**Fix:** Changed from:
```php
$baseDb->sqlQuery("INSERT INTO migrations ...");
```

To:
```php
$baseDb->execute("INSERT IGNORE INTO migrations ...");
```

### 4. Execute() Method for DDL/DML Statements

**Files Fixed:**
- `src/update.php` (lines 41, 109, 125)
- `packages/core/src/update.php`
- `templates/config/update.php`

**Issue:** Used `sqlQuery()` for CREATE TABLE and INSERT statements, which is designed for SELECT queries.

**Fix:** Changed from:
```php
$baseDb->sqlQuery($migrationsTableSql);  // CREATE TABLE
$baseDb->sqlQuery("INSERT INTO migrations ...");
```

To:
```php
$baseDb->execute($migrationsTableSql);  // CREATE TABLE
$baseDb->execute("INSERT IGNORE INTO migrations ...");
```

### 5. Table Name Extraction Fix

**Files Fixed:**
- `src/update.php` (line 84)
- `packages/core/src/update.php`
- `templates/config/update.php`

**Issue:** Manually extracted table name by removing 'Table' suffix, which failed for custom table names.

**Fix:** Changed from:
```php
$tableName = str_replace('Table', '', $className);
$tableInstance = new $classFullName($config);
```

To:
```php
$tableInstance = new $classFullName($config);
$tableName = $tableInstance->tableName;
```

### 6. Regex Pattern Escaping

**Files Fixed:**
- `src/checkmigration.php` (line 90)
- `packages/core/src/checkmigration.php`
- `templates/config/checkmigration.php`

**Issue:** Table names with regex special characters (`.`, `+`, `*`, etc.) caused regex errors.

**Fix:** Changed from:
```php
if (preg_match("/{$action}_{$tableName}_table/", $migration['name'])) {
```

To escaped pattern:
```php
$escapedTable = preg_quote($tableName, '/');
if (preg_match("/{$action}_{$escapedTable}_table/", $migration['name'])) {
```

## Test Files

### MigrationFixesTest.php

Comprehensive test suite covering all 6 critical fixes:

1. **testBatchQueryResultAccess()** - Verifies batch query result is accessed correctly
2. **testSqlInjectionProtectionInLikeClause()** - Tests SQL injection protection
3. **testInsertIgnoreProtection()** - Verifies INSERT IGNORE prevents duplicate errors
4. **testTableNameExtraction()** - Tests table name extraction from tableInstance
5. **testRegexPatternEscaping()** - Verifies regex works with special characters
6. **testExecuteMethodForDdlDml()** - Tests execute() method usage for DDL/DML

## Running Tests

### Prerequisites

- PHP 7.4 or higher
- PHPUnit installed
- MySQL database accessible
- Test database: `test_db_two` (root/root@1234)

### Run All v1.1 Tests

```bash
php vendor/bin/phpunit tests/new-feature-tests/v1.1/
```

### Run Specific Test File

```bash
php vendor/bin/phpunit tests/new-feature-tests/v1.1/MigrationFixesTest.php
```

### Run Specific Test Method

```bash
php vendor/bin/phpunit tests/new-feature-tests/v1.1/MigrationFixesTest.php --filter testBatchQueryResultAccess
```

### Expected Test Results

All 6 tests should pass:

```
PHPUnit 9.6.31 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.14
Configuration: /media/m4rc/Ps/Reut_CLI/phpunit.xml

......                                                              6 / 6 (100%)

Time: 00:01.425, Memory: 6.00 MB

OK (6 tests, 11 assertions)
```

**Test Results:**
- ✅ testBatchQueryResultAccess - Passed
- ✅ testSqlInjectionProtectionInLikeClause - Passed
- ✅ testInsertIgnoreProtection - Passed
- ✅ testTableNameExtraction - Passed
- ✅ testRegexPatternEscaping - Passed
- ✅ testExecuteMethodForDdlDml - Passed

## Files Modified

### Source Files
- `src/update.php`
- `src/migrate.php`
- `src/checkmigration.php`

### Monorepo Files
- `packages/core/src/update.php`
- `packages/core/src/checkmigration.php`

### Template Files
- `templates/config/update.php`
- `templates/config/checkmigration.php`

## Impact

These fixes address:
- **Security**: SQL injection vulnerabilities eliminated
- **Reliability**: Batch queries work correctly, no duplicate key errors
- **Compatibility**: Custom table names and special characters supported
- **Code Quality**: Proper method usage (execute vs sqlQuery)

## Related Documentation

- [MIGRATION_COMMANDS.md](../../../../MIGRATION_COMMANDS.md) - Migration commands reference
- [MIGRATION_CODE_REVIEW.md](../../../../MIGRATION_CODE_REVIEW.md) - Detailed code review

## Version History

- **v1.1** (January 2025) - Critical security and bug fixes
- **v1.0** (December 2024) - Initial security features and scaffolding tests


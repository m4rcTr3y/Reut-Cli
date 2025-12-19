# Migration Features Tests (v1.2)

## Overview

This test suite covers all new features and improvements implemented in v1.2 of the migration system. The tests verify functionality, safety, and usability of the enhanced migration commands.

## Test Coverage

### 1. Protected Columns Warning in Sync Command
- **Test**: `testProtectedColumnsWarning()`
- **Purpose**: Verifies that the sync command detects and warns about dropping protected columns
- **Coverage**: Protected columns detection logic

### 2. Standardized Error Handling
- **Test**: `testStandardizedErrorHandling()`
- **Purpose**: Ensures all migration commands use consistent error handling with custom exceptions
- **Coverage**: Database connection errors, query errors, migration errors

### 3. Migration Rollback Command
- **Tests**: 
  - `testRollbackLastBatch()` - Rollback last batch
  - `testRollbackSpecificBatch()` - Rollback specific batch
- **Purpose**: Verifies rollback functionality with dry-run mode
- **Coverage**: Batch selection, dry-run preview, rollback execution

### 4. MigrationHelper Class Utilities
- **Tests**:
  - `testMigrationHelperEnsureMigrationsTable()` - Table creation
  - `testMigrationHelperGetNextBatch()` - Batch number calculation
  - `testMigrationHelperGenerateMigrationName()` - Name generation with microtime
  - `testMigrationHelperRecordMigration()` - Migration recording
  - `testMigrationHelperHasMigration()` - Migration existence check
- **Purpose**: Tests shared utility functions for code reuse
- **Coverage**: All MigrationHelper static methods

### 5. Enhanced Status Command
- **Tests**:
  - `testStatusCommandJsonOutput()` - JSON format output
  - `testStatusCommandSummaryOutput()` - Summary statistics
  - `testStatusCommandTableFilter()` - Table-specific filtering
- **Purpose**: Verifies new status command features
- **Coverage**: JSON output, summary mode, table filtering

### 6. Dry-Run Mode
- **Tests**:
  - `testMigrateDryRunMode()` - Migrate command dry-run
  - `testSyncDryRunMode()` - Sync command dry-run
- **Purpose**: Ensures dry-run mode previews changes without executing
- **Coverage**: Both migrate and sync commands

### 7. Input Validation Improvements
- **Test**: `testInputValidationNonInteractive()`
- **Purpose**: Verifies timeout and retry logic for STDIN input
- **Coverage**: Non-interactive mode handling, timeout logic

### 8. Migration Name Uniqueness
- **Test**: `testMigrationHelperGenerateMigrationName()`
- **Purpose**: Ensures migration names include microtime for uniqueness
- **Coverage**: Microtime-based naming, uniqueness verification

### 9. Migration Validation Command
- **Test**: `testValidateMigrations()`
- **Purpose**: Verifies migration validation detects issues
- **Coverage**: SQL syntax validation, data loss warnings, conflict detection

### 10. Export/Import Commands
- **Tests**:
  - `testExportMigrationsJson()` - JSON export format
  - `testExportMigrationsSql()` - SQL export format
  - `testImportMigrationsJson()` - JSON import
  - `testImportMigrationsSql()` - SQL import
- **Purpose**: Tests migration history export and import functionality
- **Coverage**: Both JSON and SQL formats, round-trip import/export

## Running the Tests

### Prerequisites

1. **Database Setup**: Tests require MySQL/MariaDB with:
   - Host: `localhost`
   - Username: `root`
   - Password: `root@1234`
   - Database: `test_db_two` (created automatically)

2. **PHPUnit**: Ensure PHPUnit is installed via Composer:
   ```bash
   composer install
   ```

### Run All Tests

```bash
php vendor/bin/phpunit tests/new-feature-tests/v1.2/ --testdox
```

### Run Specific Test

```bash
php vendor/bin/phpunit tests/new-feature-tests/v1.2/MigrationFeaturesTest.php --filter testName
```

### Run with Coverage

```bash
php vendor/bin/phpunit tests/new-feature-tests/v1.2/ --coverage-text
```

## Test Structure

Each test follows this pattern:

1. **Setup**: Creates temporary project directory, database, and test models
2. **Execution**: Runs the command or function being tested
3. **Assertion**: Verifies expected behavior
4. **Cleanup**: Removes temporary files and database objects

## Test Database

Tests use a dedicated database (`test_db_two`) that is:
- Created fresh for each test run
- Dropped after each test
- Isolated from production data

## Features Tested

### Command-Line Features

- ✅ `migrate --dry-run` - Preview migrations without executing
- ✅ `sync --dry-run` - Preview sync changes without executing
- ✅ `status --json` - JSON output format
- ✅ `status --summary` - Summary statistics
- ✅ `status --table=name` - Filter by table name
- ✅ `rollback` - Rollback last batch
- ✅ `rollback --batch=N` - Rollback specific batch
- ✅ `rollback --migration=name` - Rollback specific migration
- ✅ `rollback --dry-run` - Preview rollback
- ✅ `validate-migrations` - Validate migration SQL
- ✅ `export-migrations` - Export migration history (JSON)
- ✅ `export-migrations --format=sql` - Export as SQL
- ✅ `import-migrations file.json` - Import from JSON
- ✅ `import-migrations file.sql` - Import from SQL

### Code Quality Features

- ✅ Protected columns warning system
- ✅ Standardized error handling with custom exceptions
- ✅ MigrationHelper class for code reuse
- ✅ Enhanced migration name uniqueness (microtime)
- ✅ Input validation with timeout/retry
- ✅ Non-interactive mode support

## Expected Test Results

All tests should pass with the following output:

```
Migration Features Test (v1.2)
 ✔ Test protected columns warning
 ✔ Test standardized error handling
 ✔ Test migration helper ensure migrations table
 ✔ Test migration helper get next batch
 ✔ Test migration helper generate migration name
 ✔ Test migration helper record migration
 ✔ Test status command json output
 ✔ Test status command summary output
 ✔ Test status command table filter
 ✔ Test migrate dry run mode
 ✔ Test sync dry run mode
 ✔ Test rollback last batch
 ✔ Test rollback specific batch
 ✔ Test validate migrations
 ✔ Test export migrations json
 ✔ Test export migrations sql
 ✔ Test import migrations json
 ✔ Test import migrations sql
 ✔ Test migration helper has migration
 ✔ Test input validation non interactive

Time: XX.XX seconds, Memory: XX.XX MB

OK (20 tests, XX assertions)
```

## Troubleshooting

### Database Connection Issues

If tests fail with connection errors:
1. Verify MySQL is running: `sudo systemctl status mysql`
2. Check credentials match test constants
3. Ensure user has CREATE/DROP DATABASE permissions

### Permission Issues

If tests fail with permission errors:
1. Check write permissions on `/tmp` directory
2. Verify database user has necessary privileges
3. Ensure PHP can create temporary directories

### Test Isolation Issues

If tests interfere with each other:
1. Each test creates its own temporary directory
2. Database is recreated for each test
3. Ensure cleanup runs properly in `tearDown()`

## Notes

- Tests are designed to be independent and can run in any order
- Each test cleans up after itself
- Temporary files are created in system temp directory
- Database operations are isolated to test database only

## Related Documentation

- [Migration Commands Documentation](../../../MIGRATION_COMMANDS.md)
- [Migration Code Review](../../../MIGRATION_CODE_REVIEW.md)
- [v1.1 Migration Fixes Tests](../v1.1/README.md)


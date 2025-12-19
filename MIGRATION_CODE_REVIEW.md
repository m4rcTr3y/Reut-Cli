# Migration Commands Code Review

## Critical Issues

### 1. **update.php: Wrong Method for DDL/DML Statements**

**Location**: `src/update.php` lines 41, 109, 125

**Issue**: Uses `sqlQuery()` for CREATE TABLE and INSERT statements. The `sqlQuery()` method is designed for SELECT queries that return result sets. For DDL/DML statements (CREATE, INSERT, UPDATE, DELETE), the `execute()` method should be used.

**Current Code**:
```php
$baseDb->sqlQuery($migrationsTableSql);  // Line 41 - CREATE TABLE
$baseDb->sqlQuery("INSERT INTO migrations ...");  // Lines 109, 125
```

**Problem**: 
- `sqlQuery()` returns an array of results, which is unnecessary for INSERT/CREATE
- `execute()` properly handles exceptions and returns boolean success
- `execute()` throws proper exceptions (`DatabaseQueryException`) for error handling

**Fix**: Replace with `execute()`:
```php
$baseDb->execute($migrationsTableSql);
$baseDb->execute("INSERT INTO migrations ...", [...]);
```

---

### 2. **update.php: Incorrect Batch Query Result Access**

**Location**: `src/update.php` line 45

**Issue**: Incorrectly accesses batch query result. `sqlQuery()` returns an array of rows, not a single associative array.

**Current Code**:
```php
$batchQuery = $baseDb->sqlQuery("SELECT MAX(batch) as max_batch FROM migrations");
$currentBatch = ($batchQuery['max_batch'] ?? 0) + 1;
```

**Problem**: `sqlQuery()` returns `[['max_batch' => 5]]`, not `['max_batch' => 5]`

**Fix**: Access first row:
```php
$batchQuery = $baseDb->sqlQuery("SELECT MAX(batch) as max_batch FROM migrations");
$maxBatch = 0;
if (is_array($batchQuery) && isset($batchQuery[0]['max_batch'])) {
    $maxBatch = (int) $batchQuery[0]['max_batch'];
}
$currentBatch = $maxBatch + 1;
```

**Reference**: See `migrate.php` lines 56-61 for correct implementation.

---

### 3. **update.php: Missing INSERT IGNORE Protection**

**Location**: `src/update.php` lines 110, 126

**Issue**: Uses regular `INSERT` instead of `INSERT IGNORE`, which can cause duplicate key errors if migrations are re-run.

**Current Code**:
```php
$baseDb->sqlQuery(
    "INSERT INTO migrations (name, sql_text, batch) VALUES (:name, :sql_text, :batch)",
    ['name' => $migrationName, 'sql_text' => $sql, 'batch' => $currentBatch]
);
```

**Problem**: If migration name already exists (UNIQUE constraint), this will throw an error.

**Fix**: Use `INSERT IGNORE` like `migrate.php`:
```php
$baseDb->execute(
    "INSERT IGNORE INTO migrations (name, sql_text, batch) VALUES (:name, :sql_text, :batch)",
    ['name' => $migrationName, 'sql_text' => $sql, 'batch' => $currentBatch]
);
```

**Note**: The comment in `update.php` says "Removed check for existing migration name", but without INSERT IGNORE, re-running sync will fail.

---

### 4. **SQL Injection Vulnerability in LIKE Clauses**

**Location**: 
- `src/migrate.php` line 99
- `src/checkmigration.php` line 84

**Issue**: Unescaped table name used directly in SQL LIKE clause, allowing potential SQL injection if table name contains special characters.

**Current Code**:
```php
$existingMigrations = $baseDb->sqlQuery("SELECT name FROM migrations WHERE name LIKE '%$tableName%'");
```

**Problem**: If `$tableName` contains SQL special characters (e.g., `'`, `%`, `_`), it could break the query or allow injection.

**Fix**: Use parameterized query:
```php
$existingMigrations = $baseDb->sqlQuery(
    "SELECT name FROM migrations WHERE name LIKE :pattern",
    ['pattern' => "%{$tableName}%"]
);
```

---

### 5. **checkmigration.php: Unescaped Regex Pattern**

**Location**: `src/checkmigration.php` line 90

**Issue**: Table name not escaped in regex pattern, causing potential regex errors with special characters.

**Current Code**:
```php
if (preg_match("/{$action}_{$tableName}_table/", $migration['name'])) {
```

**Problem**: If `$tableName` contains regex special characters (e.g., `.`, `+`, `*`, `[`, `]`), the regex will fail or match incorrectly.

**Fix**: Escape table name:
```php
$escapedTable = preg_quote($tableName, '/');
if (preg_match("/{$action}_{$escapedTable}_table/", $migration['name'])) {
```

**Reference**: `migrate.php` line 103 correctly uses `preg_quote()`.

---

### 6. **update.php: Incorrect Table Name Extraction**

**Location**: `src/update.php` line 84

**Issue**: Manually extracts table name by removing 'Table' suffix, but should use the model's `tableName` property.

**Current Code**:
```php
$tableName = str_replace('Table', '', $className);
```

**Problem**: 
- Assumes all model classes end with 'Table' (may not always be true)
- Doesn't account for models that set custom `tableName` in constructor
- Could fail if class name contains 'Table' elsewhere (e.g., `TableDataTable`)

**Fix**: Use the model instance's `tableName` property:
```php
$tableInstance = new $classFullName($config);
$tableName = $tableInstance->tableName;
```

**Reference**: `migrate.php` line 95 correctly uses `$tableInstance->tableName`.

---

## Medium Priority Issues

### 7. **update.php: Missing Protected Columns Check**

**Location**: `src/update.php` line 120

**Issue**: `sync` command doesn't respect `protectedColumns` when dropping columns, which is intentional but potentially dangerous.

**Current Code**:
```php
$columnsToDrop = array_diff($dbColumns, $modelColumnNames);
```

**Recommendation**: Consider adding a warning or confirmation prompt when dropping protected columns:
```php
$protected = $tableInstance->protectedColumns ?? [];
$columnsToDrop = array_diff($dbColumns, $modelColumnNames);
$protectedToDrop = array_intersect($columnsToDrop, $protected);

if (!empty($protectedToDrop)) {
    echo "WARNING: About to drop protected columns: " . implode(', ', $protectedToDrop) . "\n";
    echo "Do you want to continue? (yes/no): ";
    // ... confirmation logic
}
```

**Note**: This is documented behavior (sync is aggressive), but a safety check would be helpful.

---

### 8. **Inconsistent Error Handling**

**Location**: Multiple files

**Issue**: Different commands handle errors differently:
- `migrate.php`: Catches `Exception` generically
- `update.php`: Catches `PDOException` and `Exception` separately
- `checkmigration.php`: Catches `PDOException` and `Exception` separately

**Recommendation**: Standardize error handling across all migration commands. Consider using custom exceptions from `Reut\DB\Exceptions` namespace consistently.

---

### 9. **Redundant Migration History Check in migrate.php**

**Location**: `src/migrate.php` lines 99-119, 133, 176, 197

**Issue**: `migrate.php` uses both `hasMigration()` check AND `INSERT IGNORE`. This is redundant but not harmful.

**Current Behavior**: 
- Checks migration history before applying
- Uses INSERT IGNORE as backup

**Recommendation**: Keep both for safety, but document why. The `hasMigration()` check prevents unnecessary work, while `INSERT IGNORE` prevents errors if check fails.

---

## Code Quality Improvements

### 10. **Code Duplication**

**Issue**: Similar code patterns repeated across files:
- Model loading logic (lines 15-32 in migrate.php, update.php, checkmigration.php)
- Migration table creation (lines 44-53 in migrate.php, 33-41 in update.php)
- Batch query logic (lines 56-61 in migrate.php, 44-45 in update.php)

**Recommendation**: Extract common functionality into shared functions or a migration helper class.

---

### 11. **Missing Input Validation**

**Location**: `src/update.php` line 67

**Issue**: User input from STDIN not validated before using in SQL.

**Current Code**:
```php
$response = trim(fgets(STDIN));
if (strtolower($response) === 'yes' || strtolower($response) === 'y') {
```

**Recommendation**: This is fine, but consider adding a timeout or max retry limit for non-interactive environments.

---

### 12. **Inconsistent Migration Name Format**

**Issue**: All commands use timestamp-based migration names, but the format is consistent. However, the timestamp is generated once per command run, so multiple migrations in the same second could theoretically collide (though INSERT IGNORE handles this).

**Recommendation**: Consider using microtime or adding a counter to ensure uniqueness within the same second.

---

## Summary of Required Fixes

### Critical (Must Fix):
1. ✅ Replace `sqlQuery()` with `execute()` for DDL/DML in `update.php` (lines 41, 109, 125)
2. ✅ Fix batch query result access in `update.php` (line 45)
3. ✅ Add `INSERT IGNORE` to `update.php` (lines 110, 126)
4. ✅ Fix SQL injection in LIKE clauses (`migrate.php` line 99, `checkmigration.php` line 84)
5. ✅ Escape regex pattern in `checkmigration.php` (line 90)
6. ✅ Use `$tableInstance->tableName` instead of string manipulation in `update.php` (line 84)

### Recommended (Should Fix):
7. ⚠️ Add protected columns warning in `sync` command
8. ⚠️ Standardize error handling across commands
9. ⚠️ Extract common code into shared functions

### Nice to Have:
10. 💡 Add input validation improvements
11. 💡 Consider microtime for migration names

---

## Testing Recommendations

After fixes, verify:
1. `sync` command works correctly with `execute()` method
2. Batch numbering works correctly
3. Re-running `sync` doesn't cause duplicate key errors
4. SQL injection attempts are blocked
5. Regex patterns work with special characters in table names
6. Table name extraction works for all model naming conventions


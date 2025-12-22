<?php

declare(strict_types=1);

namespace Reut\DB;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Psr\Log\LoggerInterface;
use Reut\DB\Exceptions\ConnectionError;
use Reut\DB\Types\ColumnType;
use Reut\Support\ProjectPath;

/**
 * Class Database
 * handles all the databse crud operations for a database tableName, it implements all the databse logic when creating a tableName
 * 
 * @package Reut\DB\Database
 * 
 * @param array $config   the configuration for the database which include the databse table and connection
 * @param array $columns  the columns for a tableName
 * @param string $tableName the name of the database table
 * @param bool $hasRelationships=false if the table has a relationship 
 * @param int $relationships=0 number of relationships the table has
 * 
 * 
 * 
 */

class DataBase
{
    public $pdo;
    public $config;
    public $tableName;
    public $hasRelationships;
    public $relationships;
    public $results;
    public $disabledRoutes;
    public $fileFields;

    public array $columns = [];
    public array $protectedColumns = ['created_at', 'updated_at'];
    protected array $foreignKeys = [];

    public function __construct(array $config, $columns = [], ?String $tableName = null, Bool $hasRelationships = false, $relationships = 0, array $fileFields = [], array $disabledRoutes = [], array $protectedColumns = ['created_at', 'updated_at'])
    {
        $this->config = $config;
        $this->tableName = $tableName;
        $this->hasRelationships = $hasRelationships;
        $this->columns = $columns ?? [];
        $this->relationships = $relationships;
        $this->disabledRoutes = $disabledRoutes;
        $this->fileFields = $fileFields;
        $this->protectedColumns = $protectedColumns;
    }

    /**
     * Register a foreign key constraint for the table.
     *
     * @param string $column            The local column that holds the foreign key
     * @param string $referencedTable   The referenced table name
     * @param string $referencedColumn  The referenced column name
     * @param string $onDelete          ON DELETE behavior (e.g., CASCADE, SET NULL)
     * @param string $onUpdate          ON UPDATE behavior
     * @param string|null $constraint   Optional constraint name
     * @return $this
     */
    public function addForeignKey(
        string $column,
        string $referencedTable,
        string $referencedColumn = 'id',
        string $onDelete = 'CASCADE',
        string $onUpdate = 'CASCADE',
        ?string $constraint = null
    ): self {
        if (!isset($this->columns[$column])) {
            throw new \InvalidArgumentException("Column '{$column}' must be defined before adding a foreign key.");
        }

        $this->foreignKeys[] = [
            'column' => $column,
            'referenced_table' => $referencedTable,
            'referenced_column' => $referencedColumn,
            'on_delete' => strtoupper($onDelete),
            'on_update' => strtoupper($onUpdate),
            'constraint' => $constraint
        ];

        $this->hasRelationships = true;
        $this->relationships = max($this->relationships, count($this->foreignKeys));

        return $this;
    }

    public function hasRelationships(): bool
    {
        return !empty($this->foreignKeys) || (bool)$this->hasRelationships;
    }

    public function getRelationshipCount(): int
    {
        return !empty($this->foreignKeys) ? count($this->foreignKeys) : (int)$this->relationships;
    }

    // todo: execute the connect function by default on call of the function

    /**
     * connect: connects to the dabase
     */
    public function connect()
    {
        try {
            /*  $this->pdo = new \PDO(
                "mysql:host={$this->config['host']};dbname={$this->config['dbname']};port=3306",
                $this->config['username'],
                $this->config['password']
            );*/
            $this->pdo = new \PDO(
                "mysql:host={$this->config['host']};dbname={$this->config['dbname']}",
                $this->config['username'],
                $this->config['password']
            );
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
            return true;
        } catch (\PDOException $e) {
            // throw new Exception("Unk")
            throw new ConnectionError("\nFailed to connect to database");
        }
    }

    public function addColumn(string $columnName, ColumnType $columnType)
    {
        $this->columns[$columnName] = $columnType;
    }

    /**
     * Execute a statement that does not return a result set (CREATE/INSERT/UPDATE/DELETE).
     */
    public function execute(string $query, array $params = []): bool
    {
        $this->connect();
        if (!$this->pdo) {
            echo "Database connection failed";
            return false;
        }

        try {
            $stmt = $this->pdo->prepare($query);
            return $stmt->execute($params);
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function getAddColumnSQL(string $column, ColumnType $type): string
    {
        return "ALTER TABLE " . $this->tableName . " ADD $column " . $type->getSql();
    }

    public function addColumnToTable(string $column, ColumnType $type): bool
    {
        $sql = $this->getAddColumnSQL($column, $type);
        return $this->sqlQuery($sql) !== false;
    }

    public function genSQL()
    {
        if (empty($this->columns)) {
            return false;
        }

        $columnDefinitions = [];

        $primaryKeys = [];
        foreach ($this->columns as $name => $colType) {
            $columnDefinitions[] = "  $name " . $colType->getSql();
            if ($colType->isPrimaryKey()) {
                $primaryKeys[] = $name;
            }
        }

        $constraintDefinitions = $this->buildForeignKeySql();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (\n";
        $sql .= implode(",\n", array_merge($columnDefinitions, $constraintDefinitions));
        $sql .= "\n) ENGINE=InnoDB;";
        return $sql;
    }

    protected function buildForeignKeySql(): array
    {
        $sql = [];
        foreach ($this->foreignKeys as $index => $fk) {
            $constraintName = $fk['constraint']
                ? $fk['constraint']
                : sprintf(
                    'fk_%s_%s_%d',
                    strtolower($this->tableName),
                    strtolower($fk['column']),
                    $index + 1
                );

            $sql[] = sprintf(
                "  CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s) ON DELETE %s ON UPDATE %s",
                $constraintName,
                $fk['column'],
                $fk['referenced_table'],
                $fk['referenced_column'],
                $fk['on_delete'],
                $fk['on_update']
            );
        }

        return $sql;
    }

    /**
     * Expose registered foreign keys for external tooling (e.g., the viewer).
     */
    public function getForeignKeys(): array
    {
        return $this->foreignKeys;
    }

    /**
     * Get the SQL type name from a ColumnType instance
     * 
     * @param ColumnType $columnType
     * @return string SQL type name (e.g., 'INTEGER', 'VARCHAR')
     */
    public function getColumnTypeName(ColumnType $columnType): string
    {
        // Use reflection to access protected $name property
        $reflection = new \ReflectionClass($columnType);
        $property = $reflection->getProperty('name');
        $property->setAccessible(true);
        return $property->getValue($columnType);
    }

    /**
     * Validate that a foreign key column type matches the referenced column type
     * 
     * @param string $referencedTable The referenced table name
     * @param string $referencedColumn The referenced column name
     * @param ColumnType $localColumnType The local column type
     * @return bool True if types are compatible
     * @throws \Exception If types don't match
     */
    public function validateForeignKeyColumnType(string $referencedTable, string $referencedColumn, ColumnType $localColumnType): bool
    {
        $this->connect();
        if (!$this->pdo) {
            throw new \RuntimeException('Database connection failed');
        }

        try {
            // Get the referenced column type from database
            $stmt = $this->pdo->prepare("
                SELECT DATA_TYPE, COLUMN_TYPE 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = :dbname 
                AND TABLE_NAME = :tableName 
                AND COLUMN_NAME = :columnName
            ");
            $stmt->execute([
                'dbname' => $this->config['dbname'],
                'tableName' => $referencedTable,
                'columnName' => $referencedColumn
            ]);
            
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$result) {
                throw new \Exception("Referenced column '{$referencedColumn}' not found in table '{$referencedTable}'");
            }

            $referencedDataType = strtoupper($result['DATA_TYPE']);
            $localTypeName = strtoupper($this->getColumnTypeName($localColumnType));

            // Map of compatible types
            $compatibleTypes = [
                'INT' => ['INTEGER', 'INT', 'BIGINT', 'SMALLINT', 'TINYINT', 'MEDIUMINT'],
                'INTEGER' => ['INTEGER', 'INT', 'BIGINT', 'SMALLINT', 'TINYINT', 'MEDIUMINT'],
                'BIGINT' => ['BIGINT', 'INT', 'INTEGER'],
                'VARCHAR' => ['VARCHAR', 'CHAR', 'TEXT', 'TINYTEXT', 'MEDIUMTEXT', 'LONGTEXT'],
                'CHAR' => ['CHAR', 'VARCHAR', 'TEXT'],
                'TEXT' => ['TEXT', 'VARCHAR', 'CHAR', 'TINYTEXT', 'MEDIUMTEXT', 'LONGTEXT'],
            ];

            // Check exact match first
            if ($localTypeName === $referencedDataType) {
                return true;
            }

            // Check compatibility
            if (isset($compatibleTypes[$referencedDataType])) {
                if (in_array($localTypeName, $compatibleTypes[$referencedDataType], true)) {
                    return true;
                }
            }

            // For integer types, check if both are numeric
            if (in_array($referencedDataType, ['INT', 'INTEGER', 'BIGINT', 'SMALLINT', 'TINYINT', 'MEDIUMINT']) &&
                in_array($localTypeName, ['INT', 'INTEGER', 'BIGINT', 'SMALLINT', 'TINYINT', 'MEDIUMINT'])) {
                return true;
            }

            throw new \Exception(
                "Foreign key column type mismatch: " .
                "Local column type '{$localTypeName}' is not compatible with " .
                "referenced column type '{$referencedDataType}' in table '{$referencedTable}'"
            );
        } catch (\PDOException $e) {
            throw new \Exception("Error validating foreign key column type: " . $e->getMessage());
        }
    }

    /**
     * Validate that all foreign key relationships are valid (for migrations)
     * 
     * @param array $allTableInstances Array of all table instances to check against
     * @return array Array of validation errors (empty if valid)
     */
    public function validateForeignKeyRelationships(array $allTableInstances): array
    {
        $errors = [];
        
        foreach ($this->foreignKeys as $fk) {
            $referencedTable = $fk['referenced_table'];
            $referencedColumn = $fk['referenced_column'];
            $localColumn = $fk['column'];
            
            // Check if referenced table exists in our models
            $referencedTableInstance = null;
            foreach ($allTableInstances as $instance) {
                if ($instance->tableName === $referencedTable) {
                    $referencedTableInstance = $instance;
                    break;
                }
            }
            
            if (!$referencedTableInstance) {
                $errors[] = "Foreign key '{$localColumn}' references table '{$referencedTable}' which does not exist in models";
                continue;
            }
            
            // Check if referenced column exists in referenced table model
            if (!isset($referencedTableInstance->columns[$referencedColumn])) {
                $errors[] = "Foreign key '{$localColumn}' references column '{$referencedColumn}' which does not exist in model '{$referencedTable}'";
                continue;
            }
            
            // Validate column types match (only if table exists in database)
            // For new tables, we'll validate during migration
            try {
                $localColumnType = $this->columns[$localColumn];
                $referencedColumnType = $referencedTableInstance->columns[$referencedColumn];
                
                // Compare types from model definitions
                $localTypeName = strtoupper($this->getColumnTypeName($localColumnType));
                $referencedTypeName = strtoupper($this->getColumnTypeName($referencedColumnType));
                
                // Map of compatible types
                $compatibleTypes = [
                    'INT' => ['INTEGER', 'INT', 'BIGINT', 'SMALLINT', 'TINYINT', 'MEDIUMINT'],
                    'INTEGER' => ['INTEGER', 'INT', 'BIGINT', 'SMALLINT', 'TINYINT', 'MEDIUMINT'],
                    'BIGINT' => ['BIGINT', 'INT', 'INTEGER'],
                    'VARCHAR' => ['VARCHAR', 'CHAR', 'TEXT', 'TINYTEXT', 'MEDIUMTEXT', 'LONGTEXT'],
                    'CHAR' => ['CHAR', 'VARCHAR', 'TEXT'],
                    'TEXT' => ['TEXT', 'VARCHAR', 'CHAR', 'TINYTEXT', 'MEDIUMTEXT', 'LONGTEXT'],
                ];

                // Check exact match first
                if ($localTypeName === $referencedTypeName) {
                    continue; // Types match
                }

                // Check compatibility
                if (isset($compatibleTypes[$referencedTypeName])) {
                    if (in_array($localTypeName, $compatibleTypes[$referencedTypeName], true)) {
                        continue; // Types are compatible
                    }
                }

                // For integer types, check if both are numeric
                if (in_array($referencedTypeName, ['INT', 'INTEGER', 'BIGINT', 'SMALLINT', 'TINYINT', 'MEDIUMINT']) &&
                    in_array($localTypeName, ['INT', 'INTEGER', 'BIGINT', 'SMALLINT', 'TINYINT', 'MEDIUMINT'])) {
                    continue; // Both are integer types
                }

                $errors[] = "Foreign key '{$localColumn}': Column type '{$localTypeName}' is not compatible with referenced column type '{$referencedTypeName}' in table '{$referencedTable}'";
            } catch (\Exception $e) {
                $errors[] = "Foreign key '{$localColumn}': " . $e->getMessage();
            }
        }
        
        return $errors;
    }

    /**
     * Get relationship type (belongsTo, hasMany, etc.)
     * This is a belongsTo relationship if this table has a foreign key column
     * 
     * @param string $relationshipName Optional relationship name (column name)
     * @return string Relationship type
     */
    public function getRelationshipType(?string $relationshipName = null): string
    {
        if ($relationshipName && isset($this->columns[$relationshipName])) {
            // Check if this column is a foreign key
            foreach ($this->foreignKeys as $fk) {
                if ($fk['column'] === $relationshipName) {
                    return 'belongsTo';
                }
            }
        }
        
        // Default: if we have foreign keys, they are belongsTo relationships
        return !empty($this->foreignKeys) ? 'belongsTo' : 'none';
    }

    /**
     * Get relationship metadata for a specific relationship
     * 
     * @param string $columnName The foreign key column name
     * @return array|null Relationship metadata or null if not found
     */
    public function getRelationshipMetadata(string $columnName): ?array
    {
        foreach ($this->foreignKeys as $fk) {
            if ($fk['column'] === $columnName) {
                return [
                    'column' => $fk['column'],
                    'referenced_table' => $fk['referenced_table'],
                    'referenced_column' => $fk['referenced_column'],
                    'type' => 'belongsTo',
                    'on_delete' => $fk['on_delete'],
                    'on_update' => $fk['on_update'],
                    'constraint' => $fk['constraint'] ?? null
                ];
            }
        }
        
        return null;
    }

    public function createDatabase($dbname)
    {
        try {
            $this->pdo = new \PDO(
                "mysql:host={$this->config['host']}",
                $this->config['username'],
                $this->config['password']
            );
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $stmt = $this->pdo->prepare("CREATE DATABASE IF NOT EXISTS $dbname");
            return $stmt->execute();
        } catch (\PDOException $e) {
            echo "Database creation failed: " . $e->getMessage();
            return false;
        }
    }
    /**
     * This is called when creating the table
     * @param string $tableName required, or can use $this->tableName which is accessed from the Database Class
     * @param array $columns also required, 
     * @return bool true if database has been created and false when failed
     */

    public function createTable(): bool
    {
        $this->connect();
        if (!$this->pdo) {
            echo "Database connection failed";
            return false;
        }
        try {
            $qrry = $this->genSQL();
            if (!$qrry) {
                return false;
            } else {
                $stmt = $this->pdo->prepare($qrry);
                return $stmt->execute();
            }
        } catch (\PDOException $e) {
            echo $e->getMessage();
            return false;
        }
    }

    // CRUD operations and other methods...

    /**
     * Eager load relationships for the current results
     * 
     * @param array|string $relationships Relationship names to load (e.g., ['user', 'comments'] or 'user,comments')
     * @return $this
     */
    public function with($relationships)
    {
        if (empty($this->results)) {
            return $this;
        }

        // Normalize relationships to array
        if (is_string($relationships)) {
            $relationships = array_map('trim', explode(',', $relationships));
        }

        if (empty($relationships)) {
            return $this;
        }

        // Check if results is single record or array
        // Single record: associative array without numeric index 0
        // Multiple records: indexed array with numeric keys starting from 0
        $isSingle = is_array($this->results) && 
                    !empty($this->results) && 
                    !isset($this->results[0]) && 
                    !array_key_exists(0, $this->results);
        
        if ($isSingle) {
            // Single result (associative array, not indexed)
            $this->loadRelationshipsForRecord($this->results, $relationships);
        } else {
            // Multiple results (indexed array)
            foreach ($this->results as &$record) {
                $this->loadRelationshipsForRecord($record, $relationships);
            }
        }

        return $this;
    }

    /**
     * Load relationship counts (efficient COUNT queries, doesn't load data)
     * 
     * @param array|string $relationships Relationship aliases to count (e.g., [['comments', 'post_id']])
     * @return $this
     */
    public function withCount($relationships)
    {
        if (empty($this->results)) {
            return $this;
        }

        // Normalize relationships to array
        if (is_string($relationships)) {
            $relationships = array_map('trim', explode(',', $relationships));
        }

        if (empty($relationships)) {
            return $this;
        }

        // Check if results is single record or array
        $isSingle = is_array($this->results) && 
                    !empty($this->results) && 
                    !isset($this->results[0]) && 
                    !array_key_exists(0, $this->results);
        
        if ($isSingle) {
            // Single result
            $this->loadRelationshipCountsForRecord($this->results, $relationships);
        } else {
            // Multiple results
            foreach ($this->results as &$record) {
                $this->loadRelationshipCountsForRecord($record, $relationships);
            }
        }

        return $this;
    }

    /**
     * Load relationship counts for a single record
     * 
     * @param array &$record Record to load counts for
     * @param array $relationships Relationship aliases to count
     */
    private function loadRelationshipCountsForRecord(array &$record, array $relationships): void
    {
        foreach ($relationships as $relationship) {
            // Format: [alias, fkColumn]
            if (is_array($relationship) && count($relationship) >= 2) {
                $alias = trim($relationship[0]);
                $fkColumn = trim($relationship[1]);
                
                // Find table with this FK column pointing to current table
                $hasManyTable = $this->findTableWithForeignKey($fkColumn, $this->tableName);
                
                if ($hasManyTable && isset($record['id'])) {
                    try {
                        $this->connect();
                        if (!$this->pdo) {
                            continue;
                        }

                        // Efficient COUNT query - doesn't load data
                        $stmt = $this->pdo->prepare(
                            "SELECT COUNT(*) as count FROM `{$hasManyTable}` WHERE `{$fkColumn}` = ?"
                        );
                        $stmt->execute([$record['id']]);
                        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                        $record[$alias . '_count'] = (int)($result['count'] ?? 0);
                    } catch (\PDOException $e) {
                        error_log("Error loading count for relationship '{$alias}': " . $e->getMessage());
                        $record[$alias . '_count'] = 0;
                    }
                } else {
                    $record[$alias . '_count'] = 0;
                }
            }
        }
    }

    /**
     * Load relationships for a single record
     * 
     * @param array &$record Record to load relationships for
     * @param array $relationships Relationship names to load
     */
    private function loadRelationshipsForRecord(array &$record, array $relationships): void
    {
        foreach ($relationships as $relationship) {
            // Check if it's new format (array with [alias, fkColumn]) or old format (string)
            if (is_array($relationship) && count($relationship) === 2) {
                // New format: [alias, fkColumn]
                [$alias, $fkColumn] = $relationship;
                $alias = trim($alias);
                $fkColumn = trim($fkColumn);
                
                // Check if this is a nested relationship (e.g., ['post.user', 'post_id'])
                if (strpos($alias, '.') !== false) {
                    // Handle nested relationships with aliases
                    $parts = explode('.', $alias, 2);
                    $parentAlias = trim($parts[0]);
                    $nestedRel = trim($parts[1]);
                    
                    // For nested relationships, we need to find the parent FK column first
                    // This is complex, so for now we'll use the old approach
                    // TODO: Implement proper nested relationship aliasing
                    $this->loadSingleRelationship($record, $fkColumn, $alias);
                } else {
                    // Simple relationship with alias
                    $this->loadSingleRelationship($record, $fkColumn, $alias);
                }
            } else {
                // Old format: string (backward compatibility)
                $relationship = is_string($relationship) ? trim($relationship) : (string)$relationship;
                
                // Check if this is a nested relationship (e.g., 'post_id.user_id' or 'post.user')
                if (strpos($relationship, '.') !== false) {
                    $parts = explode('.', $relationship, 2);
                    $parentRel = trim($parts[0]);
                    $nestedRel = trim($parts[1]);
                    
                    // Load parent relationship first (only if not already loaded as an object)
                    // Check if parentRel is already an array (loaded relationship) or still an integer (FK value)
                    if (!isset($record[$parentRel]) || !is_array($record[$parentRel]) || (isset($record[$parentRel]) && is_numeric($record[$parentRel]))) {
                        $this->loadSingleRelationship($record, $parentRel);
                    }
                    
                    // Then load nested relationship if parent was loaded
                    if (isset($record[$parentRel]) && $record[$parentRel] !== null && is_array($record[$parentRel])) {
                        if (isset($record[$parentRel][0]) && is_numeric(key($record[$parentRel]))) {
                            // HasMany relationship (array of records)
                            foreach ($record[$parentRel] as &$parentRecord) {
                                if (is_array($parentRecord)) {
                                    // Get the table name from the parent relationship metadata
                                    $parentMetadata = $this->getRelationshipMetadata($parentRel);
                                    $parentTable = $parentMetadata ? $parentMetadata['referenced_table'] : null;
                                    $this->loadNestedRelationshipForRecord($parentRecord, $nestedRel, $parentTable);
                                }
                            }
                        } else {
                            // BelongsTo relationship (single record object)
                            // Get the table name from the parent relationship metadata
                            $parentMetadata = $this->getRelationshipMetadata($parentRel);
                            $parentTable = $parentMetadata ? $parentMetadata['referenced_table'] : null;
                            $this->loadNestedRelationshipForRecord($record[$parentRel], $nestedRel, $parentTable);
                        }
                    }
                } else {
                    $this->loadSingleRelationship($record, $relationship);
                }
            }
        }
    }
    
    /**
     * Load nested relationship for a record (used for nested eager loading)
     * 
     * @param array &$record The parent record that already has a relationship loaded
     * @param string $nestedRel The nested relationship name (e.g., 'user_id')
     * @param string|null $parentTableName The table name that the parent record belongs to (if known)
     */
    private function loadNestedRelationshipForRecord(array &$record, string $nestedRel, ?string $parentTableName = null): void
    {
        // For nested relationships, we need to load a relationship from an already-loaded record
        // The record might be from a different table, so we need to query the database
        // to find which table has this FK column
        
        if (!isset($record[$nestedRel]) || !is_numeric($record[$nestedRel])) {
            return;
        }
        
        $fkValue = $record[$nestedRel];
        
        try {
            $this->connect();
            if (!$this->pdo) {
                return;
            }
            
            // If we know the parent table name, query specifically for that table's FK
            // Otherwise, query for any table with this FK column name
            if ($parentTableName) {
                $stmt = $this->pdo->prepare("
                    SELECT REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = :tableName
                    AND COLUMN_NAME = :columnName
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                    LIMIT 1
                ");
                $stmt->execute([
                    'tableName' => $parentTableName,
                    'columnName' => $nestedRel
                ]);
            } else {
                // Fallback: query for any table with this FK column
                $stmt = $this->pdo->prepare("
                    SELECT REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND COLUMN_NAME = :columnName
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                    LIMIT 1
                ");
                $stmt->execute(['columnName' => $nestedRel]);
            }
            
            $fkInfo = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($fkInfo) {
                $referencedTable = $fkInfo['REFERENCED_TABLE_NAME'];
                $referencedColumn = $fkInfo['REFERENCED_COLUMN_NAME'] ?: 'id';
                
                // Load the related record
                $stmt = $this->pdo->prepare(
                    "SELECT * FROM `{$referencedTable}` WHERE `{$referencedColumn}` = ? LIMIT 1"
                );
                $stmt->execute([$fkValue]);
                $relatedRecord = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($relatedRecord) {
                    $record[$nestedRel] = $relatedRecord;
                } else {
                    $record[$nestedRel] = null;
                }
            }
        } catch (\PDOException $e) {
            // Silently fail - nested relationship loading is optional
            error_log("Error loading nested relationship '{$nestedRel}': " . $e->getMessage());
        }
    }

    /**
     * Load a single relationship for a record
     * 
     * @param array &$record Record to load relationship for
     * @param string $relationshipName Relationship name (column name)
     */
    /**
     * Load a single relationship for a record
     * 
     * @param array &$record The record to load the relationship for
     * @param string $fkColumn The foreign key column name (or relationship name for backward compat)
     * @param string|null $alias Optional alias name. If provided, relationship is stored under alias and FK column is removed from results
     */
    private function loadSingleRelationship(array &$record, string $fkColumn, ?string $alias = null): void
    {
        // If alias is provided, use fkColumn to find metadata, otherwise use fkColumn as relationship name (backward compat)
        $relationshipName = $alias ?? $fkColumn;
        $metadata = $this->getRelationshipMetadata($fkColumn);
        
        if (!$metadata) {
            // Relationship not found, skip
            return;
        }

        $actualFkColumn = $metadata['column'];
        $referencedTable = $metadata['referenced_table'];
        $referencedColumn = $metadata['referenced_column'];

        // Check if foreign key value exists in record
        if (!isset($record[$actualFkColumn])) {
            return;
        }

        $fkValue = $record[$actualFkColumn];
        
        // Skip if null
        if ($fkValue === null) {
            return;
        }

        try {
            $this->connect();
            if (!$this->pdo) {
                return;
            }

            // Determine relationship type
            // If this table has the foreign key, it's a belongsTo relationship
            // We need to check if the referenced table has a reverse foreign key
            $relationshipType = $this->determineRelationshipType($referencedTable, $this->tableName);

            if ($relationshipType === 'belongsTo') {
                // Load parent record
                $stmt = $this->pdo->prepare(
                    "SELECT * FROM `{$referencedTable}` WHERE `{$referencedColumn}` = ? LIMIT 1"
                );
                $stmt->execute([$fkValue]);
                $relatedRecord = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($relatedRecord) {
                    // Store under alias if provided, otherwise under relationship name (backward compat)
                    $record[$relationshipName] = $relatedRecord;
                    
                    // If using alias, remove FK column from results
                    if ($alias !== null && isset($record[$actualFkColumn])) {
                        unset($record[$actualFkColumn]);
                    }
                } else {
                    $record[$relationshipName] = null;
                    // If using alias, remove FK column from results even if relationship is null
                    if ($alias !== null && isset($record[$actualFkColumn])) {
                        unset($record[$actualFkColumn]);
                    }
                }
            } elseif ($relationshipType === 'hasMany') {
                // Load child records
                // Find the foreign key column in the referenced table that points back to this table
                $reverseFkColumn = $this->findReverseForeignKey($referencedTable, $this->tableName);
                
                if ($reverseFkColumn) {
                    $stmt = $this->pdo->prepare(
                        "SELECT * FROM `{$referencedTable}` WHERE `{$reverseFkColumn}` = ?"
                    );
                    $stmt->execute([$record['id'] ?? null]);
                    $relatedRecords = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    $record[$relationshipName] = $relatedRecords;
                    
                    // For hasMany, we typically don't remove the FK column as it's not a direct FK
                    // But if alias is provided and it matches a column, we can remove it
                    if ($alias !== null && isset($record[$actualFkColumn]) && $actualFkColumn !== 'id') {
                        unset($record[$actualFkColumn]);
                    }
                }
            }
        } catch (\PDOException $e) {
            // Silently fail - relationship loading is optional
            error_log("Error loading relationship '{$relationshipName}': " . $e->getMessage());
        }
    }

    /**
     * Determine relationship type between two tables
     * 
     * @param string $referencedTable The table we're trying to load from
     * @param string $currentTable The current table (this table)
     * @return string Relationship type ('belongsTo' or 'hasMany')
     */
    private function determineRelationshipType(string $referencedTable, string $currentTable): string
    {
        try {
            $this->connect();
            if (!$this->pdo) {
                return 'belongsTo'; // Default fallback
            }

            // Check if the referenced table has a foreign key pointing back to current table
            // If yes, it's a hasMany relationship (referenced table has FK to current table)
            // If no, it's a belongsTo relationship (current table has FK to referenced table)
            $stmt = $this->pdo->prepare("
                SELECT COLUMN_NAME 
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = :referencedTable 
                AND REFERENCED_TABLE_NAME = :currentTable
                LIMIT 1
            ");
            $stmt->execute([
                'referencedTable' => $referencedTable,
                'currentTable' => $currentTable
            ]);
            
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            // If referenced table has FK pointing to current table, it's hasMany
            return $result ? 'hasMany' : 'belongsTo';
        } catch (\PDOException $e) {
            // Default to belongsTo on error
            return 'belongsTo';
        }
    }

    /**
     * Find table that has a foreign key column pointing to the current table
     * Used for detecting hasMany relationships
     * 
     * @param string $fkColumnName The foreign key column name to search for
     * @param string $currentTable The current table name
     * @return string|null Table name or null if not found
     */
    private function findTableWithForeignKey(string $fkColumnName, string $currentTable): ?string
    {
        try {
            $this->connect();
            if (!$this->pdo) {
                return null;
            }

            $stmt = $this->pdo->prepare("
                SELECT TABLE_NAME 
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND COLUMN_NAME = :fkColumnName 
                AND REFERENCED_TABLE_NAME = :currentTable
                LIMIT 1
            ");
            $stmt->execute([
                'fkColumnName' => $fkColumnName,
                'currentTable' => $currentTable
            ]);
            
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result ? $result['TABLE_NAME'] : null;
        } catch (\PDOException $e) {
            return null;
        }
    }

    /**
     * Load a hasMany relationship (child records)
     * 
     * @param array &$record The record to load the relationship for
     * @param string $tableName The table name containing the FK (e.g., 'Comments')
     * @param string $fkColumn The foreign key column name in that table (e.g., 'post_id')
     * @param string $alias The alias to store the relationship under
     */
    private function loadHasManyRelationship(array &$record, string $tableName, string $fkColumn, string $alias): void
    {
        try {
            $this->connect();
            if (!$this->pdo) {
                return;
            }

            if (!isset($record['id'])) {
                return;
            }

            // Load all records from the table where fkColumn = current record's id
            $stmt = $this->pdo->prepare(
                "SELECT * FROM `{$tableName}` WHERE `{$fkColumn}` = ?"
            );
            $stmt->execute([$record['id']]);
            $relatedRecords = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $record[$alias] = $relatedRecords;
        } catch (\PDOException $e) {
            // Silently fail - relationship loading is optional
            error_log("Error loading hasMany relationship '{$alias}': " . $e->getMessage());
            $record[$alias] = [];
            $record[$alias . '_count'] = 0;
        }
    }

    /**
     * Find reverse foreign key column in a table that references another table
     * 
     * @param string $tableName Table to search in
     * @param string $referencedTable Table being referenced
     * @return string|null Column name or null if not found
     */
    private function findReverseForeignKey(string $tableName, string $referencedTable): ?string
    {
        try {
            $this->connect();
            if (!$this->pdo) {
                return null;
            }

            $stmt = $this->pdo->prepare("
                SELECT COLUMN_NAME 
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = :tableName 
                AND REFERENCED_TABLE_NAME = :referencedTable
                LIMIT 1
            ");
            $stmt->execute([
                'tableName' => $tableName,
                'referencedTable' => $referencedTable
            ]);
            
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result ? $result['COLUMN_NAME'] : null;
        } catch (\PDOException $e) {
            return null;
        }
    }

    /**
     * Load a specific relationship
     * 
     * @param string $relationshipName Relationship name (column name)
     * @param mixed $fkValue Foreign key value
     * @return array|null Related record(s) or null
     */
    public function loadRelationship(string $relationshipName, $fkValue)
    {
        $metadata = $this->getRelationshipMetadata($relationshipName);
        
        if (!$metadata) {
            return null;
        }

        $referencedTable = $metadata['referenced_table'];
        $referencedColumn = $metadata['referenced_column'];

        try {
            $this->connect();
            if (!$this->pdo) {
                return null;
            }

            $stmt = $this->pdo->prepare(
                "SELECT * FROM `{$referencedTable}` WHERE `{$referencedColumn}` = ? LIMIT 1"
            );
            $stmt->execute([$fkValue]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\PDOException $e) {
            return null;
        }
    }

    public function findAll(Int $page = 1, Int $limit = 5)
    {
        $n = $this->connect();
        if (!$this->pdo) {
            return $n;
        }
        try {

            $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName}");
            $stmt->execute();
            $this->results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return $this;
        } catch (\PDOException $e) {
            return 'errror' . $e->getMessage();
        }
    }

    public function paginate(Int $page = 1, Int $limit = 20)
    {
        if (!$this->results) {
            return ['results' => [], 'totalPages' => 0, 'page' => 1, 'limit' => $limit, 'totalItems' => 0];
        }

        $total = ceil(count($this->results) / $limit);
        $offset = ($page - 1) * $limit;
        $paginatedResults = array_slice($this->results, $offset, $limit);

        return [
            'results' => $paginatedResults,
            'totalPages' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalItems' => count($this->results)
        ];
    }


    public function handleFileUploads($data)
    {
        $outP = null;
        $uploadDir = rtrim(ProjectPath::resolve('uploads'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        // Create the uploads directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Loop through the file fields
        foreach ($this->fileFields as $fileField) {
            // Check if the file field exists and there was no upload error
            if (isset($_FILES[$fileField]) && $_FILES[$fileField]['error'] !== UPLOAD_ERR_NO_FILE) {
                // Continue only if no error occurred with the file upload
                if ($_FILES[$fileField]['error'] === UPLOAD_ERR_OK) {
                    $originalFilename = basename($_FILES[$fileField]['name']);
                    $pathinfo = pathinfo($originalFilename);
                    $extension = $pathinfo['extension'];

                    // Generate a unique ID for the file
                    $uniqueId = uniqid('', true);
                    $filename = $uniqueId . '.' . $extension;

                    $targetFilePath = $uploadDir . $filename;

                    // Move the uploaded file to the target directory
                    if (move_uploaded_file($_FILES[$fileField]['tmp_name'], $targetFilePath)) {
                        // Save the filename in the $data array for future use (e.g., storing in the database)
                        $data[$fileField] = $filename;
                        $outP = $data;
                    } else {
                        return "Error uploading file: " . $_FILES[$fileField]['name'];
                    }
                } else {
                    // Handle different file upload errors (optional)
                    return "File upload error for field: " . $fileField;
                }
            }
        }

        // Return the updated $data array or the original data if no files were uploaded
        return $outP ? $outP : $data;
    }


    public function uploadHelper(array $data)
    {
        $uploadDir = rtrim(ProjectPath::resolve('uploads'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $filenames = [];
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        foreach ($this->fileFields as $fileField) {
            if (isset($_FILES[$fileField]) && $_FILES[$fileField]['error'] === UPLOAD_ERR_OK) {
                $originalFilename = basename($_FILES[$fileField]['name']);
                $pathinfo = pathinfo($originalFilename);
                $extension = $pathinfo['extension'];

                // Generate a unique ID and create the new filename
                $uniqueId = uniqid('', true); // Generate a unique ID
                $filename = $uniqueId . '.' . $extension; // Append the file extension to the unique ID

                $targetFilePath = $uploadDir . $filename;

                if (move_uploaded_file($_FILES[$fileField]['tmp_name'], $targetFilePath)) {
                    $filenames[$fileField] = $filename; // Save only the filename to the database
                } else {
                    return "Error uploading file: " . $_FILES[$fileField]['name'];
                }
            }
        }
        return $filenames;
    }

    /**
     * Validate foreign key values exist in referenced tables
     * 
     * @param array $data Data containing foreign key values
     * @param bool $isUpdate Whether this is an update operation
     * @throws \InvalidArgumentException if foreign key validation fails
     */
    private function validateForeignKeys(array $data, bool $isUpdate = false): void
    {
        // Check if foreign key validation is enabled (can be disabled via env var)
        $validateFks = filter_var($_ENV['REUT_VALIDATE_FOREIGN_KEYS'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
        if (!$validateFks) {
            return; // Skip validation if disabled
        }

        $this->connect();
        if (!$this->pdo) {
            throw new \RuntimeException('Database connection failed');
        }

        foreach ($this->foreignKeys as $fk) {
            $column = $fk['column'];
            $referencedTable = $fk['referenced_table'];
            $referencedColumn = $fk['referenced_column'];
            
            // Only validate if the foreign key column is present in data
            if (!isset($data[$column])) {
                // For updates, if column is not provided, skip validation
                if ($isUpdate) {
                    continue;
                }
                // For inserts, if column is nullable, skip validation
                if (isset($this->columns[$column]) && 
                    method_exists($this->columns[$column], 'isNullable') && 
                    $this->columns[$column]->isNullable()) {
                    continue;
                }
            }
            
            $fkValue = $data[$column] ?? null;
            
            // Skip validation if value is null and column allows null
            if ($fkValue === null) {
                if (isset($this->columns[$column]) && 
                    method_exists($this->columns[$column], 'isNullable') && 
                    $this->columns[$column]->isNullable()) {
                    continue;
                }
            }
            
            // Validate foreign key value exists
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT COUNT(*) as count FROM `{$referencedTable}` WHERE `{$referencedColumn}` = ?"
                );
                $stmt->execute([$fkValue]);
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($result && (int)$result['count'] === 0) {
                    throw new \InvalidArgumentException(
                        "Foreign key violation: {$column} value '{$fkValue}' does not exist in table '{$referencedTable}'"
                    );
                }
            } catch (\PDOException $e) {
                // If table doesn't exist, that's a different error
                if (strpos($e->getMessage(), "doesn't exist") !== false) {
                    throw new \InvalidArgumentException(
                        "Referenced table '{$referencedTable}' does not exist. Please run migrations first."
                    );
                }
                throw new \InvalidArgumentException(
                    "Error validating foreign key '{$column}': " . $e->getMessage()
                );
            }
        }
    }

    /**
     * Validate required fields based on column definitions
     * 
     * @param array $data Data to validate
     * @param bool $isUpdate Whether this is an update operation
     * @throws \InvalidArgumentException if required fields are missing
     */
    private function validateRequiredFields(array $data, bool $isUpdate = false): void
    {
        // Always check for empty data
        if (empty($data)) {
            $operation = $isUpdate ? 'update' : 'insert';
            throw new \InvalidArgumentException("Data array cannot be empty for {$operation} operation");
        }

        // Check if strictRequiredValidation property exists (from core)
        $strictValidation = property_exists($this, 'strictRequiredValidation') ? $this->strictRequiredValidation : false;

        // If strict validation is enabled OR this is an insert (not update), validate all required fields
        if ($strictValidation || !$isUpdate) {
            foreach ($this->columns as $columnName => $columnType) {
                // Check if column is not nullable (required)
                if (method_exists($columnType, 'isNullable') && !$columnType->isNullable()) {
                    if (!isset($data[$columnName])) {
                        throw new \InvalidArgumentException("Required field missing: {$columnName}");
                    }
                }
            }
        }
        // If strict validation is false AND this is an update, allow partial updates (skip validation)
    }

    /**
     * Validate SQL identifier (table/column name) to prevent SQL injection
     * 
     * @param string $identifier The identifier to validate
     * @throws \InvalidArgumentException if identifier is invalid
     */
    private function validateIdentifier(string $identifier): void
    {
        // Valid SQL identifiers: start with letter/underscore, followed by letters, numbers, underscores
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException("Invalid SQL identifier: {$identifier}");
        }
    }

    public function findOne(array $criteria)
    {
        $this->connect();
        if (!$this->pdo) {
            echo "Database connection failed";
            return false;
        }

        try {
            // Construct the WHERE clause from the criteria array
            $where = implode(" AND ", array_map(function ($key) {
                return "$key = ?";
            }, array_keys($criteria)));

            // Prepare the SQL statement
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE $where LIMIT 1");

            // Execute the statement with the criteria values
            $stmt->execute(array_values($criteria));

            // Fetch and return the result
            $this->results = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $this;
        } catch (\PDOException $e) {
            return $e->getMessage();
        }
    }



    public function addOne(array $data)
    {
        // Validate required fields (always strict for inserts)
        $this->validateRequiredFields($data, false);

        // Validate foreign keys
        $this->validateForeignKeys($data, false);

        // Validate identifiers in data keys
        foreach (array_keys($data) as $key) {
            $this->validateIdentifier($key);
        }

        $n = $this->connect();

        if (!$this->pdo) {
            //echo "Database connection failed";
            return $n;
        }

        // Check if files are present in the $data array
        $hasFiles = false;
        foreach ($_FILES as $fileKey => $fileValue) {
            if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                $hasFiles = true;
                break;
            }
        }

        // If files exist in the posted data, handle file uploads
        if ($hasFiles) {
            $fileUpload = $this->handleFileUploads($data);
            if ($fileUpload == null) {
                return false;  // Return false if file upload fails
            } else {
                $data = $fileUpload;  // Merge file data with the posted data
            }
        }

        try {
            // Prepare and execute the INSERT query
            error_log(json_encode($data));
            $keys = implode(", ", array_keys($data));
            $placeholders = implode(", ", array_fill(0, count($data), "?"));
            $stmt = $this->pdo->prepare("INSERT INTO {$this->tableName} ($keys) VALUES ($placeholders)");

            return $stmt->execute(array_values($data));
        } catch (\PDOException $e) {
            return $e->getMessage();
        }
    }


    public function addMany(array $data)
    {
        $this->connect();
        if (!$this->pdo) {
            echo "Database connection failed";
            return false;
        }

        try {


            $keys = implode(", ", array_keys($data[0]));
            $placeholders = implode(", ", array_fill(0, count($data[0]), "?"));
            $stmt = $this->pdo->prepare("INSERT INTO {$this->tableName} ($keys) VALUES ($placeholders)");

            try {
                $this->pdo->beginTransaction();
                foreach ($data as $row) {
                    $stmt->execute(array_values($row));
                }
                $this->pdo->commit();
                return true;
            } catch (\PDOException $e) {
                $this->pdo->rollBack();
                echo "Failed to add records: " . $e->getMessage();
                return false;
            }
        } catch (\PDOException $e) {
            return $e->getMessage();
        }
    }

    public function update(array $dataToUpdate, array $updateCondition)
    {
        // Validate update condition is not empty
        if (empty($updateCondition)) {
            throw new \InvalidArgumentException("Update condition cannot be empty");
        }

        // Validate required fields (respects strictRequiredValidation setting)
        $this->validateRequiredFields($dataToUpdate, true);

        // Validate foreign keys
        $this->validateForeignKeys($dataToUpdate, true);

        // Validate identifiers in data and condition keys
        foreach (array_keys($dataToUpdate) as $key) {
            $this->validateIdentifier($key);
        }
        foreach (array_keys($updateCondition) as $key) {
            $this->validateIdentifier($key);
        }

        $this->connect();
        if (!$this->pdo) {
            echo "Database connection failed";
            return false;
        }

        if (!empty($this->fileFields)) {
            $fileUploadError = $this->handleFileUploads($dataToUpdate);
            if ($fileUploadError) {
                return $fileUploadError;
            }
        }

        try {

            $set = implode(", ", array_map(fn($key) => "$key = ?", array_keys($dataToUpdate)));
            $where = implode(" AND ", array_map(fn($key) => "$key = ?", array_keys($updateCondition)));
            $stmt = $this->pdo->prepare("UPDATE {$this->tableName} SET $set WHERE $where");
            $outp = $stmt->execute(array_merge(array_values($dataToUpdate), array_values($updateCondition)));
            return $outp;
        } catch (\PDOException $e) {
            return $e->getMessage();
        }
    }

    public function updateMany(array $data, array $conditions)
    {
        $this->connect();
        if (!$this->pdo) {
            echo "Database connection failed";
            return false;
            //exit();
        }

        try {
            $this->pdo->beginTransaction();
            foreach ($data as $index => $row) {
                $set = implode(", ", array_map(fn($key) => "$key = ?", array_keys($row)));
                $where = implode(" AND ", array_map(fn($key) => "$key = ?", array_keys($conditions[$index])));
                $stmt = $this->pdo->prepare("UPDATE {$this->tableName} SET $set WHERE $where");
                $stmt->execute(array_merge(array_values($row), array_values($conditions[$index])));
            }
            $this->pdo->commit();
            return true;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            echo "Failed to update records: " . $e->getMessage();
            return false;
        }
    }

    public function delete(array $condition)
    {
        $this->connect();
        if (!$this->pdo) {
            echo "Database connection failed";
            return false;
        }
        try {

            $where = implode(" AND ", array_map(fn($key) => "$key = ?", array_keys($condition)));
            $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE $where");
            return $stmt->execute(array_values($condition));
        } catch (\PDOException $e) {
            return $e->getMessage();
        }
    }

    public function deleteMany(array $conditions)
    {
        $this->connect();
        if (!$this->pdo) {
            echo "Database connection failed";
            return false;
        }
        try {

            try {
                $this->pdo->beginTransaction();
                foreach ($conditions as $condition) {
                    $where = implode(" AND ", array_map(fn($key) => "$key = ?", array_keys($condition)));
                    $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE $where");
                    $stmt->execute(array_values($condition));
                }
                $this->pdo->commit();
                return true;
            } catch (\PDOException $e) {
                $this->pdo->rollBack();
                echo "Failed to delete records: " . $e->getMessage();
                return false;
            }
        } catch (\PDOException $e) {
            return $e->getMessage();
        }
    }

    public function search(array $criteria)
    {
        $this->connect();
        if (!$this->pdo) {
            echo "Database connection failed";
            return false;
        }
        try {

            $where = implode(" AND ", array_map(fn($key) => "$key LIKE ?", array_keys($criteria)));
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE $where");
            $stmt->execute(array_map(fn($value) => "%$value%", array_values($criteria)));
            $this->results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return $this;
        } catch (\PDOException $e) {
            return $e->getMessage();
        }
    }

    public function sqlQuery(String $query, array $params = [])
    {
        $this->connect();
        if (!$this->pdo) {
            echo "Database connection failed";
            return false;
        }

        try {

            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $this->results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return $this->results;
        } catch (\PDOException $e) {
            return $e->getMessage();
        }
    }

    public function tableExists(string $tableName): bool
    {
        // Ensure connection is established
        $this->connect();
        if (!$this->pdo) {
            throw new \RuntimeException('Database connection failed');
        }

        try {
            // Use proper SQL syntax for checking table existence
            $stmt = $this->pdo->prepare(
                'SELECT EXISTS (
                SELECT 1 
                FROM information_schema.tables 
                WHERE table_schema = ? 
                AND table_name = ?
            ) as table_exists'
            );

            $stmt->execute([$this->config['dbname'], $tableName]);

            // Fetch single value since we only need the EXISTS result
            $result = $stmt->fetchColumn();

            // Convert to boolean
            return (bool) $result;
        } catch (\PDOException $e) {
            // Log the error in a production environment instead of echoing
            error_log('Table existence check failed: ' . $e->getMessage());
            return false;
        }
    }

    public function getTableSchema($tableName)
    {
        $stmt = $this->pdo->prepare("DESCRIBE $tableName");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function removeColumn($tableName, $columnName)
    {
        $stmt = $this->pdo->prepare("ALTER TABLE $tableName DROP COLUMN $columnName");
        // echo $stmt>;
        return $stmt->execute();
    }

    public function updateColumnType($tableName, $columnName, $newColumnType)
    {
        $stmt = $this->pdo->prepare("ALTER TABLE $tableName MODIFY $columnName $newColumnType");
        return $stmt->execute();
    }

    public function getDropColumnSQL(string $column): string
    {
        return "ALTER TABLE " . $this->tableName . " DROP COLUMN $column";
    }

    public function dropColumn(string $tableName, string $column): bool
    {
        $sql = "ALTER TABLE " . $tableName . " DROP COLUMN $column";
        return $this->sqlQuery($sql) !== false;
    }

    public function addColumnTable($tableName, $columnName, $columnType)
    {
        // Sanitize table and column names (ensure they are valid SQL identifiers)
        $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
        $columnName = preg_replace('/[^a-zA-Z0-9_]/', '', $columnName);

        // Check if the column already exists in the table
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) 
         FROM INFORMATION_SCHEMA.COLUMNS 
         WHERE TABLE_NAME = :tableName 
           AND COLUMN_NAME = :columnName 
           AND TABLE_SCHEMA = DATABASE()"
        );

        // Bind parameters
        $stmt->bindParam(':tableName', $tableName);
        $stmt->bindParam(':columnName', $columnName);
        $stmt->execute();

        // Get the result
        $columnExists = $stmt->fetchColumn();

        if ($columnExists == 0) {
            // Directly inject the column name and type (since placeholders cannot be used for SQL structure)
            $sql = "ALTER TABLE $tableName ADD $columnName $columnType";
            $stmt2 = $this->pdo->prepare($sql);
            return $stmt2->execute();
        } else {
            // Return false or a custom message indicating that the column already exists
            return false;
        }
    }


    public function getTablesList()
    {
        $this->connect();
        if (!$this->pdo) {
            echo "Database connection failed";
            return false;
        }
        try {

            $tables = [];
            $stmt = $this->pdo->prepare("SHOW TABLES");
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $tables[] = $row['Tables_in_' . $this->config['dbname']];
            }
            return  $tables;
        } catch (\PDOException $e) {
            return $e->getMessage();
        }
    }

    public function dropTable($tableName)
    {
        $this->connect();
        if (!$this->pdo) {
            echo "Database connection failed";
            return false;
        }
        try {
            $stmt = $this->pdo->prepare("DROP TABLE IF EXISTS $tableName");
            return $stmt->execute();
        } catch (\PDOException $e) {
            return $e->getMessage();
        }
    }

    public function getColumns($tableName)
    {
        $this->connect();
        if (!$this->pdo) {
            echo "Database connection failed";
            return false;
        }
        try {

            $columns = [];
            $stmt = $this->pdo->prepare("SHOW COLUMNS FROM $tableName");
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $columns[] = $row['Field'];
            }
            return $columns;
        } catch (\PDOException $e) {
            return $e->getMessage();
        }
    }

    public function getColumnType($tableName, $columnName)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = :tableName AND COLUMN_NAME = :columnName");
            $stmt->bindParam(':tableName', $tableName);
            $stmt->bindParam(':columnName', $columnName);
            $stmt->execute();

            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($result && isset($result['DATA_TYPE'])) {
                return $result['DATA_TYPE'];
            } else {
                throw new \Exception("Column '$columnName' not found in tableName '$tableName'.");
            }
        } catch (\PDOException $e) {
            throw new \Exception("Error getting column type: " . $e->getMessage());
        }
    }
}

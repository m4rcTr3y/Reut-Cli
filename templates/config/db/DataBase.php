<?php

declare(strict_types=1);

namespace Reut\DB;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Psr\Log\LoggerInterface;
use Reut\DB\Exceptions\ConnectionError;
use Reut\DB\Exceptions\DatabaseConnectionException;
use Reut\DB\Exceptions\DatabaseQueryException;
use Reut\DB\Types\ColumnType;

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
    
    // File upload security settings
    private array $allowedMimeTypes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'text/plain', 'text/csv'
    ];
    private int $maxFileSize = 5242880; // 5MB

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

    /**
     * Sanitize SQL identifier (table/column name)
     * Only allows alphanumeric characters and underscores, must start with letter or underscore
     * 
     * @param string $identifier The identifier to sanitize
     * @return string The sanitized identifier
     * @throws \InvalidArgumentException If identifier is invalid
     */
    protected function sanitizeIdentifier(string $identifier): string
    {
        // Remove any whitespace
        $identifier = trim($identifier);
        
        // Validate format: must start with letter or underscore, followed by alphanumeric/underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException(
                "Invalid SQL identifier: '{$identifier}'. Only alphanumeric characters and underscores are allowed, and it must start with a letter or underscore."
            );
        }
        
        return $identifier;
    }

    /**
     * Sanitize multiple identifiers
     * 
     * @param array $identifiers Array of identifiers to sanitize
     * @return array Array of sanitized identifiers
     * @throws \InvalidArgumentException If any identifier is invalid
     */
    protected function sanitizeIdentifiers(array $identifiers): array
    {
        return array_map([$this, 'sanitizeIdentifier'], $identifiers);
    }

    /**
     * Ensure database connection is established
     * @throws DatabaseConnectionException
     */
    protected function ensureConnection(): void
    {
        if ($this->pdo === null) {
            try {
                $this->connect();
            } catch (DatabaseConnectionException | ConnectionError $e) {
                if ($e instanceof ConnectionError) {
                    // Convert legacy exception
                    throw new DatabaseConnectionException(
                        $e->getMessage(),
                        $e->getCode(),
                        $e,
                        $this->config
                    );
                }
                throw $e;
            }
        }

        if (!$this->pdo) {
            throw new DatabaseConnectionException(
                "Database connection failed: PDO instance is null",
                0,
                null,
                $this->config
            );
        }
    }

    /**
     * Validate data against column definitions
     * 
     * @param array $data Data to validate
     * @param bool $isUpdate Whether this is an update operation
     * @return array Validated data
     * @throws \InvalidArgumentException If validation fails
     */
    protected function validateData(array $data, bool $isUpdate = false): array
    {
        if (empty($data)) {
            throw new \InvalidArgumentException("Data array cannot be empty");
        }

        $validated = [];
        
        foreach ($data as $column => $value) {
            // Skip unknown columns in update mode
            if (!isset($this->columns[$column])) {
                if (!$isUpdate) {
                    throw new \InvalidArgumentException("Unknown column: {$column}");
                }
                continue;
            }

            // Basic validation - check for null values on non-nullable columns
            $columnType = $this->columns[$column];
            if (!$isUpdate && !$columnType->isNullable() && ($value === null || $value === '')) {
                throw new \InvalidArgumentException("Required field missing or empty: {$column}");
            }

            // String length validation (basic check)
            if (is_string($value) && strlen($value) > 65535) {
                throw new \InvalidArgumentException("Value too long for column: {$column}");
            }

            $validated[$column] = $value;
        }

        return $validated;
    }

    /**
     * Get allowed file extensions for a MIME type
     * 
     * @param string $mimeType MIME type
     * @return array Allowed extensions
     */
    private function getAllowedExtensions(string $mimeType): array
    {
        $map = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/webp' => ['webp'],
            'application/pdf' => ['pdf'],
            'text/plain' => ['txt'],
            'text/csv' => ['csv'],
        ];
        return $map[$mimeType] ?? [];
    }

    // todo: execute the connect function by default on call of the function

    /**
     * connect: connects to the dabase
     */
    public function connect()
    {
        // Don't reconnect if already connected
        if ($this->pdo !== null) {
            return true;
        }
        
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
        // Only connect if not already connected
        if (!$this->pdo) {
            try {
                $this->connect();
            } catch (DatabaseConnectionException $e) {
                error_log("Database connection failed: " . $e->getFormattedMessage());
                throw $e;
            } catch (ConnectionError $e) {
                // Legacy exception handling
                error_log("Database connection failed: " . $e->getMessage());
                throw $e;
            }
        }

        if (!$this->pdo) {
            throw new DatabaseConnectionException(
                "Database connection failed: PDO instance is null",
                0,
                null,
                $this->config
            );
        }

        try {
            $stmt = $this->pdo->prepare($query);
            $result = $stmt->execute($params);
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                throw new DatabaseQueryException(
                    "SQL execution failed: " . ($errorInfo[2] ?? 'Unknown error'),
                    (int)($errorInfo[0] ?? 0),
                    null,
                    $query,
                    $params,
                    $errorInfo
                );
            }
            return $result;
        } catch (\PDOException $e) {
            $errorInfo = $e->errorInfo ?? ['', $e->getCode(), $e->getMessage()];
            throw new DatabaseQueryException(
                "Database query failed: " . $e->getMessage(),
                (int)$e->getCode(),
                $e,
                $query,
                $params,
                $errorInfo
            );
        }
    }

    public function getAddColumnSQL(string $column, ColumnType $type): string
    {
        $column = $this->sanitizeIdentifier($column);
        $tableName = $this->sanitizeIdentifier($this->tableName);
        return "ALTER TABLE `{$tableName}` ADD `{$column}` " . $type->getSql();
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

        $tableName = $this->sanitizeIdentifier($this->tableName);
        $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (\n";
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

    public function findAll(int $page = 1, int $limit = 5): self
    {
        $this->ensureConnection();
        
        try {
            $tableName = $this->sanitizeIdentifier($this->tableName);
            $stmt = $this->pdo->prepare("SELECT * FROM `{$tableName}`");
            $stmt->execute();
            $this->results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return $this;
        } catch (\PDOException $e) {
            $errorInfo = $e->errorInfo ?? ['', $e->getCode(), $e->getMessage()];
            throw new DatabaseQueryException(
                "Failed to fetch records: " . $e->getMessage(),
                (int)$e->getCode(),
                $e,
                "SELECT * FROM `{$this->tableName}`",
                [],
                $errorInfo
            );
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
        $uploadDir = dirname(__DIR__) . '/../uploads/';

        // Create directory with secure permissions
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0750, true);
        }

        // Loop through the file fields
        foreach ($this->fileFields as $fileField) {
            // Check if the file field exists and there was no upload error
            if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($_FILES[$fileField]['error'] !== UPLOAD_ERR_OK) {
                throw new \RuntimeException(
                    "File upload error for field: {$fileField}. Error code: " . $_FILES[$fileField]['error']
                );
            }

            // Validate file size
            if ($_FILES[$fileField]['size'] > $this->maxFileSize) {
                throw new \RuntimeException(
                    "File too large for field: {$fileField}. Maximum size: " . 
                    round($this->maxFileSize / 1024 / 1024, 2) . "MB"
                );
            }

            // Validate MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $_FILES[$fileField]['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $this->allowedMimeTypes, true)) {
                throw new \RuntimeException(
                    "Invalid file type for field: {$fileField}. Allowed types: " . 
                    implode(', ', $this->allowedMimeTypes)
                );
            }

            // Generate secure filename
            $originalFilename = basename($_FILES[$fileField]['name']);
            $pathinfo = pathinfo($originalFilename);
            $extension = strtolower($pathinfo['extension'] ?? '');
            
            // Validate extension matches MIME type
            $allowedExtensions = $this->getAllowedExtensions($mimeType);
            if (!in_array($extension, $allowedExtensions, true)) {
                throw new \RuntimeException("File extension mismatch for field: {$fileField}");
            }

            // Generate cryptographically secure filename
            $uniqueId = bin2hex(random_bytes(16));
            $filename = $uniqueId . '.' . $extension;
            $targetFilePath = $uploadDir . $filename;

            // Move uploaded file
            if (!move_uploaded_file($_FILES[$fileField]['tmp_name'], $targetFilePath)) {
                throw new \RuntimeException("Error uploading file: " . $_FILES[$fileField]['name']);
            }

            // Set secure permissions
            chmod($targetFilePath, 0640);
            $data[$fileField] = $filename;
            $outP = $data;
        }

        // Return the updated $data array or the original data if no files were uploaded
        return $outP ?: $data;
    }


    public function uploadHelper(array $data): array
    {
        $uploadDir = dirname(__DIR__) . '/../uploads/';
        $filenames = [];
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0750, true);
        }

        foreach ($this->fileFields as $fileField) {
            if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] !== UPLOAD_ERR_OK) {
                continue;
            }

            // Validate file size
            if ($_FILES[$fileField]['size'] > $this->maxFileSize) {
                throw new \RuntimeException(
                    "File too large for field: {$fileField}. Maximum size: " . 
                    round($this->maxFileSize / 1024 / 1024, 2) . "MB"
                );
            }

            // Validate MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $_FILES[$fileField]['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $this->allowedMimeTypes, true)) {
                throw new \RuntimeException(
                    "Invalid file type for field: {$fileField}. Allowed types: " . 
                    implode(', ', $this->allowedMimeTypes)
                );
            }

            $originalFilename = basename($_FILES[$fileField]['name']);
            $pathinfo = pathinfo($originalFilename);
            $extension = strtolower($pathinfo['extension'] ?? '');
            
            // Validate extension matches MIME type
            $allowedExtensions = $this->getAllowedExtensions($mimeType);
            if (!in_array($extension, $allowedExtensions, true)) {
                throw new \RuntimeException("File extension mismatch for field: {$fileField}");
            }

            // Generate cryptographically secure filename
            $uniqueId = bin2hex(random_bytes(16));
            $filename = $uniqueId . '.' . $extension;
            $targetFilePath = $uploadDir . $filename;

            if (move_uploaded_file($_FILES[$fileField]['tmp_name'], $targetFilePath)) {
                chmod($targetFilePath, 0640);
                $filenames[$fileField] = $filename;
            } else {
                throw new \RuntimeException("Error uploading file: " . $_FILES[$fileField]['name']);
            }
        }
        
        return $filenames;
    }



    public function findOne(array $criteria): self
    {
        $this->ensureConnection();
        
        if (empty($criteria)) {
            throw new \InvalidArgumentException("Criteria cannot be empty");
        }

        try {
            // Sanitize column names
            $criteriaKeys = $this->sanitizeIdentifiers(array_keys($criteria));
            $where = implode(" AND ", array_map(fn($key) => "`{$key}` = ?", $criteriaKeys));

            $tableName = $this->sanitizeIdentifier($this->tableName);
            $stmt = $this->pdo->prepare("SELECT * FROM `{$tableName}` WHERE {$where} LIMIT 1");

            $stmt->execute(array_values($criteria));
            $this->results = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $this;
        } catch (\PDOException $e) {
            $errorInfo = $e->errorInfo ?? ['', $e->getCode(), $e->getMessage()];
            throw new DatabaseQueryException(
                "Failed to find record: " . $e->getMessage(),
                (int)$e->getCode(),
                $e,
                "SELECT * FROM `{$this->tableName}` WHERE ...",
                $criteria,
                $errorInfo
            );
        }
    }



    public function addOne(array $data): bool
    {
        $this->ensureConnection();

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
            try {
                $fileUpload = $this->handleFileUploads($data);
                if ($fileUpload === null) {
                    return false;  // Return false if file upload fails
                } else {
                    $data = $fileUpload;  // Merge file data with the posted data
                }
            } catch (\Exception $e) {
                error_log("File upload error: " . $e->getMessage());
                return false;
            }
        }

        // Validate input data
        try {
            $data = $this->validateData($data, false);
        } catch (\InvalidArgumentException $e) {
            throw $e;
        }

        try {
            // Sanitize column names to prevent SQL injection
            $keys = array_keys($data);
            $sanitizedKeys = $this->sanitizeIdentifiers($keys);
            
            // Build SQL with sanitized column names
            $keysStr = implode(", ", array_map(fn($k) => "`{$k}`", $sanitizedKeys));
            $placeholders = implode(", ", array_fill(0, count($data), "?"));
            
            $tableName = $this->sanitizeIdentifier($this->tableName);
            $stmt = $this->pdo->prepare("INSERT INTO `{$tableName}` ({$keysStr}) VALUES ({$placeholders})");
            
            return $stmt->execute(array_values($data));
        } catch (\PDOException $e) {
            $errorInfo = $e->errorInfo ?? ['', $e->getCode(), $e->getMessage()];
            throw new DatabaseQueryException(
                "Failed to insert record: " . $e->getMessage(),
                (int)$e->getCode(),
                $e,
                "INSERT INTO `{$this->tableName}` ...",
                $data,
                $errorInfo
            );
        }
    }


    public function addMany(array $data): bool
    {
        $this->ensureConnection();
        
        if (empty($data) || !is_array($data[0] ?? null)) {
            throw new \InvalidArgumentException("Data must be a non-empty array of arrays");
        }

        try {
            // Sanitize column names from first row
            $keys = array_keys($data[0]);
            $sanitizedKeys = $this->sanitizeIdentifiers($keys);
            $keysStr = implode(", ", array_map(fn($k) => "`{$k}`", $sanitizedKeys));
            $placeholders = implode(", ", array_fill(0, count($data[0]), "?"));
            
            $tableName = $this->sanitizeIdentifier($this->tableName);
            $stmt = $this->pdo->prepare("INSERT INTO `{$tableName}` ({$keysStr}) VALUES ({$placeholders})");

            try {
                $this->pdo->beginTransaction();
                foreach ($data as $row) {
                    // Ensure all rows have the same keys
                    $rowValues = [];
                    foreach ($sanitizedKeys as $key) {
                        $rowValues[] = $row[$key] ?? null;
                    }
                    $stmt->execute($rowValues);
                }
                $this->pdo->commit();
                return true;
            } catch (\PDOException $e) {
                $this->pdo->rollBack();
                $errorInfo = $e->errorInfo ?? ['', $e->getCode(), $e->getMessage()];
                throw new DatabaseQueryException(
                    "Failed to add records: " . $e->getMessage(),
                    (int)$e->getCode(),
                    $e,
                    "INSERT INTO `{$tableName}` ...",
                    $data,
                    $errorInfo
                );
            }
        } catch (\PDOException $e) {
            $errorInfo = $e->errorInfo ?? ['', $e->getCode(), $e->getMessage()];
            throw new DatabaseQueryException(
                "Failed to prepare insert statement: " . $e->getMessage(),
                (int)$e->getCode(),
                $e,
                "INSERT INTO `{$this->tableName}` ...",
                [],
                $errorInfo
            );
        }
    }

    public function update(array $dataToUpdate, array $updateCondition): bool
    {
        $this->ensureConnection();
        
        if (empty($dataToUpdate)) {
            throw new \InvalidArgumentException("Data to update cannot be empty");
        }
        
        if (empty($updateCondition)) {
            throw new \InvalidArgumentException("Update condition cannot be empty");
        }

        if (!empty($this->fileFields)) {
            try {
                $fileUploadResult = $this->handleFileUploads($dataToUpdate);
                if ($fileUploadResult && is_string($fileUploadResult)) {
                    // File upload returned an error string (legacy behavior)
                    throw new \RuntimeException($fileUploadResult);
                }
                if ($fileUploadResult) {
                    $dataToUpdate = $fileUploadResult;
                }
            } catch (\Exception $e) {
                throw new \RuntimeException("File upload failed: " . $e->getMessage(), 0, $e);
            }
        }

        // Validate input data
        try {
            $dataToUpdate = $this->validateData($dataToUpdate, true);
        } catch (\InvalidArgumentException $e) {
            throw $e;
        }

        try {
            // Sanitize column names
            $updateKeys = $this->sanitizeIdentifiers(array_keys($dataToUpdate));
            $conditionKeys = $this->sanitizeIdentifiers(array_keys($updateCondition));
            
            $set = implode(", ", array_map(fn($key) => "`{$key}` = ?", $updateKeys));
            $where = implode(" AND ", array_map(fn($key) => "`{$key}` = ?", $conditionKeys));
            
            $tableName = $this->sanitizeIdentifier($this->tableName);
            $stmt = $this->pdo->prepare("UPDATE `{$tableName}` SET {$set} WHERE {$where}");
            
            $outp = $stmt->execute(array_merge(
                array_values($dataToUpdate),
                array_values($updateCondition)
            ));
            
            return $outp;
        } catch (\PDOException $e) {
            $errorInfo = $e->errorInfo ?? ['', $e->getCode(), $e->getMessage()];
            throw new DatabaseQueryException(
                "Failed to update record: " . $e->getMessage(),
                (int)$e->getCode(),
                $e,
                "UPDATE `{$this->tableName}` SET ...",
                array_merge($dataToUpdate, $updateCondition),
                $errorInfo
            );
        }
    }

    public function updateMany(array $data, array $conditions): bool
    {
        $this->ensureConnection();
        
        if (empty($data) || empty($conditions)) {
            throw new \InvalidArgumentException("Data and conditions cannot be empty");
        }
        
        if (count($data) !== count($conditions)) {
            throw new \InvalidArgumentException("Data and conditions arrays must have the same length");
        }

        try {
            $this->pdo->beginTransaction();
            
            foreach ($data as $index => $row) {
                if (empty($row) || empty($conditions[$index])) {
                    throw new \InvalidArgumentException("Row and condition at index {$index} cannot be empty");
                }
                
                // Sanitize column names
                $updateKeys = $this->sanitizeIdentifiers(array_keys($row));
                $conditionKeys = $this->sanitizeIdentifiers(array_keys($conditions[$index]));
                
                $set = implode(", ", array_map(fn($key) => "`{$key}` = ?", $updateKeys));
                $where = implode(" AND ", array_map(fn($key) => "`{$key}` = ?", $conditionKeys));
                
                $tableName = $this->sanitizeIdentifier($this->tableName);
                $stmt = $this->pdo->prepare("UPDATE `{$tableName}` SET {$set} WHERE {$where}");
                $stmt->execute(array_merge(
                    array_values($row),
                    array_values($conditions[$index])
                ));
            }
            
            $this->pdo->commit();
            return true;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            $errorInfo = $e->errorInfo ?? ['', $e->getCode(), $e->getMessage()];
            throw new DatabaseQueryException(
                "Failed to update records: " . $e->getMessage(),
                (int)$e->getCode(),
                $e,
                "UPDATE `{$this->tableName}` SET ...",
                [],
                $errorInfo
            );
        }
    }

    public function delete(array $condition): bool
    {
        $this->ensureConnection();
        
        if (empty($condition)) {
            throw new \InvalidArgumentException("Delete condition cannot be empty");
        }
        
        try {
            // Sanitize column names
            $conditionKeys = $this->sanitizeIdentifiers(array_keys($condition));
            $where = implode(" AND ", array_map(fn($key) => "`{$key}` = ?", $conditionKeys));
            
            $tableName = $this->sanitizeIdentifier($this->tableName);
            $stmt = $this->pdo->prepare("DELETE FROM `{$tableName}` WHERE {$where}");
            
            return $stmt->execute(array_values($condition));
        } catch (\PDOException $e) {
            $errorInfo = $e->errorInfo ?? ['', $e->getCode(), $e->getMessage()];
            throw new DatabaseQueryException(
                "Failed to delete record: " . $e->getMessage(),
                (int)$e->getCode(),
                $e,
                "DELETE FROM `{$this->tableName}` WHERE ...",
                $condition,
                $errorInfo
            );
        }
    }

    public function deleteMany(array $conditions): bool
    {
        $this->ensureConnection();
        
        if (empty($conditions)) {
            throw new \InvalidArgumentException("Delete conditions cannot be empty");
        }
        
        try {
            $this->pdo->beginTransaction();
            
            foreach ($conditions as $condition) {
                if (empty($condition)) {
                    throw new \InvalidArgumentException("Delete condition cannot be empty");
                }
                
                // Sanitize column names
                $conditionKeys = $this->sanitizeIdentifiers(array_keys($condition));
                $where = implode(" AND ", array_map(fn($key) => "`{$key}` = ?", $conditionKeys));
                
                $tableName = $this->sanitizeIdentifier($this->tableName);
                $stmt = $this->pdo->prepare("DELETE FROM `{$tableName}` WHERE {$where}");
                $stmt->execute(array_values($condition));
            }
            
            $this->pdo->commit();
            return true;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            $errorInfo = $e->errorInfo ?? ['', $e->getCode(), $e->getMessage()];
            throw new DatabaseQueryException(
                "Failed to delete records: " . $e->getMessage(),
                (int)$e->getCode(),
                $e,
                "DELETE FROM `{$this->tableName}` WHERE ...",
                [],
                $errorInfo
            );
        }
    }

    public function search(array $criteria): self
    {
        $this->ensureConnection();
        
        if (empty($criteria)) {
            throw new \InvalidArgumentException("Search criteria cannot be empty");
        }
        
        try {
            // Sanitize column names
            $criteriaKeys = $this->sanitizeIdentifiers(array_keys($criteria));
            $where = implode(" AND ", array_map(fn($key) => "`{$key}` LIKE ?", $criteriaKeys));
            
            $tableName = $this->sanitizeIdentifier($this->tableName);
            $stmt = $this->pdo->prepare("SELECT * FROM `{$tableName}` WHERE {$where}");
            
            $searchValues = array_map(fn($value) => "%{$value}%", array_values($criteria));
            $stmt->execute($searchValues);
            
            $this->results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return $this;
        } catch (\PDOException $e) {
            $errorInfo = $e->errorInfo ?? ['', $e->getCode(), $e->getMessage()];
            throw new DatabaseQueryException(
                "Failed to search records: " . $e->getMessage(),
                (int)$e->getCode(),
                $e,
                "SELECT * FROM `{$this->tableName}` WHERE ...",
                $criteria,
                $errorInfo
            );
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

    public function getTableSchema(string $tableName): array
    {
        $this->ensureConnection();
        
        $tableName = $this->sanitizeIdentifier($tableName);
        
        try {
            $stmt = $this->pdo->prepare("DESCRIBE `{$tableName}`");
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\PDOException $e) {
            $errorInfo = $e->errorInfo ?? ['', $e->getCode(), $e->getMessage()];
            throw new DatabaseQueryException(
                "Failed to get table schema: " . $e->getMessage(),
                (int)$e->getCode(),
                $e,
                "DESCRIBE `{$tableName}`",
                [],
                $errorInfo
            );
        }
    }

    public function removeColumn(string $tableName, string $columnName): bool
    {
        $this->ensureConnection();
        
        $tableName = $this->sanitizeIdentifier($tableName);
        $columnName = $this->sanitizeIdentifier($columnName);
        
        try {
            $stmt = $this->pdo->prepare("ALTER TABLE `{$tableName}` DROP COLUMN `{$columnName}`");
            return $stmt->execute();
        } catch (\PDOException $e) {
            $errorInfo = $e->errorInfo ?? ['', $e->getCode(), $e->getMessage()];
            throw new DatabaseQueryException(
                "Failed to remove column: " . $e->getMessage(),
                (int)$e->getCode(),
                $e,
                "ALTER TABLE `{$tableName}` DROP COLUMN `{$columnName}`",
                [],
                $errorInfo
            );
        }
    }

    public function updateColumnType(string $tableName, string $columnName, string $newColumnType): bool
    {
        $this->ensureConnection();
        
        $tableName = $this->sanitizeIdentifier($tableName);
        $columnName = $this->sanitizeIdentifier($columnName);
        
        // Validate column type (basic check - you may want to expand this)
        if (!preg_match('/^[A-Za-z0-9_()\s,]+$/', $newColumnType)) {
            throw new \InvalidArgumentException("Invalid column type: {$newColumnType}");
        }
        
        try {
            $stmt = $this->pdo->prepare("ALTER TABLE `{$tableName}` MODIFY `{$columnName}` {$newColumnType}");
            return $stmt->execute();
        } catch (\PDOException $e) {
            $errorInfo = $e->errorInfo ?? ['', $e->getCode(), $e->getMessage()];
            throw new DatabaseQueryException(
                "Failed to update column type: " . $e->getMessage(),
                (int)$e->getCode(),
                $e,
                "ALTER TABLE `{$tableName}` MODIFY `{$columnName}` {$newColumnType}",
                [],
                $errorInfo
            );
        }
    }

    public function getDropColumnSQL(string $column): string
    {
        $column = $this->sanitizeIdentifier($column);
        $tableName = $this->sanitizeIdentifier($this->tableName);
        return "ALTER TABLE `{$tableName}` DROP COLUMN `{$column}`";
    }

    public function dropColumn(string $tableName, string $column): bool
    {
        $tableName = $this->sanitizeIdentifier($tableName);
        $column = $this->sanitizeIdentifier($column);
        $sql = $this->getDropColumnSQL($column);
        
        try {
            $this->execute($sql);
            return true;
        } catch (DatabaseQueryException $e) {
            throw $e;
        }
    }

    public function addColumnTable(string $tableName, string $columnName, string $columnType): bool
    {
        $this->ensureConnection();
        
        // Sanitize table and column names
        $tableName = $this->sanitizeIdentifier($tableName);
        $columnName = $this->sanitizeIdentifier($columnName);
        
        // Validate column type
        if (!preg_match('/^[A-Za-z0-9_()\s,]+$/', $columnType)) {
            throw new \InvalidArgumentException("Invalid column type: {$columnType}");
        }

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
            // Use sanitized identifiers in SQL
            $sql = "ALTER TABLE `{$tableName}` ADD `{$columnName}` {$columnType}";
            $stmt2 = $this->pdo->prepare($sql);
            return $stmt2->execute();
        } else {
            // Column already exists
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

    public function dropTable(string $tableName): bool
    {
        $this->ensureConnection();
        
        $tableName = $this->sanitizeIdentifier($tableName);
        
        try {
            $stmt = $this->pdo->prepare("DROP TABLE IF EXISTS `{$tableName}`");
            return $stmt->execute();
        } catch (\PDOException $e) {
            $errorInfo = $e->errorInfo ?? ['', $e->getCode(), $e->getMessage()];
            throw new DatabaseQueryException(
                "Failed to drop table: " . $e->getMessage(),
                (int)$e->getCode(),
                $e,
                "DROP TABLE IF EXISTS `{$tableName}`",
                [],
                $errorInfo
            );
        }
    }

    public function getColumns(string $tableName): array
    {
        $this->ensureConnection();
        
        $tableName = $this->sanitizeIdentifier($tableName);
        
        try {
            $columns = [];
            $stmt = $this->pdo->prepare("SHOW COLUMNS FROM `{$tableName}`");
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $columns[] = $row['Field'];
            }
            return $columns;
        } catch (\PDOException $e) {
            $errorInfo = $e->errorInfo ?? ['', $e->getCode(), $e->getMessage()];
            throw new DatabaseQueryException(
                "Failed to get columns: " . $e->getMessage(),
                (int)$e->getCode(),
                $e,
                "SHOW COLUMNS FROM `{$tableName}`",
                [],
                $errorInfo
            );
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

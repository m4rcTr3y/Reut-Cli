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
    public array $fileFieldTypes = [];
    public bool $strictRequiredValidation = false;

    public array $columns = [];
    public array $protectedColumns = ['created_at', 'updated_at'];
    protected array $foreignKeys = [];

    // Dangerous file extensions that should never be allowed
    private const DANGEROUS_EXTENSIONS = ['php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'phar', 'exe', 'sh', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'js', 'jsp', 'asp', 'aspx'];

    public function __construct(array $config, $columns = [], ?String $tableName = null, Bool $hasRelationships = false, $relationships = 0, array $fileFields = [], array $disabledRoutes = [], array $protectedColumns = ['created_at', 'updated_at'], ?bool $strictRequiredValidation = null, array $fileFieldTypes = [])
    {
        $this->config = $config;
        $this->tableName = $tableName;
        $this->hasRelationships = $hasRelationships;
        $this->columns = $columns ?? [];
        $this->relationships = $relationships;
        $this->disabledRoutes = $disabledRoutes;
        $this->fileFields = $fileFields;
        $this->protectedColumns = $protectedColumns;
        $this->fileFieldTypes = $fileFieldTypes;
        
        // Handle strictRequiredValidation: if null, read from env with default false
        $this->strictRequiredValidation = $strictRequiredValidation ?? 
            filter_var($_ENV['REUT_STRICT_REQUIRED_VALIDATION'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
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
            throw new DatabaseConnectionException(
                "Failed to connect to database: " . $e->getMessage(),
                (int)$e->getCode(),
                $e,
                $this->config
            );
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
                $errorMessage = $errorInfo[2] ?? 'Unknown error';
                throw new DatabaseQueryException(
                    "SQL execution failed: " . $errorMessage,
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

        // Create the uploads directory if it doesn't exist with secure permissions
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0750, true);
        }

        // Loop through the file fields
        foreach ($this->fileFields as $fileField) {
            // Check if the file field exists and there was no upload error
            if (isset($_FILES[$fileField]) && $_FILES[$fileField]['error'] !== UPLOAD_ERR_NO_FILE) {
                // Continue only if no error occurred with the file upload
                if ($_FILES[$fileField]['error'] === UPLOAD_ERR_OK) {
                    $originalFilename = basename($_FILES[$fileField]['name']);
                    $pathinfo = pathinfo($originalFilename);
                    $extension = strtolower($pathinfo['extension'] ?? '');
                    $mimeType = $_FILES[$fileField]['type'];
                    $fileSize = $_FILES[$fileField]['size'];
                    $tmpName = $_FILES[$fileField]['tmp_name'];

                    // Validate file type if fileFieldTypes is configured for this field
                    if (isset($this->fileFieldTypes[$fileField]) && !empty($this->fileFieldTypes[$fileField])) {
                        $this->validateFileType($fileField, $extension, $mimeType);
                    }

                    // Always reject dangerous extensions
                    if (in_array($extension, self::DANGEROUS_EXTENSIONS, true)) {
                        throw new \InvalidArgumentException(
                            "File extension '{$extension}' is not allowed for security reasons. Field: {$fileField}"
                        );
                    }

                    // Validate file size (default 5MB limit)
                    $maxFileSize = 5 * 1024 * 1024; // 5MB
                    if ($fileSize > $maxFileSize) {
                        throw new \RuntimeException(
                            "File too large. Maximum size: " . ($maxFileSize / 1024 / 1024) . "MB. Field: {$fileField}"
                        );
                    }

                    // Generate a unique ID for the file
                    $uniqueId = uniqid('', true);
                    $filename = $uniqueId . '.' . $extension;

                    $targetFilePath = $uploadDir . $filename;

                    // Move the uploaded file to the target directory
                    if (move_uploaded_file($tmpName, $targetFilePath)) {
                        // Set secure file permissions (0640)
                        chmod($targetFilePath, 0640);
                        
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

    /**
     * Validate file type against allowed types for a field
     * 
     * @param string $fileField The field name
     * @param string $extension File extension (lowercase)
     * @param string $mimeType MIME type from upload
     * @throws \InvalidArgumentException if file type is not allowed
     */
    private function validateFileType(string $fileField, string $extension, string $mimeType): void
    {
        $allowedExtensions = array_map('strtolower', $this->fileFieldTypes[$fileField]);
        
        // Check if extension is in allowed list
        if (!in_array($extension, $allowedExtensions, true)) {
            throw new \InvalidArgumentException(
                "File extension '{$extension}' is not allowed for field '{$fileField}'. " .
                "Allowed extensions: " . implode(', ', $allowedExtensions)
            );
        }

        // Validate MIME type matches extension
        $mimeMap = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            'pdf' => ['application/pdf'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'txt' => ['text/plain'],
            'csv' => ['text/csv', 'text/plain'],
            'zip' => ['application/zip', 'application/x-zip-compressed'],
        ];

        if (isset($mimeMap[$extension])) {
            $expectedMimes = $mimeMap[$extension];
            if (!in_array($mimeType, $expectedMimes, true)) {
                throw new \RuntimeException(
                    "File extension mismatch. Extension '{$extension}' does not match MIME type '{$mimeType}'. Field: {$fileField}"
                );
            }
        }
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

        // If strict validation is enabled OR this is an insert (not update), validate all required fields
        if ($this->strictRequiredValidation || !$isUpdate) {
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

        // Validate criteria is not empty
        if (empty($criteria)) {
            throw new \InvalidArgumentException("Criteria cannot be empty");
        }

        // Validate identifiers in criteria keys
        foreach (array_keys($criteria) as $key) {
            $this->validateIdentifier($key);
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
        // Validate data structure
        if (empty($data) || !is_array($data[0] ?? null)) {
            throw new \InvalidArgumentException("Data must be a non-empty array of arrays");
        }

        // Validate required fields for each row (always strict for inserts)
        foreach ($data as $row) {
            $this->validateRequiredFields($row, false);
            // Validate identifiers
            foreach (array_keys($row) as $key) {
                $this->validateIdentifier($key);
            }
        }

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
        // Validate arrays have same length
        if (count($data) !== count($conditions)) {
            throw new \InvalidArgumentException("Data and conditions arrays must have the same length");
        }

        // Validate data structure
        if (empty($data) || !is_array($data[0] ?? null)) {
            throw new \InvalidArgumentException("Data must be a non-empty array of arrays");
        }

        // Validate identifiers
        foreach ($data as $row) {
            foreach (array_keys($row) as $key) {
                $this->validateIdentifier($key);
            }
        }
        foreach ($conditions as $condition) {
            foreach (array_keys($condition) as $key) {
                $this->validateIdentifier($key);
            }
        }

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
        // Validate condition is not empty
        if (empty($condition)) {
            throw new \InvalidArgumentException("Delete condition cannot be empty");
        }

        // Validate identifiers in condition keys
        foreach (array_keys($condition) as $key) {
            $this->validateIdentifier($key);
        }

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
        // Validate criteria is not empty
        if (empty($criteria)) {
            throw new \InvalidArgumentException("Search criteria cannot be empty");
        }

        // Validate identifiers in criteria keys
        foreach (array_keys($criteria) as $key) {
            $this->validateIdentifier($key);
        }

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
        $this->validateIdentifier($tableName);
        $stmt = $this->pdo->prepare("DESCRIBE $tableName");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function removeColumn($tableName, $columnName)
    {
        $this->validateIdentifier($tableName);
        $this->validateIdentifier($columnName);
        $stmt = $this->pdo->prepare("ALTER TABLE $tableName DROP COLUMN $columnName");
        // echo $stmt>;
        return $stmt->execute();
    }

    public function updateColumnType($tableName, $columnName, $newColumnType)
    {
        $this->validateIdentifier($tableName);
        $this->validateIdentifier($columnName);
        $stmt = $this->pdo->prepare("ALTER TABLE $tableName MODIFY $columnName $newColumnType");
        return $stmt->execute();
    }

    public function getDropColumnSQL(string $column): string
    {
        return "ALTER TABLE " . $this->tableName . " DROP COLUMN $column";
    }

    public function dropColumn(string $tableName, string $column): bool
    {
        $this->validateIdentifier($tableName);
        $this->validateIdentifier($column);
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
        $this->validateIdentifier($tableName);
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
        $this->validateIdentifier($tableName);
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

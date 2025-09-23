<?php

declare(strict_types=1);

namespace Reut\DB;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Psr\Log\LoggerInterface;
use Reut\DB\Exceptions\ConnectionError;
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
    public $schema;
    public $results;
    public $disabledRoutes;
    public $fileFields;

    public $columns;

    public function __construct(array $config, $columns = [], ?String $tableName = null, Bool $hasRelationships = false, $relationships = 0, array $fileFields = [], array $disabledRoutes = [])
    {
        $this->config = $config;
        $this->tableName = $tableName;
        $this->hasRelationships = $hasRelationships;
        $this->schema = $columns;
        $this->relationships = $relationships;
        $this->disabledRoutes = $disabledRoutes;
        $this->fileFields = $fileFields;
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

        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (\n";
        $sql .= implode(",\n", $columnDefinitions);
        $sql .= "\n);";
        return $sql;
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
        $uploadDir = dirname(__DIR__) . '/../uploads/';

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
        $uploadDir = dirname(__DIR__) . '/../uploads/';
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

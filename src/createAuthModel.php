<?php
declare(strict_types=1);

/**
 * Generate authentication model (UsersTable) with proper column definitions
 * 
 * @param string $tableName The table name (e.g., 'Users')
 * @param string $identifierField The identifier field name ('email' or 'username')
 * @param bool $force Overwrite existing model file if true
 * @return bool True if model was created successfully, false otherwise
 */
function createAuthModel(string $tableName, string $identifierField = 'email', bool $force = false): bool
{
    // Resolve models directory (works during init when we're already in project directory)
    $projectRoot = defined('REUT_PROJECT_ROOT') ? REUT_PROJECT_ROOT : getcwd();
    $modelsDir = rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR;
    
    // Ensure models directory exists
    if (!is_dir($modelsDir)) {
        mkdir($modelsDir, 0755, true);
    }
    
    $modelFile = $modelsDir . $tableName . 'Table.php';
    
    // Check if model file already exists
    if (file_exists($modelFile) && !$force) {
        return false; // Model already exists
    }
    
    // Model class template for authentication
    $modelTemplate = <<<EOT
<?php
declare(strict_types=1);

namespace Reut\Models;

use Reut\DB\DataBase;
use Reut\DB\Types\Varchar;
use Reut\DB\Types\Integer;
use Reut\DB\Types\Timestamp;

/**
 * Authentication model for {$tableName} table
 * This model is used by the built-in authentication system
 */
class {$tableName}Table extends DataBase
{
    public function __construct(array \$config)
    {
        parent::__construct(
            \$config,
            [], // Columns array (will be populated below)
            '{$tableName}', // Table name
            false, // hasRelationships
            0, // relationships count
            [], // File fields array
            [], // Disabled routes (empty = all routes enabled)
            ['created_at', 'updated_at'], // Protected columns
            null, // strictRequiredValidation (use env var)
            [], // File field types
            false // requiresAuth (auth routes handle their own auth)
        );

        // Primary key: Auto-incrementing integer
        \$this->addColumn('id', new Integer(
            false, // Not nullable
            true,  // Is primary key
            true,  // Auto-increment
            null   // Default value
        ));

        // Identifier field: email or username
        \$this->addColumn('{$identifierField}', new Varchar(
            255,   // Length
            false, // Not nullable (required)
            null   // Default value
        ));

        // Password field: Hashed password storage
        \$this->addColumn('password', new Varchar(
            255,   // Length (for bcrypt/argon2 hashes)
            false, // Not nullable (required)
            null   // Default value
        ));

        // Timestamps: Automatically managed
        \$this->addColumn('created_at', new Timestamp(
            false, // Not nullable
            true   // DEFAULT CURRENT_TIMESTAMP
        ));

        \$this->addColumn('updated_at', new Timestamp(
            false, // Not nullable
            true,  // DEFAULT CURRENT_TIMESTAMP
            true   // ON UPDATE CURRENT_TIMESTAMP
        ));
    }
}
EOT;

    // Write the model file
    $fileOpen = fopen($modelFile, 'w');
    if ($fileOpen) {
        try {
            fwrite($fileOpen, $modelTemplate);
            fclose($fileOpen);
            return true;
        } catch (Exception $e) {
            fclose($fileOpen);
            throw new \RuntimeException("Error creating auth model: " . $e->getMessage());
        }
    } else {
        throw new \RuntimeException("Could not open model file for writing: $modelFile");
    }
}


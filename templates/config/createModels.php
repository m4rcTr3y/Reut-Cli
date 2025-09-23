<?php
declare(strict_types=1);

$modelsDir = __DIR__ . '/../models/';
$modelName = $argv[2] ?? '';
$hasRelationships = false;

// Get model name from argument or prompt
if (empty($modelName)) {
    echo "Enter model name (e.g., Accounts): ";
    $handle = fopen("php://stdin", "r");
    $modelName = trim(fgets($handle));
} else {
    $handle = fopen("php://stdin", "r");
}

// Prompt for relationship information
echo "Does this table have relationships? (y/n): ";
$answer = trim(fgets($handle));
if ($answer === 'y' || $answer === 'Y') {
    $hasRelationships = true;
}
fclose($handle);

// Validate model name
if (empty($modelName) || !preg_match('/^[A-Z][a-zA-Z0-9]*$/', $modelName)) {
    echo "Error: Model name must start with an uppercase letter and contain only letters and numbers.\n";
    exit(1);
}

// Ensure models directory exists
if (!is_dir($modelsDir)) {
    mkdir($modelsDir, 0755, true);
}

$modelFile = $modelsDir . $modelName . 'Table.php';

// Check if model file already exists
if (file_exists($modelFile) && !in_array('--force', $argv)) {
    echo "Model file for $modelName already exists. Use --force to overwrite.\n";
    exit(1);
}

// Model class template with explanatory comments
$mytalbeRelations = $hasRelationships?'true':'false';

$modelTemplate = <<<EOT
<?php
declare(strict_types=1);

namespace Reut\Models;

use Reut\DB\DataBase;
use Reut\DB\Types\Varchar;
use Reut\DB\Types\Integer;

// This class represents the {$modelName} table in the database, extending the DataBase class for database operations
class {$modelName}Table extends DataBase
{
    // Constructor initializes the model with configuration and table settings
    // @param array \$config Database configuration settings
    public function __construct(array \$config)
    {
        // Initialize the parent DataBase class with:
        // - \$config: Database connection settings
        // - []: Initial empty columns array (to be populated below)
        // - '{$modelName}': The table name
        // - hasRelationships: Whether the table has relationships
        // - []: File fields array (for file uploads, if any)
        // - ['all']: Disabled routes array (routes to disable for this model)
        parent::__construct(
            \$config,
            [],
            '{$modelName}',
            {$mytalbeRelations},
            [],
            ['all']
        );

        // Define table columns with their properties
        // id: Auto-incrementing primary key
        \$this->addColumn('id', new Integer(
            false, // Not nullable
            true,  // Is primary key
            true,  // Auto-increment
            null   // Default value
        ));

        // TODO: Add your custom column definitions here

        // TODO: Add your relationship definitions here (e.g., hasMany, belongsTo)
    }

    // TODO: Add your custom methods here (e.g., custom queries, business logic)
}
EOT;

// Write the model file
$fileOpen = fopen($modelFile, 'w');
if ($fileOpen) {
    try {
        fwrite($fileOpen, $modelTemplate);
        fclose($fileOpen);
        echo "Generated model file: $modelFile\n";
    } catch (Exception $e) {
        fclose($fileOpen);
        echo "There was an error: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    echo "There was an error creating the model, please try again\n";
    exit(1);
}
?>
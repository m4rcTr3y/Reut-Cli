<?php
declare(strict_types=1);

namespace Reut\CLI\Commands;

use Reut\CLI\Interactive\Prompt;
use Reut\Support\ProjectPath;

/**
 * Generate model command
 */
class GenerateModelCommand extends Command
{
    public function getName(): string
    {
        return 'generate:model';
    }

    public function getDescription(): string
    {
        return 'Generate a model class';
    }

    public function getUsage(): string
    {
        return 'generate:model <ModelName> [--force]';
    }

    public function getOptions(): array
    {
        return [
            '--force' => 'Overwrite existing model file',
        ];
    }

    public function getExamples(): array
    {
        return [
            'Reut generate:model Users',
            'Reut generate:model Posts --force',
        ];
    }

    public function execute(array $args = []): int
    {
        $modelName = $this->getArg(0);
        
        if (empty($modelName)) {
            $modelName = $this->prompt->ask(
                'Enter model name (e.g., Users)',
                null,
                Prompt::pattern('/^[A-Z][a-zA-Z0-9]*$/', 'Model name must start with uppercase letter and contain only letters and numbers')
            );
        }

        // Validate model name
        if (!preg_match('/^[A-Z][a-zA-Z0-9]*$/', $modelName)) {
            $this->error('Model name must start with an uppercase letter and contain only letters and numbers.');
            return 1;
        }

        $modelsDir = rtrim(ProjectPath::resolve('models'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        
        if (!is_dir($modelsDir)) {
            mkdir($modelsDir, 0755, true);
            $this->success("Created models directory");
        }

        $modelFile = $modelsDir . $modelName . 'Table.php';

        // Check if file exists
        if (file_exists($modelFile) && !$this->hasOption('force')) {
            $this->error("Model file for {$modelName} already exists.");
            $this->info("Use --force to overwrite.");
            return 1;
        }

        // Generate model content
        $modelContent = $this->generateModelContent($modelName);
        file_put_contents($modelFile, $modelContent);

        $this->success("Generated model: {$modelName}Table.php");
        $this->writeln();
        $this->info("Next steps:");
        $this->writeln("  1. Edit {$modelFile} to add your columns");
        $this->writeln("  2. Run `Reut migrate` to create the table");

        return 0;
    }

    private function generateModelContent(string $modelName): string
    {
        $tableName = $modelName; // Default table name matches model name
        return <<<PHP
<?php
declare(strict_types=1);

namespace Reut\Models;

use Reut\DB\DataBase;
use Reut\DB\Types\Varchar;
use Reut\DB\Types\Integer;
use Reut\DB\Types\Timestamp;

/**
 * {$modelName} model
 */
class {$modelName}Table extends DataBase
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
            false // requiresAuth
        );

        // Primary key: Auto-incrementing integer
        \$this->addColumn('id', new Integer(
            false, // Not nullable
            true,  // Is primary key
            true,  // Auto-increment
            null   // Default value
        ));

        // Add your columns here
        // Example:
        // \$this->addColumn('name', new Varchar(255, false));
        // \$this->addColumn('email', new Varchar(255, false));

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
PHP;
    }
}



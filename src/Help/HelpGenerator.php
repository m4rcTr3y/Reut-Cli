<?php
declare(strict_types=1);

namespace Reut\CLI\Help;

use Reut\CLI\Commands\Command;
use Reut\CLI\Commands\CommandRegistry;
use Reut\CLI\Output\Formatter;
use Reut\CLI\Output\Table;

/**
 * Dynamic help generator
 */
class HelpGenerator
{
    private Formatter $formatter;
    private CommandRegistry $registry;

    public function __construct(CommandRegistry $registry, ?Formatter $formatter = null)
    {
        $this->registry = $registry;
        $this->formatter = $formatter ?? new Formatter();
    }

    /**
     * Generate general help
     */
    public function generateGeneralHelp(): string
    {
        $output = [];
        
        $output[] = $this->formatter->title('REUT CLI Tool') . ' v1.4.2';
        $output[] = '';
        $output[] = $this->formatter->section('Usage:');
        $output[] = '  Reut <command> [options]';
        $output[] = '  php manage.php <command> [options]';
        $output[] = '';

        // Group commands by category
        $categories = $this->groupCommandsByCategory();
        
        foreach ($categories as $category => $commands) {
            $output[] = $this->formatter->section($category . ':');
            foreach ($commands as $command) {
                $name = $command->getName();
                $description = $command->getDescription();
                $output[] = "  {$this->formatter->info($name)}" . str_repeat(' ', max(1, 25 - strlen($name))) . $description;
            }
            $output[] = '';
        }

        $output[] = $this->formatter->section('Options:');
        $output[] = "  {$this->formatter->info('-h, --help')}" . str_repeat(' ', 15) . 'Show this help message';
        $output[] = "  {$this->formatter->info('-v, --version')}" . str_repeat(' ', 10) . 'Show version information';
        $output[] = '';
        $output[] = $this->formatter->comment('For more information about a specific command, run:');
        $output[] = $this->formatter->comment('  Reut help <command>');
        $output[] = '';

        return implode("\n", $output);
    }

    /**
     * Generate command-specific help
     */
    public function generateCommandHelp(string $commandName): ?string
    {
        $command = $this->registry->get($commandName);
        
        if ($command === null) {
            return null;
        }

        $output = [];
        
        $output[] = $this->formatter->title($command->getName());
        $output[] = '';
        $output[] = $this->formatter->section('Description:');
        $output[] = '  ' . $command->getDescription();
        $output[] = '';

        $usage = $command->getUsage();
        if ($usage) {
            $output[] = $this->formatter->section('Usage:');
            $output[] = '  Reut ' . $usage;
            $output[] = '';
        }

        $options = $command->getOptions();
        if (!empty($options)) {
            $output[] = $this->formatter->section('Options:');
            foreach ($options as $option => $description) {
                $output[] = "  {$this->formatter->info($option)}" . str_repeat(' ', max(1, 30 - strlen($option))) . $description;
            }
            $output[] = '';
        }

        $examples = Examples::get($commandName);
        if (!empty($examples)) {
            $output[] = $this->formatter->section('Examples:');
            foreach ($examples as $example) {
                $output[] = $this->formatter->comment('  $ ' . $example);
            }
            $output[] = '';
        }

        return implode("\n", $output);
    }

    /**
     * Group commands by category
     */
    private function groupCommandsByCategory(): array
    {
        // Define all available commands (both registered and project-specific)
        $allAvailableCommands = [
            // Global commands (registered)
            'init' => 'Initialize a new REUT project',
            'migrate' => 'Apply migrations from model definitions (ensures tables exist)',
            'create' => 'Alias for migrate',
            'status' => 'Check for pending migrations in the models',
            'generate:model' => 'Generate a model class',
            'help' => 'Show help information',
            'version' => 'Show CLI version',
            
            // Project-specific commands (routed to manage.php)
            'generate:routes' => 'Generate routes for each model into the route/ folder',
            'view' => 'View API documentation in browser',
            'dev' => 'Start the built-in PHP dev server',
            'inspect' => 'Inspect database schema and sync model definitions',
            'sync' => 'Reconcile existing tables with models (may drop extra columns)',
            'rollback' => 'Rollback migrations (last batch, specific batch, or migration)',
            'validate-migrations' => 'Validate migration SQL syntax and check for conflicts',
            'export-migrations' => 'Export migration history to JSON or SQL format',
            'import-migrations' => 'Import migration history from JSON or SQL file',
        ];

        $categories = [
            'Project Setup' => ['init'],
            'Migrations' => ['migrate', 'create', 'status', 'rollback', 'sync', 'validate-migrations', 'export-migrations', 'import-migrations'],
            'Generation' => ['generate:model', 'generate:routes'],
            'Development' => ['dev', 'view', 'inspect'],
        ];

        $grouped = [];
        $allCommands = $this->registry->getAll();

        foreach ($categories as $category => $commandNames) {
            $grouped[$category] = [];
            foreach ($commandNames as $name) {
                if (isset($allCommands[$name])) {
                    // Use registered command
                    $grouped[$category][] = $allCommands[$name];
                } elseif (isset($allAvailableCommands[$name])) {
                    // Create a virtual command entry for project-specific commands
                    $grouped[$category][] = new VirtualCommand($name, $allAvailableCommands[$name]);
                }
            }
        }

        // Add uncategorized commands
        $uncategorized = [];
        foreach ($allCommands as $name => $command) {
            $found = false;
            foreach ($categories as $commandNames) {
                if (in_array($name, $commandNames, true)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $uncategorized[] = $command;
            }
        }

        if (!empty($uncategorized)) {
            $grouped['Other'] = $uncategorized;
        }

        return $grouped;
    }
}

/**
 * Virtual command for help display (project-specific commands)
 */
class VirtualCommand
{
    private string $name;
    private string $description;

    public function __construct(string $name, string $description)
    {
        $this->name = $name;
        $this->description = $description;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }
}



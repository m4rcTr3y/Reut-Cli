<?php
declare(strict_types=1);

namespace Reut\CLI\Commands;

use Reut\CLI\Interactive\CommandSuggestions;
use Reut\CLI\Output\Formatter;

/**
 * Command registry and dispatcher
 */
class CommandRegistry
{
    private array $commands = [];
    private array $aliases = [];
    private Formatter $formatter;
    private CommandSuggestions $suggestions;

    public function __construct()
    {
        $this->formatter = new Formatter();
        $this->registerDefaultCommands();
    }

    /**
     * Register a command
     */
    public function register(Command $command, array $aliases = []): void
    {
        $name = $command->getName();
        $this->commands[$name] = $command;

        // Register aliases
        foreach ($aliases as $alias) {
            $this->aliases[$alias] = $name;
        }
    }

    /**
     * Get command by name
     */
    public function get(string $name): ?Command
    {
        // Check direct command name
        if (isset($this->commands[$name])) {
            return $this->commands[$name];
        }

        // Check aliases
        if (isset($this->aliases[$name])) {
            return $this->commands[$this->aliases[$name]] ?? null;
        }

        return null;
    }

    /**
     * Check if command exists
     */
    public function has(string $name): bool
    {
        return isset($this->commands[$name]) || isset($this->aliases[$name]);
    }

    /**
     * Get all command names
     */
    public function getCommandNames(): array
    {
        return array_keys($this->commands);
    }

    /**
     * Get all commands
     */
    public function getAll(): array
    {
        return $this->commands;
    }

    /**
     * Execute a command
     */
    public function execute(string $name, array $argv = []): int
    {
        $command = $this->get($name);

        if ($command === null) {
            // Try to suggest similar commands
            $this->suggestions = new CommandSuggestions($this->getCommandNames(), $this->formatter);
            $suggestions = $this->suggestions->suggest($name);
            
            echo $this->suggestions->formatSuggestions($name, $suggestions) . "\n";
            echo "\nRun 'Reut help' to see all available commands.\n";
            return 1;
        }

        // Parse arguments
        $parsed = $command->parseArgs($argv);
        $command->setArgs($parsed['args']);
        $command->setOptions($parsed['options']);

        try {
            return $command->execute($parsed['args']);
        } catch (\Exception $e) {
            echo $this->formatter->error("Error: " . $e->getMessage()) . "\n";
            if ($this->getOption('verbose', false)) {
                echo $e->getTraceAsString() . "\n";
            }
            return 1;
        }
    }

    /**
     * Get option helper (for registry-level options)
     */
    private function getOption(string $name, $default = false)
    {
        // This would parse global options from argv
        // For now, return default
        return $default;
    }

    /**
     * Register default commands
     */
    private function registerDefaultCommands(): void
    {
        // Commands will be registered here as they are created
        // For now, we'll register them dynamically when needed
    }

    /**
     * Auto-discover commands from Commands directory
     */
    public function autoDiscover(string $commandsDir): void
    {
        if (!is_dir($commandsDir)) {
            return;
        }

        $files = glob($commandsDir . '/*Command.php');
        
        foreach ($files as $file) {
            $className = 'Reut\\CLI\\Commands\\' . basename($file, '.php');
            
            if (class_exists($className)) {
                $reflection = new \ReflectionClass($className);
                if ($reflection->isSubclassOf(Command::class) && !$reflection->isAbstract()) {
                    $command = new $className();
                    $this->register($command);
                }
            }
        }
    }
}



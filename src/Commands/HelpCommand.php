<?php
declare(strict_types=1);

namespace Reut\CLI\Commands;

use Reut\CLI\Help\HelpGenerator;

/**
 * Help command
 */
class HelpCommand extends Command
{
    private CommandRegistry $registry;

    public function __construct(CommandRegistry $registry)
    {
        parent::__construct();
        $this->registry = $registry;
    }

    public function getName(): string
    {
        return 'help';
    }

    public function getDescription(): string
    {
        return 'Show help information';
    }

    public function getUsage(): string
    {
        return 'help [command]';
    }

    public function getExamples(): array
    {
        return [
            'Reut help',
            'Reut help migrate',
            'Reut help generate:model',
        ];
    }

    public function execute(array $args = []): int
    {
        $generator = new HelpGenerator($this->registry, $this->formatter);

        // Show command-specific help if command name provided
        $commandName = $this->getArg(0);
        if ($commandName) {
            $help = $generator->generateCommandHelp($commandName);
            if ($help === null) {
                $this->error("Command '{$commandName}' not found.");
                $this->writeln();
                $this->writeln("Run 'Reut help' to see all available commands.");
                return 1;
            }
            $this->writeln($help);
            return 0;
        }

        // Show general help
        $this->writeln($generator->generateGeneralHelp());
        return 0;
    }
}



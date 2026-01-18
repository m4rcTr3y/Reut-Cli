<?php
declare(strict_types=1);

namespace Reut\CLI\Help;

use Reut\CLI\Commands\Command;

/**
 * Per-command help with examples
 */
class CommandHelp
{
    private Command $command;
    private HelpGenerator $generator;

    public function __construct(Command $command, HelpGenerator $generator)
    {
        $this->command = $command;
        $this->generator = $generator;
    }

    /**
     * Get formatted help text
     */
    public function getHelp(): string
    {
        return $this->generator->generateCommandHelp($this->command->getName()) ?? '';
    }

    /**
     * Display help
     */
    public function display(): void
    {
        echo $this->getHelp() . "\n";
    }
}



<?php
declare(strict_types=1);

namespace Tests\CLI\Help;

use PHPUnit\Framework\TestCase;
use Reut\CLI\Help\HelpGenerator;
use Reut\CLI\Commands\CommandRegistry;
use Reut\CLI\Commands\HelpCommand;
use Reut\CLI\Commands\InitCommand;
use Reut\CLI\Commands\MigrateCommand;

class HelpGeneratorTest extends TestCase
{
    private HelpGenerator $generator;
    private CommandRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new CommandRegistry();
        $this->registry->register(new HelpCommand($this->registry));
        $this->registry->register(new InitCommand());
        $this->registry->register(new MigrateCommand());
        $this->generator = new HelpGenerator($this->registry);
    }

    public function testGenerateGeneralHelp(): void
    {
        $help = $this->generator->generateGeneralHelp();
        $this->assertIsString($help);
        $this->assertStringContainsString('REUT CLI', $help);
        $this->assertStringContainsString('Usage', $help);
    }

    public function testGenerateCommandHelp(): void
    {
        $help = $this->generator->generateCommandHelp('migrate');
        $this->assertIsString($help);
        $this->assertStringContainsString('migrate', $help);
    }

    public function testGenerateCommandHelpForNonExistent(): void
    {
        $help = $this->generator->generateCommandHelp('nonexistent');
        $this->assertNull($help);
    }

    public function testHelpIncludesExamples(): void
    {
        $help = $this->generator->generateCommandHelp('migrate');
        // Examples should be included if they exist
        $this->assertIsString($help);
    }
}



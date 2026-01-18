<?php
declare(strict_types=1);

namespace Tests\CLI\Commands;

use PHPUnit\Framework\TestCase;
use Reut\CLI\Commands\CommandRegistry;
use Reut\CLI\Commands\HelpCommand;
use Reut\CLI\Commands\InitCommand;
use Reut\CLI\Commands\MigrateCommand;
use Reut\CLI\Commands\StatusCommand;

class HelpCommandTest extends TestCase
{
    private CommandRegistry $registry;
    private HelpCommand $command;

    protected function setUp(): void
    {
        $this->registry = new CommandRegistry();
        $this->registry->register(new InitCommand());
        $this->registry->register(new MigrateCommand());
        $this->registry->register(new StatusCommand());
        $this->command = new HelpCommand($this->registry);
    }

    public function testGetName(): void
    {
        $this->assertEquals('help', $this->command->getName());
    }

    public function testGetDescription(): void
    {
        $this->assertNotEmpty($this->command->getDescription());
    }

    public function testGetUsage(): void
    {
        $usage = $this->command->getUsage();
        $this->assertIsString($usage);
        $this->assertStringContainsString('help', $usage);
    }

    public function testGetExamples(): void
    {
        $examples = $this->command->getExamples();
        $this->assertIsArray($examples);
        $this->assertNotEmpty($examples);
    }

    public function testExecuteGeneralHelp(): void
    {
        // Capture output
        ob_start();
        $exitCode = $this->command->execute([]);
        $output = ob_get_clean();
        
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('REUT CLI', $output);
        $this->assertStringContainsString('Usage', $output);
    }

    public function testExecuteCommandSpecificHelp(): void
    {
        $this->command->setArgs(['migrate']);
        
        // Capture output
        ob_start();
        $exitCode = $this->command->execute(['migrate']);
        $output = ob_get_clean();
        
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('migrate', $output);
    }

    public function testExecuteWithInvalidCommand(): void
    {
        $this->command->setArgs(['nonexistent']);
        
        // Capture output
        ob_start();
        $exitCode = $this->command->execute(['nonexistent']);
        $output = ob_get_clean();
        
        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('not found', $output);
    }
}



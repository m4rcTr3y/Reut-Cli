<?php
declare(strict_types=1);

namespace Tests\CLI\Commands;

use PHPUnit\Framework\TestCase;
use Reut\CLI\Commands\CommandRegistry;
use Reut\CLI\Commands\HelpCommand;

class CommandRegistryTest extends TestCase
{
    private CommandRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new CommandRegistry();
    }

    public function testRegisterCommand(): void
    {
        $helpCommand = new HelpCommand($this->registry);
        $this->registry->register($helpCommand);
        
        $this->assertTrue($this->registry->has('help'));
        $this->assertSame($helpCommand, $this->registry->get('help'));
    }

    public function testRegisterCommandWithAliases(): void
    {
        $helpCommand = new HelpCommand($this->registry);
        $this->registry->register($helpCommand, ['-h', '--help']);
        
        $this->assertTrue($this->registry->has('-h'));
        $this->assertTrue($this->registry->has('--help'));
        $this->assertSame($helpCommand, $this->registry->get('-h'));
    }

    public function testGetNonExistentCommand(): void
    {
        $result = $this->registry->get('nonexistent');
        $this->assertNull($result);
    }

    public function testHasCommand(): void
    {
        $helpCommand = new HelpCommand($this->registry);
        $this->registry->register($helpCommand);
        
        $this->assertTrue($this->registry->has('help'));
        $this->assertFalse($this->registry->has('nonexistent'));
    }

    public function testGetCommandNames(): void
    {
        $helpCommand = new HelpCommand($this->registry);
        $this->registry->register($helpCommand);
        
        $names = $this->registry->getCommandNames();
        $this->assertContains('help', $names);
    }

    public function testGetAllCommands(): void
    {
        $helpCommand = new HelpCommand($this->registry);
        $this->registry->register($helpCommand);
        
        $all = $this->registry->getAll();
        $this->assertArrayHasKey('help', $all);
        $this->assertSame($helpCommand, $all['help']);
    }
}



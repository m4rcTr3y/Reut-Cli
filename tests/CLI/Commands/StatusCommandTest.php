<?php
declare(strict_types=1);

namespace Tests\CLI\Commands;

use PHPUnit\Framework\TestCase;
use Reut\CLI\Commands\StatusCommand;

class StatusCommandTest extends TestCase
{
    private StatusCommand $command;

    protected function setUp(): void
    {
        $this->command = new StatusCommand();
    }

    public function testGetName(): void
    {
        $this->assertEquals('status', $this->command->getName());
    }

    public function testGetDescription(): void
    {
        $this->assertNotEmpty($this->command->getDescription());
    }

    public function testGetUsage(): void
    {
        $usage = $this->command->getUsage();
        $this->assertIsString($usage);
        $this->assertStringContainsString('status', $usage);
    }

    public function testGetOptions(): void
    {
        $options = $this->command->getOptions();
        $this->assertIsArray($options);
        $this->assertArrayHasKey('--json', $options);
        $this->assertArrayHasKey('--summary', $options);
    }

    public function testGetExamples(): void
    {
        $examples = $this->command->getExamples();
        $this->assertIsArray($examples);
        $this->assertNotEmpty($examples);
    }

    public function testHasOption(): void
    {
        $this->command->setOptions(['json' => true]);
        
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('hasOption');
        $method->setAccessible(true);
        
        $this->assertTrue($method->invoke($this->command, 'json'));
        $this->assertFalse($method->invoke($this->command, 'nonexistent'));
    }

    public function testGetOption(): void
    {
        $this->command->setOptions(['table' => 'users']);
        
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('getOption');
        $method->setAccessible(true);
        
        $this->assertEquals('users', $method->invoke($this->command, 'table'));
        $this->assertNull($method->invoke($this->command, 'nonexistent'));
    }
}


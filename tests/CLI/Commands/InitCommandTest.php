<?php
declare(strict_types=1);

namespace Tests\CLI\Commands;

use PHPUnit\Framework\TestCase;
use Reut\CLI\Commands\InitCommand;

class InitCommandTest extends TestCase
{
    private InitCommand $command;

    protected function setUp(): void
    {
        $this->command = new InitCommand();
    }

    public function testGetName(): void
    {
        $this->assertEquals('init', $this->command->getName());
    }

    public function testGetDescription(): void
    {
        $this->assertNotEmpty($this->command->getDescription());
    }

    public function testGetUsage(): void
    {
        $usage = $this->command->getUsage();
        $this->assertIsString($usage);
        $this->assertStringContainsString('init', $usage);
    }

    public function testGetExamples(): void
    {
        $examples = $this->command->getExamples();
        $this->assertIsArray($examples);
        $this->assertNotEmpty($examples);
    }

    public function testSanitizePackageName(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('sanitizePackageName');
        $method->setAccessible(true);
        
        $this->assertEquals('my-project', $method->invoke($this->command, 'My Project'));
        $this->assertEquals('test123', $method->invoke($this->command, 'test123'));
        $this->assertEquals('app', $method->invoke($this->command, ''));
    }
}



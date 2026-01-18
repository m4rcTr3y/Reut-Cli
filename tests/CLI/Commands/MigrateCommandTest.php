<?php
declare(strict_types=1);

namespace Tests\CLI\Commands;

use PHPUnit\Framework\TestCase;
use Reut\CLI\Commands\MigrateCommand;

class MigrateCommandTest extends TestCase
{
    private MigrateCommand $command;

    protected function setUp(): void
    {
        $this->command = new MigrateCommand();
    }

    public function testGetName(): void
    {
        $this->assertEquals('migrate', $this->command->getName());
    }

    public function testGetDescription(): void
    {
        $this->assertNotEmpty($this->command->getDescription());
    }

    public function testGetUsage(): void
    {
        $usage = $this->command->getUsage();
        $this->assertIsString($usage);
        $this->assertStringContainsString('migrate', $usage);
    }

    public function testGetOptions(): void
    {
        $options = $this->command->getOptions();
        $this->assertIsArray($options);
        $this->assertArrayHasKey('--dry-run', $options);
    }

    public function testGetExamples(): void
    {
        $examples = $this->command->getExamples();
        $this->assertIsArray($examples);
        $this->assertNotEmpty($examples);
    }

    public function testHasOption(): void
    {
        $this->command->setOptions(['dry-run' => true]);
        
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('hasOption');
        $method->setAccessible(true);
        
        $this->assertTrue($method->invoke($this->command, 'dry-run'));
    }
}


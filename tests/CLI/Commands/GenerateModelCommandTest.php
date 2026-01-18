<?php
declare(strict_types=1);

namespace Tests\CLI\Commands;

use PHPUnit\Framework\TestCase;
use Reut\CLI\Commands\GenerateModelCommand;

class GenerateModelCommandTest extends TestCase
{
    private GenerateModelCommand $command;

    protected function setUp(): void
    {
        $this->command = new GenerateModelCommand();
    }

    public function testGetName(): void
    {
        $this->assertEquals('generate:model', $this->command->getName());
    }

    public function testGetDescription(): void
    {
        $this->assertNotEmpty($this->command->getDescription());
    }

    public function testGetUsage(): void
    {
        $usage = $this->command->getUsage();
        $this->assertIsString($usage);
        $this->assertStringContainsString('generate:model', $usage);
    }

    public function testGetOptions(): void
    {
        $options = $this->command->getOptions();
        $this->assertIsArray($options);
        $this->assertArrayHasKey('--force', $options);
    }

    public function testGetExamples(): void
    {
        $examples = $this->command->getExamples();
        $this->assertIsArray($examples);
        $this->assertNotEmpty($examples);
    }
}



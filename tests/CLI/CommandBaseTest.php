<?php
declare(strict_types=1);

namespace Tests\CLI;

use PHPUnit\Framework\TestCase;
use Reut\CLI\Commands\Command;
use Reut\CLI\Commands\HelpCommand;
use Reut\CLI\Commands\CommandRegistry;

class CommandBaseTest extends TestCase
{
    public function testCommandHasFormatter(): void
    {
        $registry = new CommandRegistry();
        $command = new HelpCommand($registry);
        
        $reflection = new \ReflectionClass($command);
        $property = $reflection->getProperty('formatter');
        $property->setAccessible(true);
        
        $formatter = $property->getValue($command);
        $this->assertNotNull($formatter);
    }

    public function testCommandHasTable(): void
    {
        $registry = new CommandRegistry();
        $command = new HelpCommand($registry);
        
        $reflection = new \ReflectionClass($command);
        $property = $reflection->getProperty('table');
        $property->setAccessible(true);
        
        $table = $property->getValue($command);
        $this->assertNotNull($table);
    }

    public function testCommandHasPrompt(): void
    {
        $registry = new CommandRegistry();
        $command = new HelpCommand($registry);
        
        $reflection = new \ReflectionClass($command);
        $property = $reflection->getProperty('prompt');
        $property->setAccessible(true);
        
        $prompt = $property->getValue($command);
        $this->assertNotNull($prompt);
    }

    public function testParseArgs(): void
    {
        $registry = new CommandRegistry();
        $command = new HelpCommand($registry);
        
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('parseArgs');
        $method->setAccessible(true);
        
        $result = $method->invoke($command, ['command', 'arg1', '--option=value', '-f']);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('args', $result);
        $this->assertArrayHasKey('options', $result);
        $this->assertContains('arg1', $result['args']);
        $this->assertArrayHasKey('option', $result['options']);
        $this->assertEquals('value', $result['options']['option']);
    }

    public function testSetArgsAndGetArg(): void
    {
        $registry = new CommandRegistry();
        $command = new HelpCommand($registry);
        
        $command->setArgs(['arg1', 'arg2']);
        
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('getArg');
        $method->setAccessible(true);
        
        $this->assertEquals('arg1', $method->invoke($command, 0));
        $this->assertEquals('arg2', $method->invoke($command, 1));
        $this->assertNull($method->invoke($command, 2));
    }

    public function testSetOptionsAndGetOption(): void
    {
        $registry = new CommandRegistry();
        $command = new HelpCommand($registry);
        
        $command->setOptions(['verbose' => true, 'output' => 'json']);
        
        $reflection = new \ReflectionClass($command);
        $hasOptionMethod = $reflection->getMethod('hasOption');
        $hasOptionMethod->setAccessible(true);
        $getOptionMethod = $reflection->getMethod('getOption');
        $getOptionMethod->setAccessible(true);
        
        $this->assertTrue($hasOptionMethod->invoke($command, 'verbose'));
        $this->assertEquals('json', $getOptionMethod->invoke($command, 'output'));
        $this->assertNull($getOptionMethod->invoke($command, 'nonexistent'));
    }

    public function testOutputMethods(): void
    {
        $registry = new CommandRegistry();
        $command = new HelpCommand($registry);
        
        // Test that output methods don't throw exceptions
        ob_start();
        try {
            $reflection = new \ReflectionClass($command);
            
            // Test protected methods using reflection
            $methods = ['write', 'writeln', 'success', 'error', 'warning', 'info', 'comment', 'section'];
            foreach ($methods as $methodName) {
                if ($reflection->hasMethod($methodName)) {
                    $method = $reflection->getMethod($methodName);
                    $method->setAccessible(true);
                    if ($methodName === 'write' || $methodName === 'writeln') {
                        $method->invoke($command, 'test');
                    } else {
                        $method->invoke($command, 'test message');
                    }
                }
            }
        } finally {
            $output = ob_get_clean();
        }
        
        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    public function testCreateProgressBar(): void
    {
        $registry = new CommandRegistry();
        $command = new HelpCommand($registry);
        
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('createProgressBar');
        $method->setAccessible(true);
        
        $progressBar = $method->invoke($command, 10);
        $this->assertNotNull($progressBar);
        $this->assertEquals(0.0, $progressBar->getProgress());
    }

    public function testCreateSpinner(): void
    {
        $registry = new CommandRegistry();
        $command = new HelpCommand($registry);
        
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('createSpinner');
        $method->setAccessible(true);
        
        $spinner = $method->invoke($command);
        $this->assertNotNull($spinner);
        $this->assertFalse($spinner->isRunning());
    }
}


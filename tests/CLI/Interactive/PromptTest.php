<?php
declare(strict_types=1);

namespace Tests\CLI\Interactive;

use PHPUnit\Framework\TestCase;
use Reut\CLI\Interactive\Prompt;
use Reut\CLI\Output\Formatter;

class PromptTest extends TestCase
{
    private Prompt $prompt;

    protected function setUp(): void
    {
        $this->prompt = new Prompt();
    }

    public function testRequiredValidator(): void
    {
        $validator = Prompt::required();
        $this->assertTrue($validator('test'));
        $this->assertIsString($validator(''));
    }

    public function testMinLengthValidator(): void
    {
        $validator = Prompt::minLength(5);
        $this->assertTrue($validator('hello'));
        $this->assertIsString($validator('hi'));
    }

    public function testPatternValidator(): void
    {
        $validator = Prompt::pattern('/^[A-Z]+$/', 'Must be uppercase');
        $this->assertTrue($validator('HELLO'));
        $this->assertEquals('Must be uppercase', $validator('hello'));
    }

    public function testInValidator(): void
    {
        $validator = Prompt::in(['yes', 'no'], 'Invalid choice');
        $this->assertTrue($validator('yes'));
        $this->assertEquals('Invalid choice', $validator('maybe'));
    }

    public function testValidatorWithValidInput(): void
    {
        $validator = Prompt::required();
        $result = $validator('test value');
        $this->assertTrue($result);
    }

    public function testValidatorWithInvalidInput(): void
    {
        $validator = Prompt::minLength(10);
        $result = $validator('short');
        $this->assertIsString($result);
        $this->assertStringContainsString('at least', $result);
    }
}



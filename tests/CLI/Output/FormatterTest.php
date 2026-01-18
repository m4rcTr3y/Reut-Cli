<?php
declare(strict_types=1);

namespace Tests\CLI\Output;

use PHPUnit\Framework\TestCase;
use Reut\CLI\Output\Formatter;

class FormatterTest extends TestCase
{
    private Formatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new Formatter();
    }

    public function testSuccessMethod(): void
    {
        $result = $this->formatter->success('Test message');
        $this->assertIsString($result);
        $this->assertStringContainsString('Test message', $result);
    }

    public function testErrorMethod(): void
    {
        $result = $this->formatter->error('Test error');
        $this->assertIsString($result);
        $this->assertStringContainsString('Test error', $result);
    }

    public function testWarningMethod(): void
    {
        $result = $this->formatter->warning('Test warning');
        $this->assertIsString($result);
        $this->assertStringContainsString('Test warning', $result);
    }

    public function testInfoMethod(): void
    {
        $result = $this->formatter->info('Test info');
        $this->assertIsString($result);
        $this->assertStringContainsString('Test info', $result);
    }

    public function testQuestionMethod(): void
    {
        $result = $this->formatter->question('Test question');
        $this->assertIsString($result);
        $this->assertStringContainsString('Test question', $result);
    }

    public function testCommentMethod(): void
    {
        $result = $this->formatter->comment('Test comment');
        $this->assertIsString($result);
        $this->assertStringContainsString('Test comment', $result);
    }

    public function testIconMethod(): void
    {
        $this->assertEquals('✓', $this->formatter->icon('success'));
        $this->assertEquals('✗', $this->formatter->icon('error'));
        $this->assertEquals('⚠', $this->formatter->icon('warning'));
        $this->assertEquals('', $this->formatter->icon('nonexistent'));
    }

    public function testSectionMethod(): void
    {
        $result = $this->formatter->section('Test Section');
        $this->assertIsString($result);
        $this->assertStringContainsString('Test Section', $result);
    }

    public function testTitleMethod(): void
    {
        $result = $this->formatter->title('Test Title');
        $this->assertIsString($result);
        $this->assertStringContainsString('Test Title', $result);
    }

    public function testFormatMethodWithColor(): void
    {
        $result = $this->formatter->format('Test', 'green');
        $this->assertIsString($result);
        $this->assertStringContainsString('Test', $result);
    }

    public function testFormatMethodWithStyle(): void
    {
        $result = $this->formatter->format('Test', null, 'bold');
        $this->assertIsString($result);
        $this->assertStringContainsString('Test', $result);
    }

    public function testSupportsColorsMethod(): void
    {
        $result = $this->formatter->supportsColors();
        $this->assertIsBool($result);
    }
}



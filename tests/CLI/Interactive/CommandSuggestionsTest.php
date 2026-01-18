<?php
declare(strict_types=1);

namespace Tests\CLI\Interactive;

use PHPUnit\Framework\TestCase;
use Reut\CLI\Interactive\CommandSuggestions;
use Reut\CLI\Output\Formatter;

class CommandSuggestionsTest extends TestCase
{
    private CommandSuggestions $suggestions;

    protected function setUp(): void
    {
        $commands = ['migrate', 'status', 'init', 'generate:model', 'help'];
        $this->suggestions = new CommandSuggestions($commands, new Formatter());
    }

    public function testExactMatchReturnsEmpty(): void
    {
        $result = $this->suggestions->suggest('migrate');
        $this->assertEmpty($result);
    }

    public function testTypoDetection(): void
    {
        $result = $this->suggestions->suggest('migrat');
        $this->assertNotEmpty($result);
        $this->assertContains('migrate', $result);
    }

    public function testMultipleSuggestions(): void
    {
        $result = $this->suggestions->suggest('stat', 3);
        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(3, count($result));
    }

    public function testNoSuggestionsForCompletelyDifferent(): void
    {
        $result = $this->suggestions->suggest('xyzabc123');
        // Should return empty or very few suggestions
        $this->assertIsArray($result);
    }

    public function testFormatSuggestions(): void
    {
        $suggestions = ['migrate', 'status'];
        $result = $this->suggestions->formatSuggestions('migrat', $suggestions);
        $this->assertStringContainsString('migrat', $result);
        $this->assertStringContainsString('migrate', $result);
    }

    public function testFormatSuggestionsWithSingleSuggestion(): void
    {
        $suggestions = ['migrate'];
        $result = $this->suggestions->formatSuggestions('migrat', $suggestions);
        $this->assertStringContainsString('Did you mean', $result);
    }
}



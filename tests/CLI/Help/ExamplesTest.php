<?php
declare(strict_types=1);

namespace Tests\CLI\Help;

use PHPUnit\Framework\TestCase;
use Reut\CLI\Help\Examples;

class ExamplesTest extends TestCase
{
    public function testGetExamplesForCommand(): void
    {
        $examples = Examples::get('migrate');
        $this->assertIsArray($examples);
        $this->assertNotEmpty($examples);
    }

    public function testGetExamplesForNonExistentCommand(): void
    {
        $examples = Examples::get('nonexistent');
        $this->assertIsArray($examples);
        $this->assertEmpty($examples);
    }

    public function testGetAllExamples(): void
    {
        $all = Examples::all();
        $this->assertIsArray($all);
        $this->assertNotEmpty($all);
    }

    public function testAddExamples(): void
    {
        $originalCount = count(Examples::get('test-command'));
        Examples::add('test-command', ['Reut test-command']);
        $newExamples = Examples::get('test-command');
        $this->assertGreaterThan($originalCount, count($newExamples));
    }
}



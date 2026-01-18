<?php
declare(strict_types=1);

namespace Tests\CLI\Output;

use PHPUnit\Framework\TestCase;
use Reut\CLI\Output\Table;
use Reut\CLI\Output\Formatter;

class TableTest extends TestCase
{
    private Table $table;

    protected function setUp(): void
    {
        $this->table = new Table(new Formatter());
    }

    public function testSetHeaders(): void
    {
        $this->table->setHeaders(['Name', 'Age']);
        $result = $this->table->render();
        $this->assertStringContainsString('Name', $result);
        $this->assertStringContainsString('Age', $result);
    }

    public function testAddRow(): void
    {
        $this->table->setHeaders(['Name', 'Age']);
        $this->table->addRow(['John', '30']);
        $result = $this->table->render();
        $this->assertStringContainsString('John', $result);
        $this->assertStringContainsString('30', $result);
    }

    public function testAddRows(): void
    {
        $this->table->setHeaders(['Name', 'Age']);
        $this->table->addRows([
            ['John', '30'],
            ['Jane', '25'],
        ]);
        $result = $this->table->render();
        $this->assertStringContainsString('John', $result);
        $this->assertStringContainsString('Jane', $result);
    }

    public function testTableRendering(): void
    {
        $this->table->setHeaders(['Name', 'Age']);
        $this->table->addRow(['John', '30']);
        $result = $this->table->render();
        
        // Check for table borders
        $this->assertStringContainsString('┌', $result);
        $this->assertStringContainsString('│', $result);
        $this->assertStringContainsString('└', $result);
    }

    public function testEmptyTable(): void
    {
        $result = $this->table->render();
        $this->assertEquals('', $result);
    }

    public function testTableWithLongContent(): void
    {
        $this->table->setHeaders(['Short', 'Very Long Column Name']);
        $this->table->addRow(['A', 'B']);
        $result = $this->table->render();
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }
}



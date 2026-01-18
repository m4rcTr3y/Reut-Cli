<?php
declare(strict_types=1);

namespace Tests\CLI\Output;

use PHPUnit\Framework\TestCase;
use Reut\CLI\Output\ProgressBar;
use Reut\CLI\Output\Formatter;

class ProgressBarTest extends TestCase
{
    private ProgressBar $progressBar;

    protected function setUp(): void
    {
        $this->progressBar = new ProgressBar(10, new Formatter());
    }

    public function testInitialState(): void
    {
        $this->assertEquals(0.0, $this->progressBar->getProgress());
        $this->assertFalse($this->progressBar->isComplete());
    }

    public function testAdvance(): void
    {
        $this->progressBar->advance(5);
        $this->assertEquals(0.5, $this->progressBar->getProgress());
        $this->assertFalse($this->progressBar->isComplete());
    }

    public function testSetProgress(): void
    {
        $this->progressBar->setProgress(7);
        $this->assertEquals(0.7, $this->progressBar->getProgress());
    }

    public function testFinish(): void
    {
        $this->progressBar->finish();
        $this->assertTrue($this->progressBar->isComplete());
        $this->assertEquals(1.0, $this->progressBar->getProgress());
    }

    public function testSetMessage(): void
    {
        $this->progressBar->setMessage('Processing...');
        // Message is set, just verify no exception
        $this->assertTrue(true);
    }

    public function testSetWidth(): void
    {
        $this->progressBar->setWidth(30);
        // Width is set, just verify no exception
        $this->assertTrue(true);
    }

    public function testProgressCannotExceedTotal(): void
    {
        $this->progressBar->setProgress(100);
        $this->assertEquals(1.0, $this->progressBar->getProgress());
    }

    public function testProgressCannotBeNegative(): void
    {
        $this->progressBar->setProgress(-5);
        $this->assertEquals(0.0, $this->progressBar->getProgress());
    }
}



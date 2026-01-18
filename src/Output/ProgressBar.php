<?php
declare(strict_types=1);

namespace Reut\CLI\Output;

/**
 * Progress bar for long-running operations
 */
class ProgressBar
{
    private Formatter $formatter;
    private int $total;
    private int $current = 0;
    private int $width = 50;
    private ?string $message = null;
    private float $startTime;
    private bool $displayed = false;

    public function __construct(int $total, ?Formatter $formatter = null)
    {
        $this->total = max(1, $total);
        $this->formatter = $formatter ?? new Formatter();
        $this->startTime = microtime(true);
    }

    /**
     * Set progress bar width
     */
    public function setWidth(int $width): self
    {
        $this->width = max(10, min(100, $width));
        return $this;
    }

    /**
     * Set current message
     */
    public function setMessage(?string $message): self
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Advance progress by one
     */
    public function advance(int $step = 1): void
    {
        $this->current = min($this->current + $step, $this->total);
        $this->display();
    }

    /**
     * Set current progress
     */
    public function setProgress(int $current): void
    {
        $this->current = max(0, min($current, $this->total));
        $this->display();
    }

    /**
     * Finish the progress bar
     */
    public function finish(): void
    {
        $this->current = $this->total;
        $this->display();
        echo "\n";
    }

    /**
     * Display the progress bar
     */
    public function display(): void
    {
        if (!$this->formatter->supportsColors()) {
            // Simple text output for non-color terminals
            $percent = (int)(($this->current / $this->total) * 100);
            $message = $this->message ? " {$this->message}" : '';
            echo "\r[{$percent}%] ({$this->current}/{$this->total}){$message}";
            return;
        }

        $percent = $this->current / $this->total;
        $filled = (int)($percent * $this->width);
        $empty = $this->width - $filled;

        $bar = str_repeat('█', $filled) . str_repeat('░', $empty);
        $percentStr = str_pad((string)(int)($percent * 100), 3, ' ', STR_PAD_LEFT) . '%';
        $currentStr = "({$this->current}/{$this->total})";

        $output = "  [{$bar}] {$percentStr} {$currentStr}";

        if ($this->message) {
            $output .= " {$this->message}";
        }

        // Calculate ETA
        if ($this->current > 0 && $this->current < $this->total) {
            $elapsed = microtime(true) - $this->startTime;
            $rate = $this->current / $elapsed;
            $remaining = ($this->total - $this->current) / $rate;
            $eta = $this->formatTime($remaining);
            $output .= " ETA: {$eta}";
        }

        // Clear line and output
        echo "\r" . str_repeat(' ', 120) . "\r{$output}";

        $this->displayed = true;
    }

    /**
     * Format time in seconds to human-readable format
     */
    private function formatTime(float $seconds): string
    {
        if ($seconds < 60) {
            return (int)$seconds . 's';
        } elseif ($seconds < 3600) {
            $minutes = (int)($seconds / 60);
            $secs = (int)($seconds % 60);
            return "{$minutes}m {$secs}s";
        } else {
            $hours = (int)($seconds / 3600);
            $minutes = (int)(($seconds % 3600) / 60);
            return "{$hours}h {$minutes}m";
        }
    }

    /**
     * Get current progress percentage
     */
    public function getProgress(): float
    {
        return $this->current / $this->total;
    }

    /**
     * Check if progress is complete
     */
    public function isComplete(): bool
    {
        return $this->current >= $this->total;
    }
}



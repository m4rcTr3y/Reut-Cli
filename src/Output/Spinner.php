<?php
declare(strict_types=1);

namespace Reut\CLI\Output;

/**
 * Loading spinner for indeterminate operations
 */
class Spinner
{
    private Formatter $formatter;
    private array $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
    private int $frameIndex = 0;
    private ?string $message = null;
    private bool $running = false;
    private ?int $pid = null;

    public function __construct(?Formatter $formatter = null)
    {
        $this->formatter = $formatter ?? new Formatter();
    }

    /**
     * Set spinner message
     */
    public function setMessage(?string $message): self
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Start the spinner
     */
    public function start(?string $message = null): void
    {
        if ($this->running) {
            return;
        }

        if ($message !== null) {
            $this->message = $message;
        }

        $this->running = true;
        $this->display();
    }

    /**
     * Stop the spinner
     */
    public function stop(?string $finalMessage = null): void
    {
        if (!$this->running) {
            return;
        }

        $this->running = false;
        
        // Clear the spinner line
        echo "\r" . str_repeat(' ', 120) . "\r";
        
        if ($finalMessage !== null) {
            echo $finalMessage . "\n";
        }
    }

    /**
     * Display the spinner
     */
    public function display(): void
    {
        if (!$this->running) {
            return;
        }

        $frame = $this->frames[$this->frameIndex];
        $this->frameIndex = ($this->frameIndex + 1) % count($this->frames);

        $output = "  {$frame}";
        
        if ($this->message) {
            $output .= " {$this->message}";
        }

        echo "\r{$output}";
    }

    /**
     * Advance the spinner (call in a loop)
     */
    public function advance(): void
    {
        if ($this->running) {
            $this->display();
        }
    }

    /**
     * Check if spinner is running
     */
    public function isRunning(): bool
    {
        return $this->running;
    }
}



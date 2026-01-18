<?php
declare(strict_types=1);

namespace Reut\CLI\Commands;

use Reut\CLI\Output\Formatter;
use Reut\CLI\Output\Table;
use Reut\CLI\Output\ProgressBar;
use Reut\CLI\Output\Spinner;
use Reut\CLI\Interactive\Prompt;
use Reut\CLI\Interactive\Select;
use Reut\CLI\Interactive\Confirm;

/**
 * Base command class
 */
abstract class Command
{
    protected Formatter $formatter;
    protected Table $table;
    protected Prompt $prompt;
    protected Select $select;
    protected Confirm $confirm;
    protected array $args = [];
    protected array $options = [];

    public function __construct()
    {
        $this->formatter = new Formatter();
        $this->table = new Table($this->formatter);
        $this->prompt = new Prompt(STDIN, STDOUT, $this->formatter);
        $this->select = new Select(STDIN, STDOUT, $this->formatter);
        $this->confirm = new Confirm(STDIN, STDOUT, $this->formatter);
    }

    /**
     * Execute the command
     */
    abstract public function execute(array $args = []): int;

    /**
     * Get command name
     */
    abstract public function getName(): string;

    /**
     * Get command description
     */
    abstract public function getDescription(): string;

    /**
     * Get command usage
     */
    public function getUsage(): string
    {
        return $this->getName();
    }

    /**
     * Get command examples
     */
    public function getExamples(): array
    {
        return [];
    }

    /**
     * Get command options/flags
     */
    public function getOptions(): array
    {
        return [];
    }

    /**
     * Set command arguments
     */
    public function setArgs(array $args): void
    {
        $this->args = $args;
    }

    /**
     * Set command options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    /**
     * Get option value
     */
    protected function getOption(string $name, $default = null)
    {
        return $this->options[$name] ?? $default;
    }

    /**
     * Check if option is set
     */
    protected function hasOption(string $name): bool
    {
        return isset($this->options[$name]);
    }

    /**
     * Get argument by index
     */
    protected function getArg(int $index, $default = null)
    {
        return $this->args[$index] ?? $default;
    }

    /**
     * Write output
     */
    protected function write(string $message): void
    {
        echo $message;
    }

    /**
     * Write line
     */
    protected function writeln(string $message = ''): void
    {
        echo $message . "\n";
    }

    /**
     * Write success message
     */
    protected function success(string $message): void
    {
        $this->writeln($this->formatter->success("✓ {$message}"));
    }

    /**
     * Write error message
     */
    protected function error(string $message): void
    {
        $this->writeln($this->formatter->error("✗ {$message}"));
    }

    /**
     * Write warning message
     */
    protected function warning(string $message): void
    {
        $this->writeln($this->formatter->warning("⚠ {$message}"));
    }

    /**
     * Write info message
     */
    protected function info(string $message): void
    {
        $this->writeln($this->formatter->info("ℹ {$message}"));
    }

    /**
     * Write comment message
     */
    protected function comment(string $message): void
    {
        $this->writeln($this->formatter->comment($message));
    }

    /**
     * Write section header
     */
    protected function section(string $message): void
    {
        $this->writeln();
        $this->writeln($this->formatter->section($message));
    }

    /**
     * Create progress bar
     */
    protected function createProgressBar(int $total): ProgressBar
    {
        return new ProgressBar($total, $this->formatter);
    }

    /**
     * Create spinner
     */
    protected function createSpinner(): Spinner
    {
        return new Spinner($this->formatter);
    }

    /**
     * Parse command line arguments
     */
    public function parseArgs(array $argv): array
    {
        $args = [];
        $options = [];

        foreach ($argv as $arg) {
            if (strpos($arg, '--') === 0) {
                // Long option: --option or --option=value
                $arg = substr($arg, 2);
                if (strpos($arg, '=') !== false) {
                    [$key, $value] = explode('=', $arg, 2);
                    $options[$key] = $value;
                } else {
                    $options[$arg] = true;
                }
            } elseif (strpos($arg, '-') === 0) {
                // Short option: -o or -o=value
                $arg = substr($arg, 1);
                if (strpos($arg, '=') !== false) {
                    [$key, $value] = explode('=', $arg, 2);
                    $options[$key] = $value;
                } else {
                    $options[$arg] = true;
                }
            } else {
                $args[] = $arg;
            }
        }

        return ['args' => $args, 'options' => $options];
    }
}


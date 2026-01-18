<?php
declare(strict_types=1);

namespace Reut\CLI\Interactive;

use Reut\CLI\Output\Formatter;

/**
 * Yes/No confirmation prompt
 */
class Confirm
{
    private Formatter $formatter;
    private $input;
    private $output;

    public function __construct($input = STDIN, $output = STDOUT, ?Formatter $formatter = null)
    {
        $this->input = $input;
        $this->output = $output;
        $this->formatter = $formatter ?? new Formatter();
    }

    /**
     * Ask for confirmation
     */
    public function ask(string $question, bool $default = true): bool
    {
        $defaultText = $default ? 'Y/n' : 'y/N';
        $prompt = $this->formatter->question($question) . $this->formatter->comment(" [{$defaultText}]") . ': ';

        fwrite($this->output, $prompt);
        $answer = strtolower(trim(fgets($this->input) ?: ''));

        // Use default if empty
        if (empty($answer)) {
            return $default;
        }

        // Parse yes/no answers
        if (in_array($answer, ['y', 'yes', '1', 'true'], true)) {
            return true;
        }

        if (in_array($answer, ['n', 'no', '0', 'false'], true)) {
            return false;
        }

        // Invalid answer, ask again
        fwrite($this->output, $this->formatter->error("  ✗ Please answer 'yes' or 'no'\n"));
        return $this->ask($question, $default);
    }

    /**
     * Confirm a dangerous operation
     */
    public function confirmDangerous(string $message, bool $default = false): bool
    {
        fwrite($this->output, $this->formatter->warning("⚠ WARNING: {$message}\n"));
        fwrite($this->output, $this->formatter->error("This action cannot be undone.\n\n"));
        return $this->ask('Are you sure you want to continue?', $default);
    }
}



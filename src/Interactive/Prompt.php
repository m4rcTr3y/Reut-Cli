<?php
declare(strict_types=1);

namespace Reut\CLI\Interactive;

use Reut\CLI\Output\Formatter;

/**
 * Enhanced prompt system with validation
 */
class Prompt
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
     * Ask a question with optional default value
     */
    public function ask(string $question, ?string $default = null, ?callable $validator = null): string
    {
        $prompt = $this->formatter->question($question);
        
        if ($default !== null) {
            $prompt .= $this->formatter->comment(" [default: {$default}]");
        }
        
        $prompt .= ': ';

        fwrite($this->output, $prompt);
        $answer = $this->readLine();

        // Use default if empty
        if (empty($answer) && $default !== null) {
            return $default;
        }

        // Validate if validator provided
        if ($validator !== null) {
            $result = $validator($answer);
            if ($result !== true) {
                $error = is_string($result) ? $result : 'Invalid input';
                fwrite($this->output, $this->formatter->error("  ✗ {$error}\n"));
                return $this->ask($question, $default, $validator);
            }
        }

        return $answer;
    }

    /**
     * Ask for password (masked input)
     */
    public function password(string $question, ?callable $validator = null): string
    {
        $prompt = $this->formatter->question($question) . ': ';
        fwrite($this->output, $prompt);

        // Mask input on Unix systems
        if (PHP_OS_FAMILY !== 'Windows') {
            system('stty -echo');
        }

        $password = $this->readLine();
        
        if (PHP_OS_FAMILY !== 'Windows') {
            system('stty echo');
        }

        fwrite($this->output, "\n");

        // Validate if validator provided
        if ($validator !== null) {
            $result = $validator($password);
            if ($result !== true) {
                $error = is_string($result) ? $result : 'Invalid input';
                fwrite($this->output, $this->formatter->error("  ✗ {$error}\n"));
                return $this->password($question, $validator);
            }
        }

        return $password;
    }

    /**
     * Read a line from input
     */
    private function readLine(): string
    {
        $line = fgets($this->input);
        if ($line === false) {
            return '';
        }
        return trim($line);
    }

    /**
     * Create a validator for required input
     */
    public static function required(): callable
    {
        return function ($value) {
            if (empty(trim($value))) {
                return 'This field is required';
            }
            return true;
        };
    }

    /**
     * Create a validator for minimum length
     */
    public static function minLength(int $length): callable
    {
        return function ($value) use ($length) {
            if (strlen($value) < $length) {
                return "Must be at least {$length} characters";
            }
            return true;
        };
    }

    /**
     * Create a validator for regex pattern
     */
    public static function pattern(string $pattern, string $errorMessage = 'Invalid format'): callable
    {
        return function ($value) use ($pattern, $errorMessage) {
            if (!preg_match($pattern, $value)) {
                return $errorMessage;
            }
            return true;
        };
    }

    /**
     * Create a validator for allowed values
     */
    public static function in(array $allowedValues, string $errorMessage = 'Invalid choice'): callable
    {
        return function ($value) use ($allowedValues, $errorMessage) {
            if (!in_array($value, $allowedValues, true)) {
                return $errorMessage;
            }
            return true;
        };
    }
}



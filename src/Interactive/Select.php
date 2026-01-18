<?php
declare(strict_types=1);

namespace Reut\CLI\Interactive;

use Reut\CLI\Output\Formatter;

/**
 * Multi-choice selection prompt
 */
class Select
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
     * Display choices and get selection
     */
    public function choose(string $question, array $choices, ?string $default = null): string
    {
        fwrite($this->output, $this->formatter->question($question) . "\n");

        $indexedChoices = [];
        $index = 1;

        foreach ($choices as $key => $value) {
            if (is_numeric($key)) {
                $indexedChoices[$index] = $value;
                $displayKey = $index;
            } else {
                $indexedChoices[$key] = $value;
                $displayKey = $key;
            }

            $marker = ($default !== null && $displayKey === $default) ? $this->formatter->comment('(default)') : '';
            fwrite($this->output, "  {$this->formatter->info((string)$displayKey)}. {$value} {$marker}\n");
            $index++;
        }

        fwrite($this->output, "\n");
        $prompt = $this->formatter->question('Enter your choice');
        if ($default !== null) {
            $prompt .= $this->formatter->comment(" [default: {$default}]");
        }
        $prompt .= ': ';

        fwrite($this->output, $prompt);
        $answer = trim(fgets($this->input) ?: '');

        // Use default if empty
        if (empty($answer) && $default !== null) {
            return $default;
        }

        // Check if answer is a valid choice
        if (isset($indexedChoices[$answer])) {
            return $answer;
        }

        // Try numeric index
        if (is_numeric($answer) && isset($indexedChoices[(int)$answer])) {
            return (string)(int)$answer;
        }

        fwrite($this->output, $this->formatter->error("  ✗ Invalid choice. Please try again.\n\n"));
        return $this->choose($question, $choices, $default);
    }

    /**
     * Multi-select (returns array of selected keys)
     */
    public function multiSelect(string $question, array $choices, array $defaults = []): array
    {
        fwrite($this->output, $this->formatter->question($question) . "\n");
        fwrite($this->output, $this->formatter->comment("  (Select multiple, comma-separated, e.g., 1,3,5)\n"));

        $indexedChoices = [];
        $index = 1;

        foreach ($choices as $key => $value) {
            if (is_numeric($key)) {
                $indexedChoices[$index] = $value;
                $displayKey = $index;
            } else {
                $indexedChoices[$key] = $value;
                $displayKey = $key;
            }

            $selected = in_array($displayKey, $defaults) ? $this->formatter->success('✓') : ' ';
            fwrite($this->output, "  [{$selected}] {$this->formatter->info((string)$displayKey)}. {$value}\n");
            $index++;
        }

        fwrite($this->output, "\n");
        $prompt = $this->formatter->question('Enter your choices (comma-separated)');
        if (!empty($defaults)) {
            $prompt .= $this->formatter->comment(" [default: " . implode(',', $defaults) . "]");
        }
        $prompt .= ': ';

        fwrite($this->output, $prompt);
        $answer = trim(fgets($this->input) ?: '');

        // Use defaults if empty
        if (empty($answer) && !empty($defaults)) {
            return $defaults;
        }

        // Parse comma-separated values
        $selected = array_map('trim', explode(',', $answer));
        $result = [];

        foreach ($selected as $item) {
            if (isset($indexedChoices[$item])) {
                $result[] = $item;
            } elseif (is_numeric($item) && isset($indexedChoices[(int)$item])) {
                $result[] = (string)(int)$item;
            }
        }

        if (empty($result)) {
            fwrite($this->output, $this->formatter->error("  ✗ No valid choices selected. Please try again.\n\n"));
            return $this->multiSelect($question, $choices, $defaults);
        }

        return array_unique($result);
    }
}



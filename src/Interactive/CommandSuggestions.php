<?php
declare(strict_types=1);

namespace Reut\CLI\Interactive;

use Reut\CLI\Output\Formatter;

/**
 * Command suggestion system for typo detection
 */
class CommandSuggestions
{
    private Formatter $formatter;
    private array $commands;

    public function __construct(array $commands, ?Formatter $formatter = null)
    {
        $this->commands = $commands;
        $this->formatter = $formatter ?? new Formatter();
    }

    /**
     * Find suggestions for a given command
     */
    public function suggest(string $input, int $maxSuggestions = 3): array
    {
        $suggestions = [];
        $inputLower = strtolower($input);

        foreach ($this->commands as $command) {
            $commandLower = strtolower($command);
            
            // Exact match
            if ($commandLower === $inputLower) {
                return [];
            }

            // Calculate similarity
            $distance = $this->levenshteinDistance($inputLower, $commandLower);
            $maxLength = max(strlen($inputLower), strlen($commandLower));
            $similarity = 1 - ($distance / $maxLength);

            // Check for substring match
            $isSubstring = strpos($commandLower, $inputLower) !== false || strpos($inputLower, $commandLower) !== false;

            if ($similarity > 0.5 || $isSubstring) {
                $suggestions[] = [
                    'command' => $command,
                    'similarity' => $similarity,
                    'distance' => $distance,
                ];
            }
        }

        // Sort by similarity (highest first)
        usort($suggestions, function ($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        // Return top suggestions
        return array_slice(array_column($suggestions, 'command'), 0, $maxSuggestions);
    }

    /**
     * Calculate Levenshtein distance between two strings
     */
    private function levenshteinDistance(string $str1, string $str2): int
    {
        $len1 = strlen($str1);
        $len2 = strlen($str2);

        if ($len1 === 0) {
            return $len2;
        }
        if ($len2 === 0) {
            return $len1;
        }

        $matrix = [];

        // Initialize first row and column
        for ($i = 0; $i <= $len1; $i++) {
            $matrix[$i][0] = $i;
        }
        for ($j = 0; $j <= $len2; $j++) {
            $matrix[0][$j] = $j;
        }

        // Fill the matrix
        for ($i = 1; $i <= $len1; $i++) {
            for ($j = 1; $j <= $len2; $j++) {
                $cost = $str1[$i - 1] === $str2[$j - 1] ? 0 : 1;
                $matrix[$i][$j] = min(
                    $matrix[$i - 1][$j] + 1,      // deletion
                    $matrix[$i][$j - 1] + 1,      // insertion
                    $matrix[$i - 1][$j - 1] + $cost // substitution
                );
            }
        }

        return $matrix[$len1][$len2];
    }

    /**
     * Format suggestions for display
     */
    public function formatSuggestions(string $input, array $suggestions): string
    {
        if (empty($suggestions)) {
            return '';
        }

        $output = [];
        $output[] = $this->formatter->error("Command '{$input}' not found.");
        
        if (count($suggestions) === 1) {
            $output[] = $this->formatter->question("Did you mean:") . " {$this->formatter->info($suggestions[0])}?";
        } else {
            $output[] = $this->formatter->question("Did you mean one of these?");
            foreach ($suggestions as $suggestion) {
                $output[] = "  - {$this->formatter->info($suggestion)}";
            }
        }

        return implode("\n", $output);
    }
}



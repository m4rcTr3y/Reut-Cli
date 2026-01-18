<?php
declare(strict_types=1);

namespace Reut\CLI\Output;

/**
 * Formatter for colored and styled terminal output
 */
class Formatter
{
    private bool $supportsColors;
    private bool $supports256Colors;
    private bool $isWindows;

    // ANSI color codes
    private const COLORS = [
        'black' => '0;30',
        'red' => '0;31',
        'green' => '0;32',
        'yellow' => '0;33',
        'blue' => '0;34',
        'magenta' => '0;35',
        'cyan' => '0;36',
        'white' => '0;37',
        'default' => '0',
    ];

    private const BRIGHT_COLORS = [
        'black' => '1;30',
        'red' => '1;31',
        'green' => '1;32',
        'yellow' => '1;33',
        'blue' => '1;34',
        'magenta' => '1;35',
        'cyan' => '1;36',
        'white' => '1;37',
    ];

    private const STYLES = [
        'bold' => '1',
        'dim' => '2',
        'italic' => '3',
        'underline' => '4',
        'strikethrough' => '9',
    ];

    public function __construct()
    {
        $this->isWindows = PHP_OS_FAMILY === 'Windows';
        $this->supportsColors = $this->detectColorSupport();
        $this->supports256Colors = $this->detect256ColorSupport();
    }

    /**
     * Format text with color and style
     */
    public function format(string $text, ?string $color = null, ?string $style = null): string
    {
        if (!$this->supportsColors) {
            return $text;
        }

        $codes = [];

        if ($color) {
            $colorCode = $this->getColorCode($color);
            if ($colorCode) {
                $codes[] = $colorCode;
            }
        }

        if ($style) {
            $styleCode = self::STYLES[$style] ?? null;
            if ($styleCode) {
                $codes[] = $styleCode;
            }
        }

        if (empty($codes)) {
            return $text;
        }

        return "\033[" . implode(';', $codes) . "m{$text}\033[0m";
    }

    /**
     * Success message (green)
     */
    public function success(string $text, bool $bold = false): string
    {
        return $this->format($text, 'green', $bold ? 'bold' : null);
    }

    /**
     * Error message (red)
     */
    public function error(string $text, bool $bold = false): string
    {
        return $this->format($text, 'red', $bold ? 'bold' : null);
    }

    /**
     * Warning message (yellow)
     */
    public function warning(string $text, bool $bold = false): string
    {
        return $this->format($text, 'yellow', $bold ? 'bold' : null);
    }

    /**
     * Info message (magenta)
     */
    public function info(string $text, bool $bold = false): string
    {
        return $this->format($text, 'magenta', $bold ? 'bold' : null);
    }

    /**
     * Question message (cyan)
     */
    public function question(string $text, bool $bold = false): string
    {
        return $this->format($text, 'cyan', $bold ? 'bold' : null);
    }

    /**
     * Comment/note message (dim)
     */
    public function comment(string $text): string
    {
        return $this->format($text, null, 'dim');
    }

    /**
     * Get color code
     */
    private function getColorCode(string $color): ?string
    {
        // Check for bright colors (e.g., 'bright-green')
        if (strpos($color, 'bright-') === 0) {
            $brightColor = substr($color, 7);
            return self::BRIGHT_COLORS[$brightColor] ?? null;
        }

        return self::COLORS[$color] ?? null;
    }

    /**
     * Detect if terminal supports colors
     */
    private function detectColorSupport(): bool
    {
        // Windows 10+ supports ANSI colors
        if ($this->isWindows && function_exists('sapi_windows_vt100_support')) {
            return sapi_windows_vt100_support(STDOUT);
        }

        // Check if we're in a terminal
        if (!function_exists('posix_isatty')) {
            return false;
        }

        // Check if stdout is a TTY
        if (!posix_isatty(STDOUT)) {
            return false;
        }

        // Check TERM environment variable
        $term = getenv('TERM');
        if ($term === false || $term === 'dumb') {
            return false;
        }

        return true;
    }

    /**
     * Detect if terminal supports 256 colors
     */
    private function detect256ColorSupport(): bool
    {
        if (!$this->supportsColors) {
            return false;
        }

        $term = getenv('TERM');
        if ($term === false) {
            return false;
        }

        // Common terminals that support 256 colors
        $supportedTerms = ['xterm-256color', 'screen-256color', 'tmux-256color', 'rxvt-256color'];
        return in_array($term, $supportedTerms, true);
    }

    /**
     * Check if colors are supported
     */
    public function supportsColors(): bool
    {
        return $this->supportsColors;
    }

    /**
     * Add icons/emojis to messages
     */
    public function icon(string $type): string
    {
        $icons = [
            'success' => '✓',
            'error' => '✗',
            'warning' => '⚠',
            'info' => 'ℹ',
            'question' => '?',
            'arrow' => '→',
            'check' => '✓',
            'cross' => '✗',
            'star' => '★',
            'bullet' => '•',
        ];

        return $icons[$type] ?? '';
    }

    /**
     * Format a section header
     */
    public function section(string $text): string
    {
        return $this->format($text, 'cyan', 'bold');
    }

    /**
     * Format a title
     */
    public function title(string $text): string
    {
        return $this->format($text, 'white', 'bold');
    }
}



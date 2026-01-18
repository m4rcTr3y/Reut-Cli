<?php
declare(strict_types=1);

namespace Reut\CLI\Output;

/**
 * Table renderer for formatted terminal output
 */
class Table
{
    private Formatter $formatter;
    private array $headers = [];
    private array $rows = [];
    private array $columnWidths = [];
    private string $style = 'default';

    public function __construct(?Formatter $formatter = null)
    {
        $this->formatter = $formatter ?? new Formatter();
    }

    /**
     * Set table headers
     */
    public function setHeaders(array $headers): self
    {
        $this->headers = $headers;
        $this->calculateColumnWidths();
        return $this;
    }

    /**
     * Add a row
     */
    public function addRow(array $row): self
    {
        $this->rows[] = $row;
        $this->calculateColumnWidths();
        return $this;
    }

    /**
     * Add multiple rows
     */
    public function addRows(array $rows): self
    {
        foreach ($rows as $row) {
            $this->addRow($row);
        }
        return $this;
    }

    /**
     * Set table style
     */
    public function setStyle(string $style): self
    {
        $this->style = $style;
        return $this;
    }

    /**
     * Render the table
     */
    public function render(): string
    {
        if (empty($this->headers) && empty($this->rows)) {
            return '';
        }

        $output = [];
        $columnCount = max(
            count($this->headers),
            empty($this->rows) ? 0 : max(array_map('count', $this->rows))
        );

        // Top border
        $output[] = $this->renderTopBorder($columnCount);

        // Headers
        if (!empty($this->headers)) {
            $output[] = $this->renderRow($this->headers, true);
            $output[] = $this->renderSeparator($columnCount);
        }

        // Rows
        foreach ($this->rows as $row) {
            $output[] = $this->renderRow($row);
        }

        // Bottom border
        $output[] = $this->renderBottomBorder($columnCount);

        return implode("\n", $output);
    }

    /**
     * Calculate column widths
     */
    private function calculateColumnWidths(): void
    {
        $this->columnWidths = [];

        // Check headers
        foreach ($this->headers as $index => $header) {
            $width = $this->getStringWidth((string)$header);
            $this->columnWidths[$index] = max($this->columnWidths[$index] ?? 0, $width);
        }

        // Check rows
        foreach ($this->rows as $row) {
            foreach ($row as $index => $cell) {
                $width = $this->getStringWidth((string)$cell);
                $this->columnWidths[$index] = max($this->columnWidths[$index] ?? 0, $width);
            }
        }

        // Ensure minimum width of 1
        foreach ($this->columnWidths as $index => $width) {
            if ($width < 1) {
                $this->columnWidths[$index] = 1;
            }
        }
    }

    /**
     * Get string width (accounting for ANSI codes)
     */
    private function getStringWidth(string $string): int
    {
        // Remove ANSI color codes for width calculation
        $string = preg_replace('/\033\[[0-9;]*m/', '', $string);
        return mb_strwidth($string, 'UTF-8');
    }

    /**
     * Render a row
     */
    private function renderRow(array $row, bool $isHeader = false): string
    {
        $cells = [];
        $maxIndex = max(array_keys($this->columnWidths));

        for ($i = 0; $i <= $maxIndex; $i++) {
            $cell = $row[$i] ?? '';
            $width = $this->columnWidths[$i] ?? 1;
            $padded = $this->padString((string)$cell, $width);
            
            if ($isHeader) {
                $padded = $this->formatter->format($padded, 'cyan', 'bold');
            }
            
            $cells[] = $padded;
        }

        return '│ ' . implode(' │ ', $cells) . ' │';
    }

    /**
     * Pad string to width
     */
    private function padString(string $string, int $width): string
    {
        $actualWidth = $this->getStringWidth($string);
        $padding = $width - $actualWidth;
        
        if ($padding > 0) {
            return $string . str_repeat(' ', $padding);
        }
        
        return $string;
    }

    /**
     * Render top border
     */
    private function renderTopBorder(int $columnCount): string
    {
        $parts = [];
        for ($i = 0; $i < $columnCount; $i++) {
            $width = $this->columnWidths[$i] ?? 1;
            $parts[] = str_repeat('─', $width);
        }
        return '┌─' . implode('─┬─', $parts) . '─┐';
    }

    /**
     * Render separator
     */
    private function renderSeparator(int $columnCount): string
    {
        $parts = [];
        for ($i = 0; $i < $columnCount; $i++) {
            $width = $this->columnWidths[$i] ?? 1;
            $parts[] = str_repeat('─', $width);
        }
        return '├─' . implode('─┼─', $parts) . '─┤';
    }

    /**
     * Render bottom border
     */
    private function renderBottomBorder(int $columnCount): string
    {
        $parts = [];
        for ($i = 0; $i < $columnCount; $i++) {
            $width = $this->columnWidths[$i] ?? 1;
            $parts[] = str_repeat('─', $width);
        }
        return '└─' . implode('─┴─', $parts) . '─┘';
    }

    /**
     * Render and output directly
     */
    public function display(): void
    {
        echo $this->render() . "\n";
    }
}



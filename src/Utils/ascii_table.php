<?php
// Updated reusable function to display an array of associative arrays as an ASCII table
// Parameters:
// - $data: Array of associative arrays (e.g., migrations with keys id, name, batch, applied_at)
// - $heading: String to display above the table (e.g., "Applied Migrations")
// - $headers: Optional array of column headers; defaults to ['ID', 'Migration Name', 'Batch', 'Applied At']
// - $maxFieldLength: Optional max length for field values (default 30); longer values are truncated with '...'
// Changes:
// - Added truncation for long values (e.g., names) with configurable $maxFieldLength
// - Maintains proper column alignment with padding
// - Handles missing keys with empty string fallback
// - Ensures table is not distorted by controlling field lengths

function displayTable(array $data, string $heading,  int $maxFieldLength = 26,array $headers = ['ID', 'Migration Name', 'Batch', 'Applied At'],): void
{
    if (empty($data)) {
        echo "\n=== $heading ===\n";
        echo "No data to display.\n";
        return;
    }

    // Get keys from the first row to ensure consistency
    $keys = array_keys($data[0]);
    if (count($headers) !== count($keys)) {
        // Fallback to keys if headers don't match
        $headers = $keys;
    }

    // Calculate maximum length for each column with padding
    $maxLengths = array_fill_keys($keys, 0);
    $padding = 2; // Extra spaces for readability
    foreach ($keys as $index => $key) {
        // Start with header length, truncated if necessary
        $header = strlen($headers[$index]) > $maxFieldLength ? substr($headers[$index], 0, $maxFieldLength - 3) . '...' : $headers[$index];
        $maxLengths[$key] = strlen($header) + $padding;
        // Check data lengths
        foreach ($data as $row) {
            $value = isset($row[$key]) ? (string)$row[$key] : '';
            // Truncate long values
            $value = strlen($value) > $maxFieldLength ? substr($value, 0, $maxFieldLength - 3) . '...' : $value;
            $maxLengths[$key] = max($maxLengths[$key], strlen($value) + $padding);
        }
    }

    // Build format strings for header and rows
    $headerFormat = '|';
    $rowFormat = '|';
    $separator = '+';
    foreach ($keys as $key) {
        $headerFormat .= ' %-' . $maxLengths[$key] . 's |';
        $rowFormat .= ' %-' . $maxLengths[$key] . 's |';
        $separator .= str_repeat('-', $maxLengths[$key] + 2) . '+';
    }
    $headerFormat .= "\n";
    $rowFormat .= "\n";
    $separator .= "\n";

    // Output table
    echo "\n=== $heading ===\n";
    echo $separator;
    // Truncate headers if necessary
    $formattedHeaders = array_map(function ($header) use ($maxFieldLength) {
        return strlen($header) > $maxFieldLength ? substr($header, 0, $maxFieldLength - 3) . '...' : $header;
    }, $headers);
    printf($headerFormat, ...array_map(fn($header) => str_pad($header, $maxLengths[array_keys($maxLengths)[array_search($header, $formattedHeaders)]], ' '), $formattedHeaders));
    echo $separator;

    foreach ($data as $row) {
        $values = [];
        foreach ($keys as $key) {
            $value = isset($row[$key]) ? (string)$row[$key] : '';
            // Truncate long values
            $value = strlen($value) > $maxFieldLength ? substr($value, 0, $maxFieldLength - 3) . '...' : $value;
            $values[] = str_pad($value, $maxLengths[$key], ' ');
        }
        printf($rowFormat, ...$values);
    }

    echo $separator;
}
?>
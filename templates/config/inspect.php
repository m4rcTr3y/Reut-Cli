<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config.php';

use Reut\DB\DataBase;

$options = parseOptions($argv ?? []);

$db = new DataBase($config);
try {
    $db->connect();
} catch (Throwable $e) {
    fwrite(STDERR, "Failed to connect to database: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

$allTables = $db->getTablesList();
if (!$allTables) {
    fwrite(STDOUT, "No tables found in database {$config['dbname']}." . PHP_EOL);
    exit(0);
}

$tablesToInspect = resolveTables($options, $allTables);
if (empty($tablesToInspect)) {
    fwrite(STDOUT, "No tables selected. Exiting." . PHP_EOL);
    exit(0);
}

foreach ($tablesToInspect as $table) {
    $table = sanitizeIdentifier($table);
    if ($table === '') {
        continue;
    }

    $columns = $db->sqlQuery("SHOW FULL COLUMNS FROM `{$table}`");
    if (!$columns) {
        fwrite(STDOUT, "Skipping {$table}: unable to fetch columns." . PHP_EOL);
        continue;
    }

    $modelBaseName = tableToModelName($table);
    $modelPath = __DIR__ . '/../models/' . $modelBaseName . 'Table.php';
    if (!file_exists($modelPath)) {
        fwrite(STDOUT, "Skipping {$table}: model {$modelBaseName}Table.php not found." . PHP_EOL);
        continue;
    }

    $previewBlock = buildColumnsBlock($columns);

    fwrite(STDOUT, PHP_EOL . "========== {$table} ==========" . PHP_EOL);
    fwrite(STDOUT, "Model: {$modelBaseName}Table" . PHP_EOL);
    fwrite(STDOUT, "Proposed column definitions:" . PHP_EOL . PHP_EOL);
    fwrite(STDOUT, $previewBlock . PHP_EOL . PHP_EOL);

    $shouldApply = $options['apply'];
    if (!$shouldApply) {
        $shouldApply = askConfirmation("Apply changes to {$modelBaseName}Table.php? (y/n): ");
    }

    if ($shouldApply) {
        $updated = updateModelColumns($modelPath, $previewBlock);
        if ($updated) {
            fwrite(STDOUT, "Updated {$modelBaseName}Table.php" . PHP_EOL);
        } else {
            fwrite(STDOUT, "Failed to update {$modelBaseName}Table.php" . PHP_EOL);
        }
    } else {
        fwrite(STDOUT, "Skipped applying changes for {$modelBaseName}Table.php" . PHP_EOL);
    }
}

exit(0);

function parseOptions(array $argv): array
{
    $options = [
        'tables' => [],
        'all' => false,
        'apply' => false,
    ];

    foreach ($argv as $index => $arg) {
        if ($index < 2) {
            continue;
        }
        if ($arg === '--all') {
            $options['all'] = true;
        } elseif ($arg === '--apply') {
            $options['apply'] = true;
        } elseif (substr($arg, 0, 8) === '--table=') {
            $options['tables'][] = substr($arg, 8);
        } elseif ($arg === '--table') {
            $next = $argv[$index + 1] ?? null;
            if ($next) {
                $options['tables'][] = $next;
            }
        }
    }

    return $options;
}

function resolveTables(array $options, array $allTables): array
{
    if (!empty($options['tables'])) {
        return array_values(array_intersect($options['tables'], $allTables));
    }

    if ($options['all']) {
        return $allTables;
    }

    fwrite(STDOUT, "Available tables:" . PHP_EOL);
    foreach ($allTables as $index => $table) {
        fwrite(STDOUT, "  [" . ($index + 1) . "] {$table}" . PHP_EOL);
    }
    fwrite(STDOUT, "Enter a table number, name, or 'a' for all tables: ");
    $selection = trim(fgets(STDIN));

    if ($selection === '' || strtolower($selection) === 'a') {
        return $allTables;
    }

    if (ctype_digit($selection)) {
        $key = (int)$selection - 1;
        return isset($allTables[$key]) ? [$allTables[$key]] : [];
    }

    if (in_array($selection, $allTables, true)) {
        return [$selection];
    }

    fwrite(STDOUT, "No matching table for selection '{$selection}'." . PHP_EOL);
    return [];
}

function sanitizeIdentifier(string $name): string
{
    return preg_replace('/[^A-Za-z0-9_]/', '', $name);
}

function tableToModelName(string $table): string
{
    $table = str_replace(['-', '_'], ' ', strtolower($table));
    $parts = array_filter(array_map('trim', explode(' ', $table)));
    $studly = implode('', array_map(fn($part) => ucfirst($part), $parts));
    return $studly ?: ucfirst($table);
}

function buildColumnsBlock(array $columns): string
{
    $lines = [];
    foreach ($columns as $column) {
        $lines[] = '        ' . mapColumnToStatement($column);
    }

    $body = implode(PHP_EOL, $lines);
    if ($body !== '') {
        $body .= PHP_EOL;
    }

    return "        // @reut-columns:start" . PHP_EOL .
        $body .
        "        // @reut-columns:end";
}

function mapColumnToStatement(array $column): string
{
    $field = $column['Field'];
    $type = strtolower($column['Type']);
    $nullable = boolToCode($column['Null'] === 'YES');
    $isPrimary = boolToCode($column['Key'] === 'PRI');
    $autoIncrement = boolToCode(str_contains(strtolower((string)$column['Extra']), 'auto_increment'));
    $defaultCode = valueToCode($column['Default']);

    $class = null;
    $args = '';

    if (preg_match('/varchar\((\d+)\)/', $type, $match)) {
        $length = (int)$match[1];
        $class = '\\Reut\\DB\\Types\\Varchar';
        $args = "{$length}, {$nullable}, {$defaultCode}, {$isPrimary}";
    } elseif (str_contains($type, 'int')) {
        $class = '\\Reut\\DB\\Types\\Integer';
        $args = "{$nullable}, {$isPrimary}, {$autoIncrement}, {$defaultCode}";
    } elseif (str_contains($type, 'text')) {
        $class = '\\Reut\\DB\\Types\\Text';
        $args = "{$nullable}, {$defaultCode}, {$isPrimary}";
    } elseif (str_contains($type, 'json')) {
        $class = '\\Reut\\DB\\Types\\Json';
        $args = "{$nullable}, {$defaultCode}, {$isPrimary}";
    } elseif (str_contains($type, 'bool') || str_contains($type, 'tinyint(1)')) {
        $class = '\\Reut\\DB\\Types\\Boolean';
        $boolDefault = 'null';
        if ($column['Default'] !== null) {
            $boolDefault = boolToCode(in_array($column['Default'], ['1', 1, true], true));
        }
        $args = "{$nullable}, {$boolDefault}, {$isPrimary}";
    } elseif (str_contains($type, 'timestamp')) {
        $class = '\\Reut\\DB\\Types\\Timestamp';
        $usesCurrentDefault = boolToCode(strtoupper((string)$column['Default']) === 'CURRENT_TIMESTAMP');
        $onUpdate = boolToCode(str_contains(strtolower((string)$column['Extra']), 'on update'));
        $args = "{$nullable}, {$usesCurrentDefault}, {$onUpdate}, {$isPrimary}";
    } elseif (str_contains($type, 'datetime')) {
        $class = '\\Reut\\DB\\Types\\DateTimeType';
        $args = "{$nullable}, {$defaultCode}, {$isPrimary}";
    } elseif (str_contains($type, 'date')) {
        $class = '\\Reut\\DB\\Types\\Date';
        $args = "{$nullable}, {$defaultCode}, {$isPrimary}";
    } elseif (str_contains($type, 'decimal') || str_contains($type, 'numeric')) {
        $class = '\\Reut\\DB\\Types\\Decimal';
        $precision = 10;
        $scale = 2;
        if (preg_match('/\((\d+),(\d+)\)/', $type, $match)) {
            $precision = (int)$match[1];
            $scale = (int)$match[2];
        }
        $args = "{$precision}, {$scale}, {$nullable}, {$defaultCode}, {$isPrimary}";
    } elseif (str_contains($type, 'double')) {
        $class = '\\Reut\\DB\\Types\\DoubleType';
        $args = "{$nullable}, {$defaultCode}, {$isPrimary}";
    } elseif (str_contains($type, 'float')) {
        $class = '\\Reut\\DB\\Types\\FloatType';
        $args = "{$nullable}, {$defaultCode}, {$isPrimary}";
    } elseif (preg_match('/enum\((.+)\)/', $type, $match)) {
        $values = array_map(
            fn($item) => trim($item, " '"),
            explode(',', $match[1])
        );
        $valuesCode = '["' . implode('","', $values) . '"]';
        $class = '\\Reut\\DB\\Types\\EnumType';
        $args = "{$valuesCode}, {$nullable}, {$defaultCode}, {$isPrimary}";
    }

    if (!$class) {
        return "// TODO: Map '{$field}' ({$column['Type']}) manually";
    }

    return "\$this->addColumn('{$field}', new {$class}({$args}));";
}

function boolToCode(bool $value): string
{
    return $value ? 'true' : 'false';
}

function valueToCode(mixed $value): string
{
    if ($value === null) {
        return 'null';
    }

    if (is_string($value) && strtoupper($value) === 'CURRENT_TIMESTAMP') {
        return "'CURRENT_TIMESTAMP'";
    }

    return var_export($value, true);
}

function askConfirmation(string $question): bool
{
    fwrite(STDOUT, $question);
    $answer = strtolower(trim(fgets(STDIN)));
    return in_array($answer, ['y', 'yes'], true);
}

function updateModelColumns(string $modelPath, string $newBlock): bool
{
    $contents = file_get_contents($modelPath);
    if ($contents === false) {
        return false;
    }

    $startMarker = '// @reut-columns:start';
    $endMarker = '// @reut-columns:end';

    if (str_contains($contents, $startMarker) && str_contains($contents, $endMarker)) {
        $pattern = '/' . preg_quote($startMarker, '/') . '.*?' . preg_quote($endMarker, '/') . '/s';
        $updated = preg_replace($pattern, $newBlock, $contents, 1);
        if ($updated === null) {
            return false;
        }
        return file_put_contents($modelPath, $updated) !== false;
    }

    $sectionPattern = '/(\/\/ Define table columns[^\n]*\R)(.*?)(\s*\/\/ TODO: Define your relationships)/s';
    if (preg_match($sectionPattern, $contents)) {
        $updated = preg_replace_callback(
            $sectionPattern,
            fn($matches) => $matches[1] . $newBlock . PHP_EOL . $matches[3],
            $contents,
            1
        );
        if ($updated === null) {
            return false;
        }
        return file_put_contents($modelPath, $updated) !== false;
    }

    $parentPattern = '/(parent::__construct\(.*?\);\s*)/s';
    if (preg_match($parentPattern, $contents)) {
        $updated = preg_replace_callback(
            $parentPattern,
            fn($matches) => $matches[1] . PHP_EOL . $newBlock . PHP_EOL,
            $contents,
            1
        );
        if ($updated === null) {
            return false;
        }
        return file_put_contents($modelPath, $updated) !== false;
    }

    $contents .= PHP_EOL . $newBlock . PHP_EOL;
    return file_put_contents($modelPath, $contents) !== false;
}


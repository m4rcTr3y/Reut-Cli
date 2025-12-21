<?php
declare(strict_types=1);

/**
 * dev.php
 * Spins up the built-in PHP server for API development.
 * Usage: php manage.php dev [--host=0.0.0.0] [--port=9000] [--docroot=.]
 */
// Resolve project root (works when called from manage.php which sets REUT_PROJECT_ROOT)
$projectRoot = defined('REUT_PROJECT_ROOT') ? REUT_PROJECT_ROOT : getcwd();
$docRoot = $projectRoot; // Serve the project root by default
$routerDir = rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'devserver';
$routerFile = $routerDir . DIRECTORY_SEPARATOR . 'router.php';

ensureDevAssets($routerDir, $routerFile);

$options = parseDevOptions($argv ?? []);
$host = $options['host'] ?? '0.0.0.0';
$port = (string)($options['port'] ?? '9000');
$docRoot = realpath($options['docroot'] ?? $docRoot) ?: $docRoot;
$address = "{$host}:{$port}";

if (!file_exists($docRoot . '/index.php')) {
    fwrite(STDERR, "Cannot find index.php in {$docRoot}. Aborting dev server.\n");
    exit(1);
}

echo "Starting REUT dev server on http://{$address}\n";
echo "Document root: {$docRoot}\n";
echo "Press CTRL+C to stop.\n\n";

$command = sprintf(
    '%s -S %s -t %s %s',
    escapeshellarg(PHP_BINARY),
    escapeshellarg($address),
    escapeshellarg($docRoot),
    escapeshellarg($routerFile)
);

passthru($command);

/**
 * Parse host/port/docroot flags from CLI.
 */
function parseDevOptions(array $argv): array
{
    $options = [];
    foreach ($argv as $arg) {
        if (strpos($arg, '--host=') === 0) {
            $options['host'] = substr($arg, 7);
        } elseif (strpos($arg, '--port=') === 0) {
            $options['port'] = (int)substr($arg, 7);
        } elseif (strpos($arg, '--docroot=') === 0) {
            $options['docroot'] = substr($arg, 10);
        }
    }
    return $options;
}

/**
 * Ensure router scaffold exists for projects created before the dev command.
 */
function ensureDevAssets(string $routerDir, string $routerFile): void
{
    if (!is_dir($routerDir)) {
        mkdir($routerDir, 0755, true);
    }

    if (!file_exists($routerFile)) {
        file_put_contents($routerFile, getRouterTemplate());
    }
}

function getRouterTemplate(): string
{
    return <<<'PHP'
<?php
declare(strict_types=1);

$requested = $_SERVER['REQUEST_URI'] ?? '/';
$docRoot = getcwd();
$file = realpath($docRoot . parse_url($requested, PHP_URL_PATH));
$docRootLength = strlen($docRoot);

if (
    $file &&
    is_file($file) &&
    strncmp($file, $docRoot, $docRootLength) === 0
) {
    return false;
}

require $docRoot . '/index.php';
PHP;
}


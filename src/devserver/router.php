<?php
declare(strict_types=1);

/**
 * Simple router for the built-in PHP dev server.
 *
 * - Serves static assets directly when they exist.
 * - Falls back to project root index.php for everything else.
 */

$requested = $_SERVER['REQUEST_URI'] ?? '/';
$docRoot = getcwd();
$file = realpath($docRoot . parse_url($requested, PHP_URL_PATH));
$docRootLength = strlen($docRoot);

if (
    $file &&
    is_file($file) &&
    strncmp($file, $docRoot, $docRootLength) === 0
) {
    return false; // Let the built-in server handle static assets
}

require $docRoot . '/index.php';


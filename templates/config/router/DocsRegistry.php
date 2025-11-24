<?php
declare(strict_types=1);

namespace Reut\Router;

final class DocsRegistry
{
    private static array $entries = [];

    public static function add(array $entry): void
    {
        self::$entries[] = [
            'group' => $entry['group'] ?? '',
            'method' => strtoupper($entry['method'] ?? 'GET'),
            'path' => $entry['path'] ?? '/',
            'description' => $entry['description'] ?? '',
            'requiresAuth' => (bool)($entry['requiresAuth'] ?? false),
        ];
    }

    public static function all(): array
    {
        return self::$entries;
    }
}


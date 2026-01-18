<?php
declare(strict_types=1);

namespace Reut\CLI\Help;

/**
 * Command examples database
 */
class Examples
{
    private static array $examples = [
        'init' => [
            'Reut init',
            'Reut init myproject',
        ],
        'migrate' => [
            'Reut migrate',
            'Reut migrate --dry-run',
            'Reut create',
        ],
        'status' => [
            'Reut status',
            'Reut status --json',
            'Reut status --summary',
            'Reut status --table=users',
        ],
        'rollback' => [
            'Reut rollback',
            'Reut rollback --batch=2',
            'Reut rollback --migration=create_users_table_20240101120000',
            'Reut rollback --dry-run',
        ],
        'generate:model' => [
            'Reut generate:model Users',
            'Reut generate:model Posts --force',
        ],
        'generate:routes' => [
            'Reut generate:routes',
        ],
        'dev' => [
            'Reut dev',
            'Reut dev --port=9000',
            'Reut dev --host=0.0.0.0 --port=8080',
        ],
        'view' => [
            'Reut view',
            'Reut view --port=8088',
        ],
        'inspect' => [
            'Reut inspect',
            'Reut inspect --table=users',
            'Reut inspect --all',
            'Reut inspect --apply',
        ],
        'sync' => [
            'Reut sync',
            'Reut sync --dry-run',
        ],
        'validate-migrations' => [
            'Reut validate-migrations',
        ],
        'export-migrations' => [
            'Reut export-migrations',
            'Reut export-migrations --format=sql',
        ],
        'import-migrations' => [
            'Reut import-migrations migrations.json',
            'Reut import-migrations migrations.sql',
        ],
        'update' => [
            'Reut update',
        ],
    ];

    /**
     * Get examples for a command
     */
    public static function get(string $command): array
    {
        return self::$examples[$command] ?? [];
    }

    /**
     * Get all examples
     */
    public static function all(): array
    {
        return self::$examples;
    }

    /**
     * Add examples for a command
     */
    public static function add(string $command, array $examples): void
    {
        if (!isset(self::$examples[$command])) {
            self::$examples[$command] = [];
        }
        self::$examples[$command] = array_merge(self::$examples[$command], $examples);
    }
}



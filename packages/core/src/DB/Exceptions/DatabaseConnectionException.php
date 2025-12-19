<?php
declare(strict_types=1);

namespace Reut\DB\Exceptions;

/**
 * Exception thrown when database connection fails
 */
class DatabaseConnectionException extends ReutException
{
    public function __construct(
        string $message = "Failed to connect to database",
        int $code = 0,
        ?\Throwable $previous = null,
        array $config = []
    ) {
        // Remove password from context for security
        $safeConfig = $config;
        if (isset($safeConfig['password'])) {
            $safeConfig['password'] = '***';
        }
        
        $suggestion = "Please check:\n" .
            "1. Database server is running\n" .
            "2. Host, username, and database name are correct\n" .
            "3. User has proper permissions\n" .
            "4. Firewall allows connections";
        
        parent::__construct(
            $message,
            $code,
            $previous,
            [
                'host' => $config['host'] ?? 'unknown',
                'database' => $config['dbname'] ?? 'unknown',
                'username' => $config['username'] ?? 'unknown',
            ],
            $suggestion
        );
    }
}


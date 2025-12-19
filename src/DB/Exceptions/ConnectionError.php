<?php
declare(strict_types=1);

namespace Reut\DB\Exceptions;

/**
 * ConnectionError - Legacy exception class
 * @deprecated Use DatabaseConnectionException instead
 */
class ConnectionError extends DatabaseConnectionException
{
    public function __construct($message = "Failed to connect to database", $code = 0, ?\Throwable $previous = null, array $config = []) {
        parent::__construct($message, $code, $previous, $config);
    }

    /**
     * Legacy method for backward compatibility
     * @deprecated
     */
    public function getCustomInfo() {
        return "Database connection error: " . $this->getMessage();
    }
}
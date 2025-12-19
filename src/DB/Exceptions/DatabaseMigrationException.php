<?php
declare(strict_types=1);

namespace Reut\DB\Exceptions;

/**
 * Exception thrown when migration operations fail
 */
class DatabaseMigrationException extends ReutException
{
    protected ?string $migrationName = null;
    protected ?string $tableName = null;
    protected ?string $sql = null;

    public function __construct(
        string $message = "Migration failed",
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $migrationName = null,
        ?string $tableName = null,
        ?string $sql = null
    ) {
        $this->migrationName = $migrationName;
        $this->tableName = $tableName;
        $this->sql = $sql;
        
        $context = [];
        if ($migrationName) {
            $context['migration'] = $migrationName;
        }
        if ($tableName) {
            $context['table'] = $tableName;
        }
        if ($sql) {
            // Truncate long SQL for readability
            $context['sql'] = strlen($sql) > 200 ? substr($sql, 0, 200) . '...' : $sql;
        }
        
        $suggestion = "Migration troubleshooting:\n" .
            "1. Check if the table/model exists\n" .
            "2. Verify SQL syntax is correct\n" .
            "3. Check database permissions\n" .
            "4. Review migration history: `Reut status`\n" .
            "5. Check for conflicting migrations";
        
        parent::__construct($message, $code, $previous, $context, $suggestion);
    }

    /**
     * Get migration name
     */
    public function getMigrationName(): ?string
    {
        return $this->migrationName;
    }

    /**
     * Get table name
     */
    public function getTableName(): ?string
    {
        return $this->tableName;
    }

    /**
     * Get SQL that failed
     */
    public function getSql(): ?string
    {
        return $this->sql;
    }
}


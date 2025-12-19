<?php
declare(strict_types=1);

namespace Reut\DB\Exceptions;

/**
 * Exception thrown when a database query fails
 */
class DatabaseQueryException extends ReutException
{
    protected ?string $query = null;
    protected array $queryParams = [];

    public function __construct(
        string $message = "Database query failed",
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $query = null,
        array $queryParams = [],
        array $errorInfo = []
    ) {
        $this->query = $query;
        $this->queryParams = $queryParams;
        
        $context = [];
        if ($query) {
            // Truncate long queries for readability
            $context['query'] = strlen($query) > 200 ? substr($query, 0, 200) . '...' : $query;
        }
        if (!empty($queryParams)) {
            // Don't expose all params if there are many
            $context['params_count'] = count($queryParams);
            if (count($queryParams) <= 5) {
                $context['params'] = $queryParams;
            }
        }
        if (!empty($errorInfo)) {
            $context['sql_state'] = $errorInfo[0] ?? null;
            $context['error_code'] = $errorInfo[1] ?? null;
            $context['error_message'] = $errorInfo[2] ?? null;
        }
        
        $suggestion = $this->generateSuggestion($errorInfo, $query);
        
        parent::__construct($message, $code, $previous, $context, $suggestion);
    }

    /**
     * Get the SQL query that failed
     */
    public function getQuery(): ?string
    {
        return $this->query;
    }

    /**
     * Get query parameters
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * Generate helpful suggestion based on error type
     */
    protected function generateSuggestion(array $errorInfo, ?string $query): string
    {
        $sqlState = $errorInfo[0] ?? '';
        $errorCode = $errorInfo[1] ?? 0;
        
        $suggestions = [];
        
        // SQL syntax errors
        if ($sqlState === '42000' || $errorCode == 1064) {
            $suggestions[] = "Check SQL syntax - verify table/column names are correct";
            $suggestions[] = "Ensure all required keywords (SELECT, FROM, WHERE, etc.) are present";
        }
        
        // Table doesn't exist
        if ($sqlState === '42S02' || $errorCode == 1146) {
            $suggestions[] = "Table does not exist - run migrations: `Reut migrate`";
            $suggestions[] = "Check table name spelling and case sensitivity";
        }
        
        // Column doesn't exist
        if ($sqlState === '42S22' || $errorCode == 1054) {
            $suggestions[] = "Column does not exist - check column name spelling";
            $suggestions[] = "Add missing column to model and run: `Reut migrate`";
        }
        
        // Duplicate entry
        if ($sqlState === '23000' || $errorCode == 1062) {
            $suggestions[] = "Duplicate entry detected - check unique constraints";
            $suggestions[] = "Verify the data being inserted doesn't violate uniqueness";
        }
        
        // Access denied
        if ($sqlState === '28000' || $errorCode == 1045) {
            $suggestions[] = "Access denied - check database credentials";
            $suggestions[] = "Verify user has necessary permissions";
        }
        
        if (empty($suggestions)) {
            return "Review the error message and check your query syntax and database state";
        }
        
        return implode("\n", $suggestions);
    }
}


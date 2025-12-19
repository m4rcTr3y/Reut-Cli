<?php
declare(strict_types=1);

namespace Reut\DB\Exceptions;

/**
 * Base exception class for REUT framework
 * Provides enhanced error reporting with context and suggestions
 */
class ReutException extends \Exception
{
    protected array $context = [];
    protected ?string $suggestion = null;

    public function __construct(
        string $message = "",
        int $code = 0,
        ?\Throwable $previous = null,
        array $context = [],
        ?string $suggestion = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
        $this->suggestion = $suggestion;
    }

    /**
     * Get additional context information
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get suggestion for resolving the error
     */
    public function getSuggestion(): ?string
    {
        return $this->suggestion;
    }

    /**
     * Get formatted error message with context
     */
    public function getFormattedMessage(): string
    {
        $message = $this->getMessage();
        
        if (!empty($this->context)) {
            $contextStr = [];
            foreach ($this->context as $key => $value) {
                if (is_scalar($value)) {
                    $contextStr[] = "$key: $value";
                } elseif (is_array($value)) {
                    $contextStr[] = "$key: " . json_encode($value);
                }
            }
            if (!empty($contextStr)) {
                $message .= "\nContext: " . implode(", ", $contextStr);
            }
        }
        
        if ($this->suggestion) {
            $message .= "\nSuggestion: " . $this->suggestion;
        }
        
        return $message;
    }

    /**
     * Convert to array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'type' => static::class,
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'context' => $this->context,
            'suggestion' => $this->suggestion,
            'trace' => $this->getTraceAsString(),
        ];
    }
}


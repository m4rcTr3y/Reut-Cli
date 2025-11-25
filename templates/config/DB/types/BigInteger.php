<?php
declare(strict_types=1);

namespace Reut\DB\Types;

/**
 * Class BigInteger
 * Represents a BIGINT column definition.
 */
class BigInteger extends ColumnType
{
    public function __construct(
        bool $nullable = true,
        bool $isPrimary = false,
        bool $autoIncrement = false,
        int|string|null $default = null,
        bool $unsigned = false
    ) {
        parent::__construct($nullable, $default, $isPrimary, $autoIncrement);
        $this->name = $unsigned ? "BIGINT UNSIGNED" : "BIGINT";
    }
}


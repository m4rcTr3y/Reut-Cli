<?php
declare(strict_types=1);

namespace Reut\DB\Types;

/**
 * Class SmallInteger
 * Represents a SMALLINT column definition.
 */
class SmallInteger extends ColumnType
{
    public function __construct(
        bool $nullable = true,
        bool $isPrimary = false,
        bool $autoIncrement = false,
        int|string|null $default = null,
        bool $unsigned = false
    ) {
        parent::__construct($nullable, $default, $isPrimary, $autoIncrement);
        $this->name = $unsigned ? "SMALLINT UNSIGNED" : "SMALLINT";
    }
}


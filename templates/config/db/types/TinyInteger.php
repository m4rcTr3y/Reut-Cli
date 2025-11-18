<?php
declare(strict_types=1);

namespace Reut\DB\Types;

/**
 * Class TinyInteger
 * Represents a TINYINT column definition.
 */
class TinyInteger extends ColumnType
{
    public function __construct(
        bool $nullable = true,
        bool $isPrimary = false,
        bool $autoIncrement = false,
        int|string|null $default = null,
        bool $unsigned = false
    ) {
        parent::__construct($nullable, $default, $isPrimary, $autoIncrement);
        $this->name = $unsigned ? "TINYINT UNSIGNED" : "TINYINT";
    }
}


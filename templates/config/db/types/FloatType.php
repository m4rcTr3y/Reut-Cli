<?php
declare(strict_types=1);

namespace Reut\DB\Types;

/**
 * Class FloatType
 * Represents a FLOAT column definition.
 */
class FloatType extends ColumnType
{
    public function __construct(
        bool $nullable = true,
        float|int|string|null $default = null,
        bool $isPrimary = false
    ) {
        parent::__construct($nullable, $default, $isPrimary);
        $this->name = "FLOAT";
    }
}


<?php
declare(strict_types=1);

namespace Reut\DB\Types;

/**
 * Class DoubleType
 * Represents a DOUBLE column definition.
 */
class DoubleType extends ColumnType
{
    public function __construct(
        bool $nullable = true,
        float|int|string|null $default = null,
        bool $isPrimary = false
    ) {
        parent::__construct($nullable, $default, $isPrimary);
        $this->name = "DOUBLE";
    }
}


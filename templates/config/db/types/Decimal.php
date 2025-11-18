<?php
declare(strict_types=1);

namespace Reut\DB\Types;

/**
 * Class Decimal
 * Represents a DECIMAL column definition.
 */
class Decimal extends ColumnType
{
    public function __construct(
        int $precision = 10,
        int $scale = 2,
        bool $nullable = true,
        float|int|string|null $default = null,
        bool $isPrimary = false
    ) {
        parent::__construct($nullable, $default, $isPrimary);
        $precision = max($precision, 1);
        $scale = max(min($scale, $precision), 0);
        $this->name = "DECIMAL($precision,$scale)";
    }
}


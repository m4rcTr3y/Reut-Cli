<?php
declare(strict_types=1);

namespace Reut\DB\Types;

/**
 * Class DateTimeType
 * Represents a DATETIME column definition.
 */
class DateTimeType extends ColumnType
{
    public function __construct(
        bool $nullable = true,
        string|\DateTimeInterface|null $default = null,
        bool $isPrimary = false
    ) {
        parent::__construct($nullable, $default, $isPrimary);
        $this->name = "DATETIME";
    }
}


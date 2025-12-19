<?php
declare(strict_types=1);

namespace Reut\DB\Types;

/**
 * Class Json
 * Represents a JSON column definition.
 */
class Json extends ColumnType
{
    public function __construct(
        bool $nullable = true,
        string|null $default = null,
        bool $isPrimary = false
    ) {
        parent::__construct($nullable, $default, $isPrimary);
        $this->name = "JSON";
    }
}


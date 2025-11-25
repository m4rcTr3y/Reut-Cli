<?php
declare(strict_types=1);

namespace Reut\DB\Types;

/**
 * Class Blob
 * Represents a BLOB column definition.
 */
class Blob extends ColumnType
{
    public function __construct(
        bool $nullable = true,
        bool $isPrimary = false
    ) {
        parent::__construct($nullable, null, $isPrimary);
        $this->name = "BLOB";
    }
}


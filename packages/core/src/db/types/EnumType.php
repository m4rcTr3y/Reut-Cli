<?php
declare(strict_types=1);

namespace Reut\DB\Types;

/**
 * Class EnumType
 * Represents an ENUM column definition.
 */
class EnumType extends ColumnType
{
    protected array $allowedValues;

    public function __construct(
        array $allowedValues,
        bool $nullable = true,
        ?string $default = null,
        bool $isPrimary = false
    ) {
        if (empty($allowedValues)) {
            throw new \InvalidArgumentException('EnumType requires at least one allowed value.');
        }

        $this->allowedValues = array_map(
            fn ($value) => addslashes((string) $value),
            $allowedValues
        );

        if ($default !== null && !in_array($default, $allowedValues, true)) {
            throw new \InvalidArgumentException('Default value must be one of the allowed enum values.');
        }

        parent::__construct($nullable, $default, $isPrimary);
        $this->name = "ENUM(" . implode(', ', array_map(fn ($value) => "'$value'", $this->allowedValues)) . ")";
    }
}


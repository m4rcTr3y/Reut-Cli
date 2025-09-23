<?php
declare(strict_types=1);

namespace Reut\DB\Types;

/**
 * Class Date
 * this creates a DATE() field in sql
 *@param \string $nullable default to false is the field is nullable or set to true
 *@param \string $default the default value to be set in the column
 *@param \bool $isPrimary default to false if a feild is not primary key or set to true if is
 *
 */
class Date extends ColumnType{
    public function __construct(bool $nullable = true, $default = null,bool $isPrimay=false){
        parent::__construct($nullable,$default,$isPrimay);
        $this->name = "DATE";
    }
}
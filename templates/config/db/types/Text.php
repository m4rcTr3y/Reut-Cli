<?php
declare(strict_types=1);

namespace Reut\DB\Types;

/**
 * Class Text
 * this creates a Text field in sql
 *@param \string $nullable default to false is the field is nullable or set to true
 *@param \string $default the default value to be set in the column
 *@param \bool $isPrimary default to false if a feild is not primary key or set to true if is
 *
 */
 
class Text extends ColumnType{
    public function __construct($nullable = true, $default = null,$isPrimay=false){
        parent::__construct($nullable,$default,$isPrimay);
        $this->name = "TEXT";
    }
}
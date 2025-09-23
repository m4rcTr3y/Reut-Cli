<?php
declare(strict_types=1);

namespace Reut\DB\Types;


/**
 * Class Varchar
 * this creates a VARCHAR() field in sql
 *@param int $length
 *@param string $nullable default to false is the field is nullable or set to true
 *@param string $default the default value to be set in the column
 *@param bool $isPrimary default to false if a feild is not primary key or set to true if is
 *
 */
 
class Varchar extends ColumnType{
    public function __construct(int $length=255,bool $nullable = true,string|null $default = '',bool $isPrimay=false){
        parent::__construct($nullable,$default,$isPrimay);
        $this->name = "VARCHAR($length)";
    }
}

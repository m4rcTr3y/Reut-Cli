<?php

declare(strict_types=1);

namespace Reut\DB\Types;

/**
 * Class Integer
 * this creates a INTEGER field in sql
 *
 *@package Reut\DB\Types\Integer
 *
 *@param string $nullable default to false is the field is nullable or set to true
 *@param bool $isPrimary default to false if a feild is not primary key or set to true if is
 *@param bool $autoIncrement defaults to false of feild is not set to auto increment or set to true
 *@param string $default the default value to be set in the column
 *
 */



class Integer extends ColumnType
{
    public function __construct(bool $nullable = true, bool $isPrimay = false, bool $autoIncrement = false, string|null $default)
    {
        parent::__construct(
            $nullable, 
            $default, 
            $isPrimay, 
            $autoIncrement);
        $this->name = "INTEGER";
    }
}

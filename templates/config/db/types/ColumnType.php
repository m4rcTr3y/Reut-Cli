<?php
declare(strict_types=1);

namespace Reut\DB\Types;

/** 
* Class ColumnType 
* this provides the base interface / class to build other sql column types
*
*@package Reut\DB\ColumnTypes
*
*@param \string $nullable default to false is the field is nullable or set to true
*@param \string $default the default value to be set in the column
*@param \string $name the name of the field as column name
*@param \bool $isPrimary default to false if a feild is not primary key or set to true if is
*@param \bool $autoIncrement defaults to false of feild is not set to auto increment or set to true
*
*/

abstract class ColumnType{

    protected $name;
    protected $nullable;
    protected  $default;
    protected $autoIncrement;
    protected $isPrimary;

    public function __construct(bool $nullable=true,string|null $default = null,bool $isPrimary=false,bool $autoIncrement=false){
        $this->nullable = $nullable;
        $this->default = $default;
        $this->isPrimary = $isPrimary;
        $this->autoIncrement = $autoIncrement;
    }

    /**
     * returns the Sql of the field, the returned is a string
     *@return string;
     */
    public function getSql(){
        $baseSql = $this->name;
        if(!$this->nullable){
            $baseSql .= " NOT NULL";
        }
        if($this->isPrimary){
            $baseSql .= " PRIMARY KEY";
        }
        if($this->autoIncrement){
            $baseSql .= " AUTO_INCREMENT";
        }
        if($this->default !== null){
            $baseSql.= " DEFAULT ".(is_string($this->default)?"'{$this->default}" : $this->default);
        }

        return $baseSql;
    }

    /**
     * If the column or field is a primary key
     * @return bool
     */
    public function isPrimaryKey(){
        return $this->isPrimary;
    }

}


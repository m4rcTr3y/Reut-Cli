<?php
namespace Reut\DB\Exceptions;

class ConnectionError extends \Exception{
    public function __construct($message, $code = 0) {
            parent::__construct($message, $code);
        }

        public function getCustomInfo() {
            return "This is a custom error related to: " . $this->getMessage();
        }
}
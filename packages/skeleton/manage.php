<?php

if (!defined('REUT_PROJECT_ROOT')) {
    define('REUT_PROJECT_ROOT', __DIR__);
}

chdir(REUT_PROJECT_ROOT);

require REUT_PROJECT_ROOT . '/vendor/autoload.php';

use Reut\DB\Creator\DatabaseCreator;

DatabaseCreator::Generate();


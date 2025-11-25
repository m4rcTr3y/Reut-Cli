<?php

if (!defined('REUT_PROJECT_ROOT')) {
    define('REUT_PROJECT_ROOT', __DIR__);
}

chdir(REUT_PROJECT_ROOT);

$autoload = REUT_PROJECT_ROOT . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    fwrite(STDERR, "vendor/autoload.php not found. Run `composer install` inside this project before using the CLI.\n");
    exit(1);
}

require $autoload;

use Reut\DB\Creator\DatabaseCreator;

if (!class_exists(DatabaseCreator::class)) {
    fwrite(STDERR, "Composer dependencies are missing. Run `composer install` to install reut/core and other packages.\n");
    exit(1);
}

DatabaseCreator::Generate();


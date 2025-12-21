<?php

return <<<PHP
<?php

if (!defined('REUT_PROJECT_ROOT')) {
    define('REUT_PROJECT_ROOT', __DIR__);
}

require REUT_PROJECT_ROOT . '/vendor/autoload.php';
use Dotenv\Dotenv;


\$dotenv = Dotenv::createImmutable(REUT_PROJECT_ROOT);
\$dotenv->load();

\$username = \$_ENV['DB_USERNAME'];
\$password = \$_ENV['DB_PASSWORD'];
\$database = \$_ENV['DB_NAME'];

// this is the config required for the database connection
\$config = [
    'driver' => \$_ENV['DB_TYPE'] ?? 'mysql',
    'host' => \$_ENV['DB_HOST'] ?? 'localhost',
    'username' => \$_ENV['DB_USERNAME'],
    'password' => \$_ENV['DB_PASSWORD'],
    'dbname' => \$_ENV['DB_NAME'],
    'port' => \$_ENV['DB_PORT'] ?? null,
];

PHP;
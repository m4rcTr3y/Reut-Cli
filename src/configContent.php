<?php

return <<<PHP
<?php

require __DIR__ . '/vendor/autoload.php';
use Dotenv\Dotenv;


\$dotenv = Dotenv::createImmutable(__DIR__);
\$dotenv->load();

\$username = \$_ENV['DB_USERNAME'];
\$password = \$_ENV['DB_PASSWORD'];
\$database = \$_ENV['DB_NAME'];

// this is the config required for the database connection
\$config = [
    'host' => 'localhost',
    'username' => \$_ENV['DB_USERNAME'],
    'password' => \$_ENV['DB_PASSWORD'],
    'dbname' =>  \$_ENV['DB_NAME']
];

PHP;
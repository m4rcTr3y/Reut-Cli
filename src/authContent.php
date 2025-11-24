<?php

return <<<PHP
<?php

require __DIR__ . '/vendor/autoload.php';
use Dotenv\Dotenv;

\$dotenv = Dotenv::createImmutable(__DIR__);
\$dotenv->load();

// Authentication configuration
\$authConfig = [
    // Table name to use for authentication (default: 'Users')
    'table' => \$_ENV['AUTH_TABLE'] ?? 'Users',
    
    // Field names in the auth table
    'fields' => [
        'identifier' => \$_ENV['AUTH_IDENTIFIER_FIELD'] ?? 'email',  // 'email' or 'username'
        'password' => \$_ENV['AUTH_PASSWORD_FIELD'] ?? 'password',
        'id' => \$_ENV['AUTH_ID_FIELD'] ?? 'id',
    ],
    
    // Auto-create auth table if it doesn't exist
    'auto_create_table' => filter_var(\$_ENV['AUTH_AUTO_CREATE'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
    
    // JWT token expiry (in seconds)
    'token_expiry' => (int)(\$_ENV['AUTH_TOKEN_EXPIRY'] ?? 3600), // 1 hour default
    
    // Refresh token expiry (in days)
    'refresh_token_expiry' => (int)(\$_ENV['AUTH_REFRESH_EXPIRY'] ?? 7), // 7 days default
];

return \$authConfig;

PHP;


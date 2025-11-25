<?php

return [
    'table' => $_ENV['AUTH_TABLE'] ?? 'Users',
    'fields' => [
        'identifier' => $_ENV['AUTH_IDENTIFIER_FIELD'] ?? 'email',
        'password' => $_ENV['AUTH_PASSWORD_FIELD'] ?? 'password',
        'id' => $_ENV['AUTH_ID_FIELD'] ?? 'id',
    ],
    'auto_create_table' => filter_var($_ENV['AUTH_AUTO_CREATE'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
    'token_expiry' => (int) ($_ENV['AUTH_TOKEN_EXPIRY'] ?? 3600),
    'refresh_token_expiry' => (int) ($_ENV['AUTH_REFRESH_EXPIRY'] ?? 7),
];


<?php

return <<<PHP
<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

use Slim\Factory\AppFactory;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

\$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
\$dotenv->load();

\$app = AppFactory::create();


\$app->addRoutingMiddleware();                    
\$app->addBodyParsingMiddleware();                

\$app->add(function (Request \$request, \$handler) {
    \$method = \$request->getMethod();

    
    if (in_array(\$method, ['PUT', 'PATCH', 'DELETE'])) {
        \$contentType = \$request->getHeaderLine('Content-Type');

      
        if (str_contains(\$contentType, 'application/json')) {
            \$body = (string) \$request->getBody();
            if (\$body !== '') {
                \$data = json_decode(\$body, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    
                    \$request = \$request->withParsedBody(\$data);
                }
            }
        }
        elseif (str_contains(\$contentType, 'application/x-www-form-urlencoded')) {
            \$request = \$request->withParsedBody(\$request->getQueryParams());
        }
    }

    return \$handler->handle(\$request);
});


\$registerRoutes = require __DIR__ . '/routers/routes.php';
\$registerRoutes(\$app, \$config);


\$displayErrorDetails = (\$_ENV['APP_ENV'] ?? 'production') === 'development';

\$customErrorHandler = function (Request \$request, Throwable \$exception, bool \$displayErrorDetails) use (\$app) {
    \$payload = [
        'error'   => true,
        'message' => \$exception->getMessage(),
        'code'    => \$exception->getCode(),
        'file'    => \$exception->getFile(),
        'line'    => \$exception->getLine(),
    ];

    if (\$displayErrorDetails) {
        \$payload['trace'] = \$exception->getTrace();
    }

    \$response = \$app->getResponseFactory()->createResponse();
    \$response->getBody()->write(json_encode(\$payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    \$status = \$exception instanceof \Slim\Exception\HttpException ? \$exception->getCode() : 500;

    return \$response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(\$status);
};

\$notFoundHandler = function (Request \$request) {
    \$payload = [
        'error'   => true,
        'message' => 'Route not found',
        'path'    => \$request->getUri()->getPath(),
        'method'  => \$request->getMethod(),
    ];
    \$response = new \Slim\Psr7\Response();
    \$response->getBody()->write(json_encode(\$payload, JSON_PRETTY_PRINT));
    return \$response->withHeader('Content-Type', 'application/json')->withStatus(404);
};

\$errorMiddleware = \$app->addErrorMiddleware(\$displayErrorDetails, true, true);
\$errorMiddleware->setDefaultErrorHandler(\$customErrorHandler);
\$errorMiddleware->setErrorHandler(HttpNotFoundException::class, \$notFoundHandler);


\$app->add(function (Request \$request, \$handler) {
    \$response = \$handler->handle(\$request);

    \$response = \$response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
        ->withHeader('Access-Control-Allow-Credentials', 'true');

    if (\$request->getMethod() === 'OPTIONS') {
        return \$response->withStatus(204);
    }

    return \$response;
});

\$app->run();

PHP;
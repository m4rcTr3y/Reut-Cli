<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Reut\Auth\AuthRouter;
use Reut\Router\DocsController;
use Reut\Router\SchemaController;
use Reut\Router\ReuteRoute;
use Slim\App;

return function (App $app, array $config): void {
    if ((strtolower($_ENV['REUT_AUTH_ENABLED'] ?? 'true')) === 'true') {
        $authConfig = require __DIR__ . '/../auth.php';
        new AuthRouter($app, $config, $authConfig);
    }

    if ((strtolower($_ENV['REUT_DOCS_ENABLED'] ?? 'true')) === 'true') {
        $app->get('/docs', [DocsController::class, 'index']);
    }

    // Schema viewer - disabled in production by default
    if ((strtolower($_ENV['REUT_SCHEMA_ENABLED'] ?? 'true')) === 'true') {
        $app->get('/schema', [SchemaController::class, 'index']);
    }

    $routes = ReuteRoute::use($app);

    $routes->get('/health', function (Request $request, Response $response) {
        $response->getBody()->write(json_encode([
            'status' => 'ok',
            'timestamp' => time(),
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }, 'Service healthcheck');
};


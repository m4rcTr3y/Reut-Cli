<?php

return <<<PHP
<?php
declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';
require __DIR__.'/config.php';
use Slim\Psr7\Response as SlimResponse;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Exception\HttpNotFoundException;
use Dotenv\Dotenv;


\$dotenv = Dotenv::createImmutable(__DIR__);

\$app = AppFactory::create();
\$app->addBodyParsingMiddleware();

//Register all routes to the app so as to be accesible
\$registerRoutes = require __DIR__.'/routers/routes.php';
\$registerRoutes(\$app,\$config);




//add headers
date_default_timezone_set('Africa/Nairobi');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers:Access-Control-Allow-Headers,Content-Type,X-Requested-With,Authorization,Access-Control-Allow-Methods');


//error handler 
\$errorHandler = \$app->addErrorMiddleware(true, true, true);
\$errorHandler->setErrorHandler(HttpNotFoundException::class,function(Request \$request,Throwable \$exception,bool \$displayErrorDetails){
    \$response = new SlimResponse();
    \$response->getBody()->write('notfound');

    return \$response->withStatus(404);
});

//enable options requests for all routes
\$app->options('/{routes:.+}', function (Request \$request,  \$response) {
    return \$response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Credentials', 'true')
        ->withStatus(204);
});



\$app->run();

PHP;
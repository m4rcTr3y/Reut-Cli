<?php
declare(strict_types=1);

use Reut\Support\ProjectPath;

function RegisterRoutes(string $configDir, string $routersDir)
{
    // Load environment variables
    $projectRoot = ProjectPath::resolve('.');
    if (file_exists($projectRoot . '/.env') && class_exists('\Dotenv\Dotenv')) {
        $dotenv = \Dotenv\Dotenv::createImmutable($projectRoot);
        $dotenv->safeLoad();
    }

    // Get auth model name from environment variable (defaults to 'Users')
    $authModelName = $_ENV['AUTH_TABLE'] ?? 'Users';
    $authRouterName = $authModelName . 'Router';

    $configDir = rtrim($configDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $routersDir = rtrim($routersDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    if (!is_dir($configDir)) {
        mkdir($configDir, 0755, true);
    }


    $routesFile = $configDir . 'routes.php';

    // Scan routers directory for *Router.php files
    $routerFiles = glob($routersDir . '*Router.php');
    $routerClasses = [];

    foreach ($routerFiles as $file) {
        // Extract router class name (e.g., UsersRouter from UsersRouter.php)
        $routerName = str_replace('.php', '', basename($file));
        
        // Skip auth model router - don't register it
        if ($routerName === $authRouterName) {
            echo "Skipping auth router registration: $routerName (configured in AUTH_TABLE)\n";
            continue;
        }
        
        $routerClasses[] = $routerName;
    }

    // Check if routes.php exists and if all routers are registered
    $missingRouters = [];
    if (file_exists($routesFile)) {
        $routesContent = file_get_contents($routesFile);
        foreach ($routerClasses as $router) {
            if (
                strpos($routesContent, "use Reut\\Routers\\{$router};") === false ||
                strpos($routesContent, "{$router}::register(\$app);") === false
            ) {
                $missingRouters[] = $router;
            }
        }
    } else {
        // If routes.php doesn't exist, all routers are considered missing
        $missingRouters = $routerClasses;
    }

  

    // Generate routes.php content for model routers (e.g., ProductsRouter, OrdersRouter)
    // Note: AuthRouter (built-in auth routes) is registered separately below and is NOT affected by the filtering above
    $uses = '';
    $registers = '';
    foreach ($routerClasses as $router) {
        $uses .= "use Reut\\Routers\\{$router};\n";
        $lowercaseName = '$'.strtolower($router).'var';
        $registers .=" new {$router}(\$app,\$config);\n";
    }

    // Register built-in AuthRouter (provides default auth routes like /login, /register, etc.)
    // This is separate from model routers and will always be registered if auth.php exists
    // Check if auth is enabled
    $authEnabled = "(\$_ENV['REUT_AUTH_ENABLED'] ?? 'true') === 'true'";
    $authUses = '';
    $authRegisters = '';
    
    if (file_exists(ProjectPath::resolve('auth.php'))) {
        $authUses = "use Reut\\Auth\\AuthRouter;\n";
        $authRegisters = "
        // Register built-in authentication routes
        if ({$authEnabled}) {
            \$authConfig = require __DIR__ . '/../auth.php';
            new AuthRouter(\$app, \$config, \$authConfig);
        }
        ";
    }

    // Check if docs are enabled
    $docsEnabled = "(\$_ENV['REUT_DOCS_ENABLED'] ?? 'true') === 'true'";
    $docsUses = "use Reut\\Router\\DocsController;\n";
    $docsRegisters = "
        // Register API documentation endpoint
        if ({$docsEnabled}) {
            \$app->get('/docs', [DocsController::class, 'index']);
        }
        ";

    $routesTemplate = <<<EOT
                        <?php
                        use Slim\App as App; 
                        {$uses}
                        {$authUses}
                        {$docsUses}
                        return function (App \$app,Array \$config) {
                        {$registers}
                        {$authRegisters}
                        {$docsRegisters}
                        };
                        EOT;

    //write the php register file for all the routes generated
    $fileOpen = fopen($routesFile, 'w');
    if ($fileOpen) {
        fwrite($fileOpen, $routesTemplate);
        echo "Generated route file: $routesFile\n";
    } else {
        echo "There was an error creatinng the router file";
    }
};

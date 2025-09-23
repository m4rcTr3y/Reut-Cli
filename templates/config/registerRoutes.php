<?php

function RegisterRoutes(String $configDir, String $routersDir)
{

    if (!is_dir($configDir)) {
        mkdir($configDir);
    }


    $routesFile = $configDir . 'routes.php';

    // Scan routers directory for *Router.php files
    $routerFiles = glob($routersDir . '*Router.php');
    $routerClasses = [];

    foreach ($routerFiles as $file) {
        // Extract router class name (e.g., UsersRouter from UsersRouter.php)
        $routerName = str_replace('.php', '', basename($file));
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

  

    // Generate routes.php content
    $uses = '';
    $registers = '';
    foreach ($routerClasses as $router) {
        $uses .= "use Reut\\Routers\\{$router};\n";
        $lowercaseName = '$'.strtolower($router).'var';
        $registers .=" new {$router}(\$app,\$config);\n";
    }

    $routesTemplate = <<<EOT
                        <?php
                        use Slim\App as App; 
                        {$uses}
                        return function (App \$app,Array \$config) {
                        {$registers}
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

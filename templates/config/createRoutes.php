<?php
require_once __DIR__.'/registerRoutes.php';

    // Load environment variables
    $projectRoot = dirname(__DIR__);
    if (file_exists($projectRoot . '/.env') && class_exists('\Dotenv\Dotenv')) {
        $dotenv = \Dotenv\Dotenv::createImmutable($projectRoot);
        $dotenv->safeLoad();
    }

    // Get auth model name from environment variable (defaults to 'Users')
    $authModelName = $_ENV['AUTH_TABLE'] ?? 'Users';

    $modelsDir = __DIR__ . '/../models/';
    $routersDir = __DIR__ . '/../routers/';

    // Ensure routers directory exists
    if (!is_dir($routersDir)) {
        mkdir($routersDir);
    }

    // Scan models directory
    $modelFiles = glob($modelsDir . '*Table.php');

    //echo "this is the script";
 

    foreach ($modelFiles as $file) {
        // Extract model name (e.g., User from UserTable.php)
        $modelName = str_replace('Table.php', '', basename($file));
        
        // Skip auth model - don't generate routes for it
        if ($modelName === $authModelName) {
            echo "Skipping auth model: $modelName (configured in AUTH_TABLE)\n";
            continue;
        }
        
        $routerFile = $routersDir . $modelName . 'Router.php';

        // Skip if file exists (unless --force is used)
        if (file_exists($routerFile) && !in_array('--force', $argv)) {
            echo "Route file for $modelName already exists. Use --force to overwrite.\n";
            continue;
        }

        writeToFile($routersDir,$modelName);
   
    }


    RegisterRoutes($routersDir,$routersDir);


    function writeToFile(String $routersDir,String $modelName){

    $lowercase = strtolower($modelName);
     $routerFile = $routersDir . $modelName . 'Router.php';
    $classImport = "use Reut\\Models\\{$modelName}Table;";
             $template = <<<EOT
                        <?php
                        declare(strict_types=1);
                        namespace Reut\Routers;

                        use Slim\App;
                        use Psr\Http\Message\ResponseInterface as Response;
                        use Psr\Http\Message\ServerRequestInterface as Request;
                        use Reut\Auth\NoAuth;
                        use Reut\Router\ReuteRoute;
                        use Reut\Query\ReutQueries;

                        //import the {$modelName} model here
                        
                        {$classImport}

                        // NoAuth class implements endpoints without authentication, authenticaton can be changed using the Auth class
                        class {$modelName}Router extends NoAuth {
                            protected \$config;
                             public function __construct(App \$app,Array \$config){
                                \$this->config = \$config;
                                parent::__construct(\$app);
                            
                            }

                            protected function genRoutes() {
                                \$instance = new {$modelName}Table(\$this->config);
                                \$router = ReuteRoute::use(\$this->app);
                                
                                // Get disabled routes from model instance
                                \$disabledRoutes = \$instance->disabledRoutes ?? [];
                                \$isAllDisabled = in_array('all', \$disabledRoutes);

                                \$router->group('/{$lowercase}', '{$modelName}', function (ReuteRoute \$grouped) use (\$instance, \$disabledRoutes, \$isAllDisabled) {

                                    //get all {$modelName}s from table " http://endpoint/{$lowercase}/all
                                    if (!\$isAllDisabled && !in_array('all', \$disabledRoutes)) {
                                        \$grouped->get('/all', function (Request \$request, Response \$response) use (\$instance) {
                                            \$params = \$request->getQueryParams();
                                            \$page = \$params['page'] ?? 1;
                                            \$limit = \$params['limit'] ?? 20;
                                            \$data = ReutQueries::handleFindAll(\$instance, \$request)->paginate((int)\$page, (int)\$limit);
                                            \$response->getBody()->write(json_encode(\$data));
                                            return \$response->withHeader('Content-Type', 'application/json');
                                        }, 'List {$modelName} records with pagination');
                                    }

                                    //Get single {$modelName} from the table " http://endpoint/{$lowercase}/find/{id}
                                    if (!\$isAllDisabled && !in_array('find', \$disabledRoutes)) {
                                        \$grouped->get('/find/{id}',function (Request \$request, Response \$response, \$args) use (\$instance) {
                                            \$id = \$args['id'];
                                            \$data = ReutQueries::handleFindOne(\$instance, \$id, \$request);
                                            \$response->getBody()->write(json_encode(\$data->results));
                                            return \$response->withHeader('Content-Type', 'application/json');
                                        }, 'Find single {$modelName} by id');
                                    }
                                    
                                    //Create new {$modelName}
                                    if (!\$isAllDisabled && !in_array('add', \$disabledRoutes)) {
                                        \$grouped->post('/add', function (Request \$request, Response \$response) use (\$instance) {
                                            \$input = \$request->getParsedBody();
                                            \$result = \$instance->addOne(\$input);
                                            \$response->getBody()->write(json_encode(['status' => \$result]));
                                            return \$response->withHeader('Content-Type', 'application/json');
                                        }, 'Create new {$modelName}');
                                    }

                                    //Update single {$modelName} from the table " http://endpoint/{$lowercase}/update/id
                                    if (!\$isAllDisabled && !in_array('update', \$disabledRoutes)) {
                                        \$grouped->put( '/update/{id}',function (Request \$request, Response \$response, \$args) use (\$instance) {
                                            \$id = \$args['id'];
                                            \$input = \$request->getParsedBody();
                                            return ReutQueries::handleUpdate(\$instance, \$id, \$input, \$request, \$response);
                                        }, 'Update {$modelName} by id');
                                    }

                                    //delete single {$modelName} from the table " http://endpoint/{$lowercase}/delete/id
                                    if (!\$isAllDisabled && !in_array('delete', \$disabledRoutes)) {
                                        \$grouped->delete('/delete/{id}', function (Request \$request, Response \$response,\$args) use (\$instance) {
                                            \$id = \$args['id'];
                                            return ReutQueries::handleDelete(\$instance, \$id, \$request, \$response);
                                        }, 'Delete {$modelName} by id');
                                    }


                                });
                            }
                        }
                        EOT;

        // Write the route file
        $fileOpen = fopen($routerFile,'w');
        if($fileOpen){
            fwrite($fileOpen,$template);
            echo "Generated route file: $routerFile\n";
        }else{
            echo "There was an error creatinng the router file";
        }
    }
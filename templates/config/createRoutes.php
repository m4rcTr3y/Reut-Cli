<?php
require_once __DIR__.'/registerRoutes.php';


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
                        use Slim\Routing\RouteCollectorProxy;
                        use Psr\Http\Message\ResponseInterface as Response;
                        use Psr\Http\Message\ServerRequestInterface as Request;
                        use Reut\Auth\NoAuth;

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
                                \$this->app->group('/{$lowercase}', function (RouteCollectorProxy \$group) {

                                    \$instance = new {$modelName}Table(\$this->config);

                                    //get all {$modelName}s from database
                                    \$group->get( '/all', function (Request \$request, Response \$response) use (\$instance) {
                                        \$params = \$request->getQueryParams();
                                        \$page = \$params['page'] ?? 1;
                                        \$limit = \$params['limit'] ?? 20;
                                        \$data = \$instance->findAll()->paginate((int)\$page, (int)\$limit);
                                        \$response->getBody()->write(json_encode(\$data));
                                        return \$response->withHeader('Content-Type', 'application/json');
                                    });

                                    //Get single {$modelName} from the table " http://endpoint/{$modelName}/find/id
                                    \$group->get('/find',function (Request \$request, Response \$response, \$args) use (\$instance) {
                                        \$id = \$args['id'];
                                        \$data = \$instance->findOne(['id' => \$id]);
                                        \$response->getBody()->write(json_encode(\$data->results));
                                        return \$response->withHeader('Content-Type', 'application/json');
                                    });
                                    \$group->post('/add', function (Request \$request, Response \$response) use (\$instance) {
                                        \$input = \$request->getParsedBody();
                                        \$result = \$instance->addOne(\$input);
                                        \$response->getBody()->write(json_encode(['status' => \$result]));
                                        return \$response->withHeader('Content-Type', 'application/json');
                                    });

                                    //Update single {$modelName} from the table " http://endpoint/{$modelName}/update/id
                                    \$group->put( 'update',function (Request \$request, Response \$response, \$args) use (\$instance) {
                                        \$id = \$args['id'];
                                        \$input = \$request->getParsedBody();
                                        \$result = \$instance->update(\$input, ['id' => \$id]);
                                        \$response->getBody()->write(json_encode(['status' => \$result]));
                                        return \$response->withHeader('Content-Type', 'application/json');
                                    });

                                    //delete single {$modelName} from the table " http://endpoint/{$modelName}/delete/id
                                    \$group->delete('delete', function (Request \$request, Response \$response,\$args) use (\$instance) {
                                        \$id = \$args['id'];
                                        \$result = \$instance->delete(['id' => \$id]);
                                        \$response->getBody()->write(json_encode(['status' => \$result]));
                                        return \$response->withHeader('Content-Type', 'application/json');
                                    });


                                });
                            }
                        }
                        EOT;

        // Write the route file
        $fileOpen = fopen($routerFile,'a');
        if($fileOpen){
            fwrite($fileOpen,$template);
            echo "Generated route file: $routerFile\n";
        }else{
            echo "There was an error creatinng the router file";
        }
    }
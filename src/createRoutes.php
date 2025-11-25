<?php
declare(strict_types=1);

use Reut\Support\ProjectPath;

require_once __DIR__ . '/registerRoutes.php';


    $modelsDir = rtrim(ProjectPath::resolve('models'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $routersDir = rtrim(ProjectPath::resolve('routers'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

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
                        use Psr\Http\Message\ResponseInterface as Response;
                        use Psr\Http\Message\ServerRequestInterface as Request;
                        use Reut\Auth\NoAuth;
                        use Reut\Router\ReuteRoute;

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

                                \$router->group('/{$lowercase}', '{$modelName}', function (ReuteRoute \$grouped) use (\$instance) {

                                    //get all {$modelName}s from table " http://endpoint/{$lowercase}/all
                                    \$grouped->get('/all', function (Request \$request, Response \$response) use (\$instance) {
                                        \$params = \$request->getQueryParams();
                                        \$page = \$params['page'] ?? 1;
                                        \$limit = \$params['limit'] ?? 20;
                                        \$data = \$instance->findAll()->paginate((int)\$page, (int)\$limit);
                                        \$response->getBody()->write(json_encode(\$data));
                                        return \$response->withHeader('Content-Type', 'application/json');
                                    }, 'List {$modelName} records with pagination');

                                    //Get single {$modelName} from the table " http://endpoint/{$lowercase}/find/{id}
                                    \$grouped->get('/find/{id}',function (Request \$request, Response \$response, \$args) use (\$instance) {
                                        \$id = \$args['id'];
                                        \$data = \$instance->findOne(['id' => \$id]);
                                        \$response->getBody()->write(json_encode(\$data->results));
                                        return \$response->withHeader('Content-Type', 'application/json');
                                    }, 'Find single {$modelName} by id');
                                    \$grouped->post('/add', function (Request \$request, Response \$response) use (\$instance) {
                                        \$input = \$request->getParsedBody();
                                        \$result = \$instance->addOne(\$input);
                                        \$response->getBody()->write(json_encode(['status' => \$result]));
                                        return \$response->withHeader('Content-Type', 'application/json');
                                    }, 'Create new {$modelName}');

                                    //Update single {$modelName} from the table " http://endpoint/{$lowercase}/update/id
                                    \$grouped->put( '/update/{id}',function (Request \$request, Response \$response, \$args) use (\$instance) {
                                        \$id = \$args['id'];
                                        \$input = \$request->getParsedBody();
                                        \$result = \$instance->update(\$input, ['id' => \$id]);
                                        \$response->getBody()->write(json_encode(['status' => \$result]));
                                        return \$response->withHeader('Content-Type', 'application/json');
                                    }, 'Update {$modelName} by id');

                                    //delete single {$modelName} from the table " http://endpoint/{$lowercase}/delete/id
                                    \$grouped->delete('/delete/{id}', function (Request \$request, Response \$response,\$args) use (\$instance) {
                                        \$id = \$args['id'];
                                        \$result = \$instance->delete(['id' => \$id]);
                                        \$response->getBody()->write(json_encode(['status' => \$result]));
                                        return \$response->withHeader('Content-Type', 'application/json');
                                    }, 'Delete {$modelName} by id');


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
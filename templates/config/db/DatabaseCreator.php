<?php

declare(strict_types=1);

namespace Reut\DB\Creator;

class DatabaseCreator{

    public static function Generate(){
        global $argv;
        // $data = $argc;
        if (count($argv) < 2) {
            echo "\nUsage: php manage.php <command>\n";
            echo "Commands:\n";
            echo "  create            - Initial start of project or add tables from models to the database\n";
            echo "  status            - Check for pending migrations in the models\n";
            echo "  generate:routes   - Generate routes for each model into the route/ folder\n";
            echo "  generate:model    - Generate model class, pass the model name into the console\n";
            echo "  migrate           - Apply migrations to the table from changes in the model definition\n";
            echo "  -v, version       - Show CLI version\n";
            echo "  -h, help          - Show this help message\n";
            exit(1);
        }
        
        $command = (String) $argv[1];                                                                                  
        
        switch ($command) {
            case 'create':
                require dirname(__DIR__). '/migrate.php';
                break;
                
            case 'generate:routes':
                require dirname(__DIR__). '/createRoutes.php';
                break;
            case 'generate:model':
                require dirname(__DIR__). '/createModels.php';
                break;
            case 'migrate':
                require dirname(__DIR__) . '/update.php';
                break;
            case 'status':
                require dirname(__DIR__) . '/checkmigration.php';
                break;
            case '-h':
            case 'help':
                echo "Usage: php manage.php <command>\n";
                echo "Commands:\n";
                echo "  create            - Initial start of project or add tables from models to the database\n";
                echo "  status            - Check for pending migrations in the models\n";
                echo "  generate:routes   - Generate routes for each model into the route/ folder\n";
                echo "  generate:model    - Generate model class, pass the model name into the console\n";
                echo "  migrate           - Apply migrations to the table from changes in the model definition\n";
                echo "  -v, version       - Show CLI version\n";
                echo "  -h, help          - Show this help message\n";
                break;
            case '-v':
            case 'version':
                echo "Reut CLI version 1.0.2\n";
                break;
            default:
                echo "Invalid command.\n";
                echo "Use 'php manage.php -h' or 'php manage.php help' for usage information.\n";
                exit(1);
        }
    }




}




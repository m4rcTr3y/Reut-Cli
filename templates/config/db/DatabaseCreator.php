<?php

declare(strict_types=1);

namespace Reut\DB\Creator;

class DatabaseCreator{

    public static function Generate(){
        global $argv;
        // $data = $argc;
        if ($argv < 2) {
            echo "\nUsage: php script.php <command>\n";
            echo "Commands:\n";
            echo "  create  - Initial start of project\n";
            echo "  migrate  - create tables if not created in the database\n";
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
            default:
                echo "Invalid command. Usage: php script.php <command>\n";
                echo "Commands:\n";
                echo "  create  - Initial start of project or add tables from models to the database\n";
                echo "  status  - check for pending migrations in the models\n";
                echo "  generate:routes  - generate routes for each model into the route/ folder \n";
                echo "  generate:model  - generate model class, you pass the model name into the console \n";
                echo "  migrate - apply migrations to the table from changes in the model definition\n";
                exit(1);
        }
    }




}




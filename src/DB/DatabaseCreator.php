<?php

declare(strict_types=1);

namespace Reut\DB;

class DatabaseCreator{

    public static function Generate(){
        global $argv;
        // $data = $argc;
        if (count($argv) < 2) {
            echo "\nUsage: php manage.php <command>\n";
            echo "Commands:\n";
            echo "  create            - Initial start of project or add tables from models to the database (alias of migrate)\n";
            echo "  migrate           - Apply migrations from model definitions (ensures tables exist)\n";
            echo "  sync              - Reconcile existing tables with models (may drop extra columns)\n";
            echo "  status            - Check for pending migrations in the models\n";
            echo "  inspect           - Inspect database schema and sync model definitions\n";
            echo "  rollback          - Rollback migrations (last batch, specific batch, or migration)\n";
            echo "  validate-migrations - Validate migration SQL syntax and check for issues\n";
            echo "  export-migrations - Export migration history to JSON/SQL file\n";
            echo "  import-migrations - Import migration history from JSON/SQL file\n";
            echo "  generate:routes   - Generate routes for each model into the route/ folder\n";
            echo "  generate:model    - Generate model class, pass the model name into the console\n";
            echo "  view              - Start the HTML schema viewer\n";
            echo "  dev               - Start the built-in PHP dev server\n";
            echo "  -v, version       - Show CLI version\n";
            echo "  -h, help          - Show this help message\n";
            exit(1);
        }
        
        $command = (String) $argv[1];                                                                                  
        
        switch ($command) {
            case 'create':
            case 'migrate':
                require dirname(__DIR__). '/migrate.php';
                break;
                
            case 'generate:routes':
                require dirname(__DIR__). '/createRoutes.php';
                break;
            case 'generate:model':
                require dirname(__DIR__). '/createModels.php';
                break;
            case 'sync':
                require dirname(__DIR__) . '/update.php';
                break;
            case 'view':
                require dirname(__DIR__) . '/view.php';
                break;
            case 'dev':
                require dirname(__DIR__) . '/dev.php';
                break;
            case 'status':
                require dirname(__DIR__) . '/checkmigration.php';
                break;
            case 'inspect':
                require dirname(__DIR__) . '/inspect.php';
                break;
            case 'rollback':
                require dirname(__DIR__) . '/rollback.php';
                break;
            case 'validate-migrations':
                require dirname(__DIR__) . '/validate-migrations.php';
                break;
            case 'export-migrations':
                require dirname(__DIR__) . '/export-migrations.php';
                break;
            case 'import-migrations':
                require dirname(__DIR__) . '/import-migrations.php';
                break;
            case '-h':
            case 'help':
                echo "Usage: php manage.php <command>\n";
                echo "Commands:\n";
                echo "  create            - Initial start of project or add tables from models to the database (alias of migrate)\n";
                echo "  status            - Check for pending migrations in the models\n";
                echo "  generate:routes   - Generate routes for each model into the route/ folder\n";
                echo "  generate:model    - Generate model class, pass the model name into the console\n";
                echo "  migrate           - Apply migrations from model definitions (ensures tables exist)\n";
                echo "  sync              - Reconcile existing tables with models (may drop extra columns)\n";
                echo "  inspect           - Inspect database schema and sync model definitions\n";
                echo "  rollback          - Rollback migrations (last batch, specific batch, or migration)\n";
                echo "  validate-migrations - Validate migration SQL syntax and check for issues\n";
                echo "  export-migrations - Export migration history to JSON/SQL file\n";
                echo "  import-migrations - Import migration history from JSON/SQL file\n";
                echo "  -v, version       - Show CLI version\n";
                echo "  -h, help          - Show this help message\n";
                break;
            case '-v':
            case 'version':
                echo "Reut CLI version 1.3.0\n";
                break;
            default:
                echo "Invalid command.\n";
                echo "Use 'php manage.php -h' or 'php manage.php help' for usage information.\n";
                exit(1);
        }
    }




}




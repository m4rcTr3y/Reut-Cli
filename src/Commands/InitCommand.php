<?php
declare(strict_types=1);

namespace Reut\CLI\Commands;

use Reut\CLI\Interactive\Prompt;

/**
 * Initialize a new REUT project
 */
class InitCommand extends Command
{
    public function getName(): string
    {
        return 'init';
    }

    public function getDescription(): string
    {
        return 'Initialize a new REUT project';
    }

    public function getUsage(): string
    {
        return 'init [project-name]';
    }

    public function getExamples(): array
    {
        return [
            'Reut init',
            'Reut init myproject',
        ];
    }

    public function execute(array $args = []): int
    {
        $this->section('🚀 Initializing a new REUT project...');
        $this->writeln();

        // Get project name
        $projectName = $this->getArg(0);
        if (empty($projectName)) {
            $projectName = $this->prompt->ask(
                'Enter project name',
                'myproject',
                Prompt::pattern('/^[a-zA-Z0-9_-]+$/', 'Project name can only contain letters, numbers, underscores, and hyphens')
            );
        }

        // Validate project name
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $projectName)) {
            $this->error('Invalid project name. Only letters, numbers, underscores, and hyphens are allowed.');
            return 1;
        }

        // Database type selection
        $dbType = $this->select->choose(
            'Select database type',
            ['mysql' => 'MySQL', 'postgresql' => 'PostgreSQL'],
            'mysql'
        );

        // Database configuration
        $this->section('📊 Database Configuration');
        $dbName = $this->prompt->ask('Enter database name', 'test_db');
        $dbUsername = $this->prompt->ask('Enter database username', 'root');
        $dbPassword = $this->prompt->password('Enter database password (leave blank if none)');
        $dbHost = $this->prompt->ask('Enter database host', 'localhost');

        // Secret key
        $secretKey = $this->prompt->ask(
            'Enter secret key (for JWT tokens)',
            bin2hex(random_bytes(16)),
            Prompt::minLength(8)
        );

        // Features selection
        $this->section('⚙️  Features');
        $enableAuth = $this->confirm->ask('Enable built-in authentication?', true);
        
        $testUserIdentifier = null;
        $testUserPassword = null;
        if ($enableAuth) {
            $this->writeln();
            $this->info('Test User Setup (optional)');
            $testUserIdentifier = $this->prompt->ask('Enter test user email/username (optional)');
            
            if (!empty($testUserIdentifier)) {
                $testUserPassword = $this->prompt->password(
                    'Enter test user password',
                    Prompt::minLength(6)
                );
            }
        }

        // Create project directory
        $projectDir = getcwd() . DIRECTORY_SEPARATOR . $projectName;
        if (is_dir($projectDir)) {
            $this->error("Directory already exists: {$projectDir}");
            return 1;
        }

        $this->section('📁 Creating project structure...');
        $progress = $this->createProgressBar(10);
        $progress->setMessage('Setting up project...');

        // Create directory
        mkdir($projectDir, 0755, true);
        $progress->advance();
        $this->success("Created project directory: {$projectName}");

        // Change to project directory
        chdir($projectDir);
        $progress->advance();

        // Copy skeleton or create structure
        $skeletonDir = __DIR__ . '/../../packages/skeleton';
        $usingSkeleton = is_dir($skeletonDir);
        
        if ($usingSkeleton) {
            $this->copyDirectory($skeletonDir, $projectDir);
            $progress->advance();
            $this->success('Copied project skeleton');
        } else {
            $this->warning('Skeleton not found, using legacy scaffolding');
            $dirs = ['config/db', 'models', 'routers'];
            foreach ($dirs as $dir) {
                mkdir($dir, 0755, true);
            }
            $progress->advance();
            
            $templateConfigDir = __DIR__ . '/../../templates/config';
            if (is_dir($templateConfigDir)) {
                $this->copyDirectory($templateConfigDir, 'config');
                $progress->advance();
            }
        }

        // Ensure required directories
        $requiredDirs = ['models', 'routers'];
        foreach ($requiredDirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
        $progress->advance();

        // Copy viewer
        $viewerSourceDir = __DIR__ . '/../viewer';
        if (is_dir($viewerSourceDir)) {
            $this->copyDirectory($viewerSourceDir, 'viewer');
            $progress->advance();
            $this->success('Copied viewer directory');
        }
        $progress->advance();

        // Copy devserver
        $devSourceDir = __DIR__ . '/../devserver';
        if (is_dir($devSourceDir)) {
            $this->copyDirectory($devSourceDir, 'devserver');
            $progress->advance();
            $this->success('Copied devserver directory');
        }
        $progress->advance();

        // Generate .env file
        $envContent = $this->generateEnvFile($dbHost, $dbUsername, $dbPassword, $dbName, $dbType, $secretKey, $enableAuth);
        file_put_contents('.env', $envContent);
        $progress->advance();
        $this->success('Created .env file');

        // Generate config.php
        $configContent = file_exists(__DIR__ . '/../configContent.php')
            ? require __DIR__ . '/../configContent.php'
            : '<?php return [];';
        file_put_contents('config.php', $configContent);
        $progress->advance();
        $this->success('Created config.php');

        // Generate auth.php
        $authContent = file_exists(__DIR__ . '/../authContent.php')
            ? require __DIR__ . '/../authContent.php'
            : '<?php return [];';
        file_put_contents('auth.php', $authContent);
        $progress->advance();
        $this->success('Created auth.php');

        // Generate auth model if enabled
        $authModelGenerated = false;
        if ($enableAuth) {
            $tableName = 'Users';
            $identifierField = 'email';
            
            if (file_exists('auth.php')) {
                $authFileContent = file_get_contents('auth.php');
                if (preg_match("/'table'\s*=>\s*['\"]?(\w+)['\"]?/", $authFileContent, $matches)) {
                    $tableName = $matches[1];
                }
                if (preg_match("/'identifier'\s*=>\s*['\"]?(\w+)['\"]?/", $authFileContent, $matches)) {
                    $identifierField = $matches[1];
                }
            }
            
            $modelsDir = 'models' . DIRECTORY_SEPARATOR;
            if (!is_dir($modelsDir)) {
                mkdir($modelsDir, 0755, true);
            }
            
            $modelFile = $modelsDir . $tableName . 'Table.php';
            if (!file_exists($modelFile)) {
                $createAuthModelPath = __DIR__ . '/../createAuthModel.php';
                if (file_exists($createAuthModelPath)) {
                    require_once $createAuthModelPath;
                    if (function_exists('createAuthModel')) {
                        try {
                            if (createAuthModel($tableName, $identifierField, false)) {
                                $this->success("Generated {$tableName}Table model");
                                $authModelGenerated = true;
                            }
                        } catch (\Exception $e) {
                            $this->warning("Failed to generate auth model: " . $e->getMessage());
                        }
                    }
                }
            }
            
            if ($testUserIdentifier && $testUserPassword) {
                $authSetupData = [
                    'identifier' => $testUserIdentifier,
                    'password' => $testUserPassword,
                    'table' => $tableName,
                    'identifierField' => $identifierField
                ];
                file_put_contents('.auth-setup.json', json_encode($authSetupData, JSON_PRETTY_PRINT));
                $this->success('Stored test user credentials');
            }
        }

        // Generate index.php
        $indexContent = file_exists(__DIR__ . '/../indexContent.php')
            ? require __DIR__ . '/../indexContent.php'
            : '<?php echo "Hello from REUT!";';
        file_put_contents('index.php', $indexContent);
        $progress->advance();
        $this->success('Created index.php');

        // Generate manage.php
        $manageContent = $this->generateManageFile();
        file_put_contents('manage.php', $manageContent);
        $progress->advance();
        $this->success('Created manage.php');

        // Configure composer.json
        $packageSlug = $this->sanitizePackageName($projectName);
        if ($usingSkeleton && file_exists('composer.json')) {
            $composerData = json_decode(file_get_contents('composer.json'), true);
            if (is_array($composerData)) {
                $composerData['name'] = "reut/{$packageSlug}";
                
                // Ensure autoload section includes Reut namespaces
                if (!isset($composerData['autoload'])) {
                    $composerData['autoload'] = [];
                }
                if (!isset($composerData['autoload']['psr-4'])) {
                    $composerData['autoload']['psr-4'] = [];
                }
                
                // Add Reut namespaces if not already present
                if (!isset($composerData['autoload']['psr-4']['Reut\\Models\\'])) {
                    $composerData['autoload']['psr-4']['Reut\\Models\\'] = 'models/';
                }
                if (!isset($composerData['autoload']['psr-4']['Reut\\Routers\\'])) {
                    $composerData['autoload']['psr-4']['Reut\\Routers\\'] = 'routers/';
                }
                
                file_put_contents('composer.json', json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $this->success('Configured composer.json');
            }
        }

        // Copy .htaccess
        $templateHtaccess = __DIR__ . '/../../templates/.htaccess';
        if (file_exists($templateHtaccess)) {
            copy($templateHtaccess, '.htaccess');
            $this->success('Copied .htaccess');
        }

        $progress->finish();

        // Summary
        $this->writeln();
        $this->section('✅ Project initialized successfully!');
        $this->writeln();
        $this->info('Next steps:');
        $this->writeln('  1. Run `composer install` in the project directory');
        
        if ($enableAuth && $authModelGenerated) {
            $this->writeln('  2. Run `Reut migrate` to create the authentication table');
            if ($testUserIdentifier) {
                $this->writeln("  3. Test user will be created automatically after migration");
                $this->writeln("  4. Login at POST /auth/login with:");
                $this->writeln("     - {$identifierField}: {$testUserIdentifier}");
            }
        } else {
            $this->writeln('  2. Use `Reut generate:model Users` to create models');
        }

        return 0;
    }

    private function generateEnvFile(string $host, string $username, string $password, string $dbname, string $dbtype, string $secretKey, bool $authEnabled): string
    {
        $authStr = $authEnabled ? 'true' : 'false';
        return <<<ENV
SECRET_KEY=$secretKey
DB_HOST=$host
DB_USERNAME=$username
DB_PASSWORD=$password
DB_NAME=$dbname
DB_TYPE=$dbtype
APP_ENV=development
REUT_AUTH_ENABLED=$authStr
REUT_DOCS_ENABLED=true

# CORS Configuration
REUT_CORS_ENABLED=true
REUT_CORS_ALLOWED_ORIGINS=*
REUT_CORS_ALLOWED_METHODS="GET, POST, PUT, DELETE, PATCH, OPTIONS"
REUT_CORS_ALLOWED_HEADERS="Content-Type, Authorization, X-Requested-With, X-CSRF-Token"
REUT_CORS_ALLOW_CREDENTIALS=false
REUT_CORS_MAX_AGE=86400

# Rate Limiting Configuration
REUT_RATE_LIMIT_ENABLED=true
REUT_RATE_LIMIT_MAX_REQUESTS=100
REUT_RATE_LIMIT_WINDOW_SECONDS=60

# CSRF Protection Configuration
REUT_CSRF_ENABLED=true
REUT_CSRF_TOKEN_NAME=csrf_token
REUT_CSRF_TOKEN_LENGTH=32
REUT_CSRF_TOKEN_LIFETIME=3600

# Required Field Validation
REUT_STRICT_REQUIRED_VALIDATION=false
ENV;
    }

    private function generateManageFile(): string
    {
        return <<<'PHP'
<?php
if (!defined('REUT_PROJECT_ROOT')) {
    define('REUT_PROJECT_ROOT', __DIR__);
}
chdir(REUT_PROJECT_ROOT);
$autoload = REUT_PROJECT_ROOT . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    fwrite(STDERR, "vendor/autoload.php not found. Run `composer install` before using the REUT CLI.\n");
    exit(1);
}
require $autoload;

use Reut\DB\DatabaseCreator;
if (!class_exists(DatabaseCreator::class)) {
    fwrite(STDERR, "Composer dependencies missing. Run `composer install` to install reut/core.\n");
    fwrite(STDERR, "If you just ran composer install, try running the command again.\n");
    exit(1);
}

DatabaseCreator::Generate();
PHP;
    }

    private function copyDirectory(string $source, string $dest): void
    {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }
        $dir = opendir($source);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $srcPath = $source . DIRECTORY_SEPARATOR . $file;
            $destPath = $dest . DIRECTORY_SEPARATOR . $file;
            if (is_dir($srcPath)) {
                $this->copyDirectory($srcPath, $destPath);
            } else {
                copy($srcPath, $destPath);
            }
        }
        closedir($dir);
    }

    private function sanitizePackageName(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : 'app';
    }
}


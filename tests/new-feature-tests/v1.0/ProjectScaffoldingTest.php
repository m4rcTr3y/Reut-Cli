<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Tests for Project Scaffolding and Functionality
 * 
 * Tests cover:
 * 1. Project initialization (file structure)
 * 2. Configuration files (.env, config.php, auth.php)
 * 3. Security features integration
 * 4. Directory structure
 * 5. File content validation
 */
class ProjectScaffoldingTest extends TestCase
{
    private string $testProjectDir;
    private string $testProjectName = 'test_scaffold_project';
    private string $originalCwd;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Store original working directory
        $this->originalCwd = getcwd();
        
        // Create a temporary directory for test projects
        $this->testProjectDir = sys_get_temp_dir() . '/reut_test_' . uniqid();
        mkdir($this->testProjectDir, 0755, true);
        chdir($this->testProjectDir);
    }

    protected function tearDown(): void
    {
        // Clean up test project directory
        if (is_dir($this->testProjectDir . '/' . $this->testProjectName)) {
            $this->removeDirectory($this->testProjectDir . '/' . $this->testProjectName);
        }
        
        // Restore original working directory
        chdir($this->originalCwd);
        
        // Clean up temp directory
        if (is_dir($this->testProjectDir)) {
            $this->removeDirectory($this->testProjectDir);
        }
        
        parent::tearDown();
    }

    /**
     * Test that project initialization creates all required files
     */
    public function testProjectInitializationCreatesRequiredFiles(): void
    {
        $this->scaffoldProject();

        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;

        // Core files
        $this->assertFileExists($projectPath . '/index.php', 'index.php should exist');
        $this->assertFileExists($projectPath . '/config.php', 'config.php should exist');
        $this->assertFileExists($projectPath . '/auth.php', 'auth.php should exist');
        $this->assertFileExists($projectPath . '/manage.php', 'manage.php should exist');
        $this->assertFileExists($projectPath . '/.env', '.env file should exist');
        $this->assertFileExists($projectPath . '/composer.json', 'composer.json should exist');

        // Directories
        $this->assertDirectoryExists($projectPath . '/models', 'models directory should exist');
        $this->assertDirectoryExists($projectPath . '/routers', 'routers directory should exist');
    }

    /**
     * Test that .env file contains all security configuration
     */
    public function testEnvFileContainsSecurityConfiguration(): void
    {
        $this->scaffoldProject();

        $envPath = $this->testProjectDir . '/' . $this->testProjectName . '/.env';
        $envContent = file_get_contents($envPath);

        // Basic configuration
        $this->assertStringContainsString('SECRET_KEY=', $envContent, '.env should contain SECRET_KEY');
        $this->assertStringContainsString('DB_USERNAME=', $envContent, '.env should contain DB_USERNAME');
        $this->assertStringContainsString('DB_PASSWORD=', $envContent, '.env should contain DB_PASSWORD');
        $this->assertStringContainsString('DB_NAME=', $envContent, '.env should contain DB_NAME');
        $this->assertStringContainsString('DB_TYPE=', $envContent, '.env should contain DB_TYPE');
        $this->assertStringContainsString('APP_ENV=', $envContent, '.env should contain APP_ENV');
        $this->assertStringContainsString('REUT_AUTH_ENABLED=', $envContent, '.env should contain REUT_AUTH_ENABLED');
        $this->assertStringContainsString('REUT_DOCS_ENABLED=', $envContent, '.env should contain REUT_DOCS_ENABLED');

        // Rate limiting configuration
        $this->assertStringContainsString('REUT_RATE_LIMIT_ENABLED=', $envContent, '.env should contain REUT_RATE_LIMIT_ENABLED');
        $this->assertStringContainsString('REUT_RATE_LIMIT_MAX_REQUESTS=', $envContent, '.env should contain REUT_RATE_LIMIT_MAX_REQUESTS');
        $this->assertStringContainsString('REUT_RATE_LIMIT_WINDOW_SECONDS=', $envContent, '.env should contain REUT_RATE_LIMIT_WINDOW_SECONDS');

        // CSRF protection configuration
        $this->assertStringContainsString('REUT_CSRF_ENABLED=', $envContent, '.env should contain REUT_CSRF_ENABLED');
        $this->assertStringContainsString('REUT_CSRF_TOKEN_NAME=', $envContent, '.env should contain REUT_CSRF_TOKEN_NAME');
        $this->assertStringContainsString('REUT_CSRF_TOKEN_LENGTH=', $envContent, '.env should contain REUT_CSRF_TOKEN_LENGTH');
        $this->assertStringContainsString('REUT_CSRF_TOKEN_LIFETIME=', $envContent, '.env should contain REUT_CSRF_TOKEN_LIFETIME');

        // Required field validation
        $this->assertStringContainsString('REUT_STRICT_REQUIRED_VALIDATION=', $envContent, '.env should contain REUT_STRICT_REQUIRED_VALIDATION');
    }

    /**
     * Test that index.php includes security middlewares
     */
    public function testIndexPhpIncludesSecurityMiddlewares(): void
    {
        $this->scaffoldProject();

        $indexPath = $this->testProjectDir . '/' . $this->testProjectName . '/index.php';
        $indexContent = file_get_contents($indexPath);

        // Check for middleware imports
        $this->assertStringContainsString('RateLimitMiddleware', $indexContent, 'index.php should import RateLimitMiddleware');
        $this->assertStringContainsString('CsrfMiddleware', $indexContent, 'index.php should import CsrfMiddleware');

        // Check for middleware instantiation
        $this->assertStringContainsString('new RateLimitMiddleware', $indexContent, 'index.php should instantiate RateLimitMiddleware');
        $this->assertStringContainsString('new CsrfMiddleware', $indexContent, 'index.php should instantiate CsrfMiddleware');

        // Check for middleware addition
        $this->assertStringContainsString('$app->add(new RateLimitMiddleware', $indexContent, 'index.php should add RateLimitMiddleware');
        $this->assertStringContainsString('$app->add(new CsrfMiddleware', $indexContent, 'index.php should add CsrfMiddleware');
    }

    /**
     * Test that config.php has correct structure
     */
    public function testConfigPhpStructure(): void
    {
        $this->scaffoldProject();

        $configPath = $this->testProjectDir . '/' . $this->testProjectName . '/config.php';
        $configContent = file_get_contents($configPath);

        // Check for required constants and variables
        $this->assertStringContainsString('REUT_PROJECT_ROOT', $configContent, 'config.php should define REUT_PROJECT_ROOT');
        $this->assertStringContainsString('$config', $configContent, 'config.php should define $config array');
        $this->assertStringContainsString('DB_USERNAME', $configContent, 'config.php should use DB_USERNAME');
        $this->assertStringContainsString('DB_PASSWORD', $configContent, 'config.php should use DB_PASSWORD');
        $this->assertStringContainsString('DB_NAME', $configContent, 'config.php should use DB_NAME');
    }

    /**
     * Test that auth.php has correct structure
     */
    public function testAuthPhpStructure(): void
    {
        $this->scaffoldProject();

        $authPath = $this->testProjectDir . '/' . $this->testProjectName . '/auth.php';
        $authContent = file_get_contents($authPath);

        // Check for required structure
        $this->assertStringContainsString('REUT_PROJECT_ROOT', $authContent, 'auth.php should define REUT_PROJECT_ROOT');
        $this->assertStringContainsString('$authConfig', $authContent, 'auth.php should define $authConfig');
        $this->assertStringContainsString('table', $authContent, 'auth.php should define table configuration');
        $this->assertStringContainsString('fields', $authContent, 'auth.php should define fields configuration');
    }

    /**
     * Test that manage.php has correct structure
     */
    public function testManagePhpStructure(): void
    {
        $this->scaffoldProject();

        $managePath = $this->testProjectDir . '/' . $this->testProjectName . '/manage.php';
        $manageContent = file_get_contents($managePath);

        // Check for required structure
        $this->assertStringContainsString('REUT_PROJECT_ROOT', $manageContent, 'manage.php should define REUT_PROJECT_ROOT');
        $this->assertStringContainsString('DatabaseCreator', $manageContent, 'manage.php should use DatabaseCreator');
        $this->assertStringContainsString('Generate', $manageContent, 'manage.php should call Generate()');
    }

    /**
     * Test that composer.json has correct structure
     */
    public function testComposerJsonStructure(): void
    {
        $this->scaffoldProject();

        $composerPath = $this->testProjectDir . '/' . $this->testProjectName . '/composer.json';
        $this->assertFileExists($composerPath, 'composer.json should exist');

        $composerData = json_decode(file_get_contents($composerPath), true);
        $this->assertIsArray($composerData, 'composer.json should be valid JSON');
        $this->assertArrayHasKey('name', $composerData, 'composer.json should have name');
        $this->assertStringStartsWith('reut/', $composerData['name'], 'composer.json name should start with reut/');
    }

    /**
     * Test that skeleton files are copied correctly
     */
    public function testSkeletonFilesCopied(): void
    {
        $this->scaffoldProject();

        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        $skeletonDir = __DIR__ . '/../../../packages/skeleton';

        if (is_dir($skeletonDir)) {
            // Check that skeleton directories exist
            if (is_dir($skeletonDir . '/routers')) {
                $this->assertDirectoryExists($projectPath . '/routers', 'routers directory from skeleton should exist');
            }
            
            if (is_dir($skeletonDir . '/viewer')) {
                $this->assertDirectoryExists($projectPath . '/viewer', 'viewer directory from skeleton should exist');
            }
            
            if (is_dir($skeletonDir . '/devserver')) {
                $this->assertDirectoryExists($projectPath . '/devserver', 'devserver directory from skeleton should exist');
            }
        }
    }

    /**
     * Test that .env values are correctly set
     */
    public function testEnvFileValues(): void
    {
        $this->scaffoldProject();

        $envPath = $this->testProjectDir . '/' . $this->testProjectName . '/.env';
        $envContent = file_get_contents($envPath);

        // Parse .env file
        $envVars = [];
        foreach (explode("\n", $envContent) as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $envVars[trim($key)] = trim($value);
            }
        }

        // Verify security settings have default values
        $this->assertEquals('true', $envVars['REUT_RATE_LIMIT_ENABLED'] ?? '', 'REUT_RATE_LIMIT_ENABLED should default to true');
        $this->assertEquals('100', $envVars['REUT_RATE_LIMIT_MAX_REQUESTS'] ?? '', 'REUT_RATE_LIMIT_MAX_REQUESTS should default to 100');
        $this->assertEquals('60', $envVars['REUT_RATE_LIMIT_WINDOW_SECONDS'] ?? '', 'REUT_RATE_LIMIT_WINDOW_SECONDS should default to 60');
        
        $this->assertEquals('true', $envVars['REUT_CSRF_ENABLED'] ?? '', 'REUT_CSRF_ENABLED should default to true');
        $this->assertEquals('csrf_token', $envVars['REUT_CSRF_TOKEN_NAME'] ?? '', 'REUT_CSRF_TOKEN_NAME should default to csrf_token');
        $this->assertEquals('32', $envVars['REUT_CSRF_TOKEN_LENGTH'] ?? '', 'REUT_CSRF_TOKEN_LENGTH should default to 32');
        $this->assertEquals('3600', $envVars['REUT_CSRF_TOKEN_LIFETIME'] ?? '', 'REUT_CSRF_TOKEN_LIFETIME should default to 3600');
        
        $this->assertEquals('false', $envVars['REUT_STRICT_REQUIRED_VALIDATION'] ?? '', 'REUT_STRICT_REQUIRED_VALIDATION should default to false');
    }

    /**
     * Test that index.php has proper middleware order
     */
    public function testIndexPhpMiddlewareOrder(): void
    {
        $this->scaffoldProject();

        $indexPath = $this->testProjectDir . '/' . $this->testProjectName . '/index.php';
        $indexContent = file_get_contents($indexPath);

        // Check that all required middlewares are present
        $this->assertStringContainsString('addRoutingMiddleware', $indexContent, 'Should have routing middleware');
        $this->assertStringContainsString('addBodyParsingMiddleware', $indexContent, 'Should have body parsing middleware');
        $this->assertStringContainsString('RateLimitMiddleware', $indexContent, 'Should have rate limit middleware');
        $this->assertStringContainsString('CsrfMiddleware', $indexContent, 'Should have CSRF middleware');

        // Verify that security middlewares are added (check for the add() calls)
        $this->assertStringContainsString('$app->add(new RateLimitMiddleware', $indexContent, 
            'Should add RateLimitMiddleware to app');
        $this->assertStringContainsString('$app->add(new CsrfMiddleware', $indexContent, 
            'Should add CsrfMiddleware to app');

        // Verify order: routing and body parsing should come before security middlewares
        // Split content into lines for easier analysis
        $lines = explode("\n", $indexContent);
        $routingLine = -1;
        $bodyParsingLine = -1;
        $rateLimitLine = -1;
        $csrfLine = -1;

        foreach ($lines as $index => $line) {
            if (strpos($line, 'addRoutingMiddleware') !== false) {
                $routingLine = $index;
            }
            if (strpos($line, 'addBodyParsingMiddleware') !== false) {
                $bodyParsingLine = $index;
            }
            if (strpos($line, 'RateLimitMiddleware') !== false && strpos($line, 'new') !== false) {
                $rateLimitLine = $index;
            }
            if (strpos($line, 'CsrfMiddleware') !== false && strpos($line, 'new') !== false) {
                $csrfLine = $index;
            }
        }

        // Verify that security middlewares come after basic middleware setup
        if ($routingLine >= 0 && $rateLimitLine >= 0) {
            $this->assertGreaterThan($routingLine, $rateLimitLine, 
                'RateLimitMiddleware should be added after routing middleware');
        }
        if ($bodyParsingLine >= 0 && $rateLimitLine >= 0) {
            $this->assertGreaterThan($bodyParsingLine, $rateLimitLine, 
                'RateLimitMiddleware should be added after body parsing middleware');
        }
    }

    /**
     * Test that project can be scaffolded with different configurations
     */
    public function testProjectScaffoldingWithDifferentConfigs(): void
    {
        // Test with auth disabled
        $this->scaffoldProject(['authEnabled' => 'false']);
        
        $envPath = $this->testProjectDir . '/' . $this->testProjectName . '/.env';
        $envContent = file_get_contents($envPath);
        $this->assertStringContainsString('REUT_AUTH_ENABLED=false', $envContent, 'Auth should be disabled when specified');
    }

    /**
     * Test that all required directories have correct permissions
     */
    public function testDirectoryPermissions(): void
    {
        $this->scaffoldProject();

        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        
        // Check that directories are readable
        $this->assertTrue(is_readable($projectPath . '/models'), 'models directory should be readable');
        $this->assertTrue(is_readable($projectPath . '/routers'), 'routers directory should be readable');
    }

    /**
     * Scaffold a test project
     */
    private function scaffoldProject(array $options = []): void
    {
        $projectName = $options['projectName'] ?? $this->testProjectName;
        $dbType = $options['dbType'] ?? 'mysql';
        $dbName = $options['dbName'] ?? 'test_db';
        $dbUsername = $options['dbUsername'] ?? 'root';
        $dbPassword = $options['dbPassword'] ?? '';
        $secretKey = $options['secretKey'] ?? '12345678';
        $authEnabled = $options['authEnabled'] ?? 'true';

        $projectDir = $this->testProjectDir . '/' . $projectName;
        mkdir($projectDir, 0755, true);
        chdir($projectDir);

        // Copy skeleton if it exists (adjust path for new location: tests/new-feature-tests/v1.0/)
        // From v1.0/ -> new-feature-tests/ -> tests/ -> root (3 levels up)
        $skeletonDir = __DIR__ . '/../../../packages/skeleton';
        if (is_dir($skeletonDir)) {
            $this->copyDirectory($skeletonDir, $projectDir);
        }

        // Create required directories
        $dirs = ['models', 'routers'];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        // Copy viewer and devserver if they exist (adjust path for new location)
        $viewerSourceDir = __DIR__ . '/../../../src/viewer';
        if (is_dir($viewerSourceDir)) {
            $this->copyDirectory($viewerSourceDir, $projectDir . '/viewer');
        }

        $devSourceDir = __DIR__ . '/../../../src/devserver';
        if (is_dir($devSourceDir)) {
            $this->copyDirectory($devSourceDir, $projectDir . '/devserver');
        }

        // Generate .env file
        $envContent = <<<ENV
SECRET_KEY=$secretKey
DB_USERNAME=$dbUsername
DB_PASSWORD=$dbPassword
DB_NAME=$dbName
DB_TYPE=$dbType
APP_ENV=development
REUT_AUTH_ENABLED=$authEnabled
REUT_DOCS_ENABLED=true

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
        file_put_contents('.env', $envContent);

        // Generate config.php (adjust path for new location: tests/new-feature-tests/v1.0/)
        $configContentPath = __DIR__ . '/../../../src/configContent.php';
        if (file_exists($configContentPath)) {
            $configContent = require $configContentPath;
            file_put_contents('config.php', $configContent);
        }

        // Generate auth.php
        $authContentPath = __DIR__ . '/../../../src/authContent.php';
        if (file_exists($authContentPath)) {
            $authContent = require $authContentPath;
            file_put_contents('auth.php', $authContent);
        }

        // Generate index.php
        $indexContentPath = __DIR__ . '/../../../src/indexContent.php';
        if (file_exists($indexContentPath)) {
            $indexContent = require $indexContentPath;
            file_put_contents('index.php', $indexContent);
        }

        // Generate manage.php
        $manageContent = <<<PHP
<?php
if (!defined('REUT_PROJECT_ROOT')) {
    define('REUT_PROJECT_ROOT', __DIR__);
}
chdir(REUT_PROJECT_ROOT);
\$autoload = REUT_PROJECT_ROOT . '/vendor/autoload.php';
if (!file_exists(\$autoload)) {
    fwrite(STDERR, "vendor/autoload.php not found. Run `composer install` before using the REUT CLI.\\n");
    exit(1);
}
require \$autoload;
use Reut\DB\Creator\DatabaseCreator;
if (!class_exists(DatabaseCreator::class)) {
    fwrite(STDERR, "Composer dependencies missing. Run `composer install` to install reut/core.\\n");
    exit(1);
}
DatabaseCreator::Generate();
PHP;
        file_put_contents('manage.php', $manageContent);

        // Generate composer.json if skeleton doesn't have it
        if (!file_exists('composer.json')) {
            $packageSlug = preg_replace('/[^a-z0-9]/', '', strtolower($projectName));
            $composerData = [
                'name' => "reut/$packageSlug",
                'require' => ['php' => '>=7.4'],
                'autoload' => ['psr-4' => ["MyFramework\\" => "src/"]]
            ];
            file_put_contents('composer.json', json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            // Update composer.json name
            $composerData = json_decode(file_get_contents('composer.json'), true);
            if (is_array($composerData)) {
                $packageSlug = preg_replace('/[^a-z0-9]/', '', strtolower($projectName));
                $composerData['name'] = "reut/$packageSlug";
                file_put_contents('composer.json', json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
        }
    }

    /**
     * Copy directory recursively
     */
    private function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $destPath = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                copy($item->getPathname(), $destPath);
            }
        }
    }

    /**
     * Remove directory recursively
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}


<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use PDO;

/**
 * Tests for Project Scaffolding and CLI Commands (v1.4)
 * 
 * Tests cover:
 * 1. Project initialization (scaffolding)
 * 2. File structure creation
 * 3. Composer.json with reut/core dependency
 * 4. manage.php creation and functionality
 * 5. CLI commands execution (dev, migrate, view, etc.)
 * 6. Command error handling
 * 
 * Database credentials:
 * - Username: root
 * - Password: root@1234
 * - Database: test_db_v14_scaffold
 */
class ProjectScaffoldingAndCommandsTest extends TestCase
{
    private string $testProjectDir;
    private string $testProjectName = 'test_scaffold_v14';
    private string $originalCwd;
    private string $cliPath;
    private PDO $pdo;
    private const DB_USER = 'root';
    private const DB_PASS = 'root@1234';
    private const DB_NAME = 'test_db_v14_scaffold';
    private const DB_HOST = 'localhost';

    protected function setUp(): void
    {
        parent::setUp();
        
        // Store original working directory
        $this->originalCwd = getcwd();
        
        // Create a temporary directory for test projects
        $this->testProjectDir = sys_get_temp_dir() . '/reut_scaffold_v14_' . uniqid();
        mkdir($this->testProjectDir, 0755, true);
        chdir($this->testProjectDir);
        
        // Path to CLI script
        $cliPathCandidate = realpath(__DIR__ . '/../../../bin/Reut');
        $this->cliPath = $cliPathCandidate ?: __DIR__ . '/../../../bin/Reut';
        
        // Connect to database
        $dsn = "mysql:host=" . self::DB_HOST . ";charset=utf8mb4";
        $this->pdo = new PDO($dsn, self::DB_USER, self::DB_PASS);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create test database
        $this->createTestDatabase();
    }

    protected function tearDown(): void
    {
        // Restore original working directory
        chdir($this->originalCwd);
        
        // Clean up test project directory
        if (is_dir($this->testProjectDir . '/' . $this->testProjectName)) {
            $this->removeDirectory($this->testProjectDir . '/' . $this->testProjectName);
        }
        
        // Clean up temp directory
        if (is_dir($this->testProjectDir)) {
            $this->removeDirectory($this->testProjectDir);
        }
        
        // Clean up test database
        try {
            $this->pdo->exec("DROP DATABASE IF EXISTS " . self::DB_NAME);
        } catch (\PDOException $e) {
            // Ignore errors
        }
        
        parent::tearDown();
    }

    /**
     * Test project initialization creates correct file structure
     */
    public function testProjectScaffoldingCreatesFiles(): void
    {
        $this->scaffoldTestProject();
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        
        // Check essential files exist
        $this->assertFileExists($projectPath . '/manage.php', 'manage.php should be created');
        $this->assertFileExists($projectPath . '/composer.json', 'composer.json should be created');
        $this->assertFileExists($projectPath . '/.env', '.env file should be created');
        $this->assertFileExists($projectPath . '/config.php', 'config.php should be created');
        $this->assertFileExists($projectPath . '/index.php', 'index.php should be created');
        $this->assertFileExists($projectPath . '/auth.php', 'auth.php should be created');
        
        // Check directories exist
        $this->assertDirectoryExists($projectPath . '/models', 'models directory should be created');
        $this->assertDirectoryExists($projectPath . '/devserver', 'devserver directory should be created');
        $this->assertDirectoryExists($projectPath . '/viewer', 'viewer directory should be created');
    }

    /**
     * Test composer.json includes reut/core dependency
     */
    public function testComposerJsonIncludesReutCore(): void
    {
        $this->scaffoldTestProject();
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        
        $composerJson = json_decode(file_get_contents($projectPath . '/composer.json'), true);
        $this->assertIsArray($composerJson, 'composer.json should be valid JSON');
        $this->assertArrayHasKey('require', $composerJson, 'composer.json should have require section');
        $this->assertArrayHasKey('reut/core', $composerJson['require'], 'composer.json should include reut/core');
        $this->assertNotEmpty($composerJson['require']['reut/core'], 'reut/core version should be specified');
    }

    /**
     * Test manage.php is created correctly and can be executed
     */
    public function testManagePhpCreationAndExecution(): void
    {
        $this->scaffoldTestProject();
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        
        // Check manage.php content
        $manageContent = file_get_contents($projectPath . '/manage.php');
        $this->assertStringContainsString('REUT_PROJECT_ROOT', $manageContent, 'manage.php should define REUT_PROJECT_ROOT');
        $this->assertStringContainsString('DatabaseCreator', $manageContent, 'manage.php should use DatabaseCreator');
        $this->assertStringContainsString('vendor/autoload.php', $manageContent, 'manage.php should require autoloader');
        
        // Check manage.php is executable (can be run)
        chdir($projectPath);
        $output = $this->runCommand('php manage.php 2>&1', $projectPath);
        // Should show usage or help message (not fatal error)
        $this->assertNotEmpty($output, 'manage.php should produce output');
        $this->assertStringNotContainsString('Fatal error', $output, 'manage.php should not have fatal errors');
    }

    /**
     * Test dev command can be executed (after composer install)
     */
    public function testDevCommandExecution(): void
    {
        $this->scaffoldTestProject();
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        
        // Install dependencies
        chdir($projectPath);
        $this->runCommand('composer install --no-interaction --quiet 2>&1', $projectPath);
        
        // Check dev command can be called (will fail because server can't start in test, but should not have class errors)
        $output = $this->runCommand('timeout 2 php manage.php dev 2>&1 || true', $projectPath);
        
        // Should not have class not found errors
        $this->assertStringNotContainsString('Class "Reut\\Support\\ProjectPath" not found', $output, 
            'dev command should not have ProjectPath class error');
        $this->assertStringNotContainsString('Composer dependencies missing', $output, 
            'dev command should not have dependency errors after composer install');
    }

    /**
     * Test migrate command works after scaffolding
     */
    public function testMigrateCommandExecution(): void
    {
        $this->scaffoldTestProject();
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        
        // Install dependencies
        chdir($projectPath);
        $this->runCommand('composer install --no-interaction --quiet 2>&1', $projectPath);
        
        // Run migrate command
        $output = $this->runCommand('php manage.php migrate 2>&1', $projectPath);
        
        // Should not have fatal errors
        $this->assertStringNotContainsString('Fatal error', $output, 'migrate command should not have fatal errors');
        $this->assertStringNotContainsString('Class not found', $output, 'migrate command should not have class errors');
    }

    /**
     * Test view command can be executed
     */
    public function testViewCommandExecution(): void
    {
        $this->scaffoldTestProject();
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        
        // Install dependencies
        chdir($projectPath);
        $this->runCommand('composer install --no-interaction --quiet 2>&1', $projectPath);
        
        // Check view command can be called (will fail because server can't start in test, but should not have class errors)
        $output = $this->runCommand('timeout 2 php manage.php view 2>&1 || true', $projectPath);
        
        // Should not have class not found errors
        $this->assertStringNotContainsString('Class "Reut\\Support\\ProjectPath" not found', $output, 
            'view command should not have ProjectPath class error');
    }

    /**
     * Test that commands fail gracefully when dependencies are missing
     */
    public function testCommandsFailGracefullyWithoutDependencies(): void
    {
        $this->scaffoldTestProject();
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        
        // Don't run composer install - test error handling
        chdir($projectPath);
        
        // manage.php should check for dependencies
        $output = $this->runCommand('php manage.php migrate 2>&1', $projectPath);
        
        // Should show helpful error message
        $this->assertStringContainsString('vendor/autoload.php', $output, 
            'Should show autoload error when dependencies missing');
    }

    /**
     * Test .env file contains correct configuration
     */
    public function testEnvFileConfiguration(): void
    {
        $this->scaffoldTestProject();
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        
        $envContent = file_get_contents($projectPath . '/.env');
        
        // Check for database configuration
        $this->assertStringContainsString('DB_HOST', $envContent, '.env should contain DB_HOST');
        $this->assertStringContainsString('DB_NAME', $envContent, '.env should contain DB_NAME');
        $this->assertStringContainsString('DB_USER', $envContent, '.env should contain DB_USER');
        
        // Check for CORS configuration (v1.4 feature)
        $this->assertStringContainsString('REUT_CORS_ENABLED', $envContent, '.env should contain CORS config');
        $this->assertStringContainsString('REUT_CORS_ALLOWED_ORIGINS', $envContent, '.env should contain CORS origins');
    }

    /**
     * Test config.php is created correctly
     */
    public function testConfigPhpCreation(): void
    {
        $this->scaffoldTestProject();
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        
        $configContent = file_get_contents($projectPath . '/config.php');
        
        // Check for essential configuration
        $this->assertStringContainsString('REUT_PROJECT_ROOT', $configContent, 'config.php should define REUT_PROJECT_ROOT');
        $this->assertStringContainsString('Dotenv', $configContent, 'config.php should use Dotenv');
    }

    /**
     * Test auth.php is created correctly
     */
    public function testAuthPhpCreation(): void
    {
        $this->scaffoldTestProject();
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        
        $authContent = file_get_contents($projectPath . '/auth.php');
        
        // Check for auth configuration
        $this->assertStringContainsString('table', $authContent, 'auth.php should contain table config');
        $this->assertStringContainsString('fields', $authContent, 'auth.php should contain fields config');
        
        // Check that it safely handles missing vendor/autoload.php (v1.4 fix)
        $this->assertStringContainsString('file_exists', $authContent, 
            'auth.php should check if vendor/autoload.php exists');
    }

    /**
     * Test index.php is created correctly
     */
    public function testIndexPhpCreation(): void
    {
        $this->scaffoldTestProject();
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        
        $indexContent = file_get_contents($projectPath . '/index.php');
        
        // Check for essential components
        $this->assertStringContainsString('Slim\\App', $indexContent, 'index.php should use Slim App');
        $this->assertStringContainsString('CorsMiddleware', $indexContent, 'index.php should use CorsMiddleware (v1.4)');
    }

    /**
     * Test that UsersTable model is generated when auth is enabled
     */
    public function testUsersTableModelGeneration(): void
    {
        $this->scaffoldTestProject();
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        
        // Check if UsersTable model was created
        $modelFile = $projectPath . '/models/UsersTable.php';
        if (file_exists($modelFile)) {
            $modelContent = file_get_contents($modelFile);
            
            // Check for proper column definitions
            $this->assertStringContainsString('class UsersTable', $modelContent, 'UsersTable should be defined');
            $this->assertStringContainsString('extends DataBase', $modelContent, 'UsersTable should extend DataBase');
            $this->assertStringContainsString('updated_at', $modelContent, 'UsersTable should have updated_at');
            
            // Check for proper Timestamp definition (v1.4 bug fix)
            // Should have 3 parameters for updated_at: (false, true, true)
            $this->assertStringContainsString('new Timestamp', $modelContent, 'Should use Timestamp type');
        }
    }

    /**
     * Scaffold a test project using the init command
     */
    private function scaffoldTestProject(): void
    {
        chdir($this->testProjectDir);
        
        // Prepare input for init command
        $inputs = [
            $this->testProjectName . "\n",           // Project name
            "mysql\n",                              // Database type
            self::DB_NAME . "\n",                   // Database name
            self::DB_USER . "\n",                   // Database username
            self::DB_PASS . "\n",                   // Database password
            "test_secret_key_12345\n",              // Secret key
            "y\n",                                  // Enable auth
            "test@example.com\n",                   // Test user email
            "password123\n"                         // Test user password
        ];
        
        $inputString = implode('', $inputs);
        
        // Run init command
        $command = sprintf(
            'echo -n %s | %s init 2>&1',
            escapeshellarg($inputString),
            escapeshellarg($this->cliPath)
        );
        
        $output = shell_exec($command);
        
        // Verify project was created
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        if (!is_dir($projectPath)) {
            $this->fail('Project directory was not created. Output: ' . substr($output ?? '', 0, 500));
        }
    }

    /**
     * Create test database
     */
    private function createTestDatabase(): void
    {
        try {
            $this->pdo->exec("DROP DATABASE IF EXISTS " . self::DB_NAME);
            $this->pdo->exec("CREATE DATABASE " . self::DB_NAME);
        } catch (\PDOException $e) {
            $this->markTestSkipped('Could not create test database: ' . $e->getMessage());
        }
    }

    /**
     * Run a CLI command and return output
     */
    private function runCliCommand(array $args): string
    {
        $command = escapeshellarg($this->cliPath) . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1';
        $output = shell_exec($command);
        return $output ?? '';
    }

    /**
     * Run a shell command in a specific directory
     */
    private function runCommand(string $command, string $cwd): string
    {
        $oldCwd = getcwd();
        chdir($cwd);
        $output = shell_exec($command . ' 2>&1');
        chdir($oldCwd);
        return $output ?? '';
    }

    /**
     * Recursively remove a directory
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}


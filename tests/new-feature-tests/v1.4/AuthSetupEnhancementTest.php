<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use PDO;
use Reut\DB\DataBase;
use Reut\Auth\AuthRouter;
use Reut\Support\ProjectPath;

/**
 * Tests for Auth Setup Enhancement (v1.4)
 * 
 * Tests cover:
 * 1. Auth model generation during project initialization
 * 2. Test user credential storage in .auth-setup.json
 * 3. Post-migration automatic user creation
 * 4. AuthRouter bug fix (updated_at column definition)
 * 5. AuthRouter preference for existing model files over auto-creation
 * 6. Proper Timestamp column definitions for created_at and updated_at
 * 
 * Database credentials:
 * - Username: root
 * - Password: root@1234
 * - Database: test_db_v14
 */
class AuthSetupEnhancementTest extends TestCase
{
    private string $testProjectDir;
    private string $testProjectName = 'test_auth_setup';
    private string $originalCwd;
    private PDO $pdo;
    private const DB_USER = 'root';
    private const DB_PASS = 'root@1234';
    private const DB_NAME = 'test_db_v14';
    private const DB_HOST = 'localhost';

    protected function setUp(): void
    {
        parent::setUp();
        
        // Store original working directory
        $this->originalCwd = getcwd();
        
        // Create a temporary directory for test projects
        $this->testProjectDir = sys_get_temp_dir() . '/reut_test_v14_' . uniqid();
        mkdir($this->testProjectDir, 0755, true);
        chdir($this->testProjectDir);
        
        // Connect to database
        $dsn = "mysql:host=" . self::DB_HOST . ";charset=utf8mb4";
        $this->pdo = new PDO($dsn, self::DB_USER, self::DB_PASS);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create test database
        $this->pdo->exec("DROP DATABASE IF EXISTS " . self::DB_NAME);
        $this->pdo->exec("CREATE DATABASE " . self::DB_NAME);
        $this->pdo->exec("USE " . self::DB_NAME);
        
        // Create config.php
        $this->createConfigFile();
        
        // Create auth.php
        $this->createAuthConfigFile();
        
        // Create models directory
        mkdir($this->testProjectDir . '/models', 0755, true);
        
        // Set up autoloading
        $this->setupAutoloading();
    }

    protected function tearDown(): void
    {
        // Restore original working directory
        chdir($this->originalCwd);
        
        // Clean up test database
        try {
            $this->pdo->exec("DROP DATABASE IF EXISTS " . self::DB_NAME);
        } catch (\PDOException $e) {
            // Ignore errors
        }
        
        // Clean up temporary directory
        $this->removeDirectory($this->testProjectDir);
        
        parent::tearDown();
    }

    private function createConfigFile(): void
    {
        $configContent = <<<'PHP'
<?php
$config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => 'root@1234',
    'dbname' => 'test_db_v14',
    'charset' => 'utf8mb4'
];
PHP;
        file_put_contents($this->testProjectDir . '/config.php', $configContent);
    }

    private function createAuthConfigFile(): void
    {
        $authContent = <<<'PHP'
<?php
return [
    'enabled' => true,
    'table' => 'Users',
    'fields' => [
        'id' => 'id',
        'identifier' => 'email',
        'password' => 'password'
    ],
    'token_expiry' => 3600,
    'auto_create_table' => true
];
PHP;
        file_put_contents($this->testProjectDir . '/auth.php', $authContent);
    }

    private function setupAutoloading(): void
    {
        // Set REUT_PROJECT_ROOT for ProjectPath
        if (!defined('REUT_PROJECT_ROOT')) {
            define('REUT_PROJECT_ROOT', $this->testProjectDir);
        }
        
        // Register autoloader for models
        spl_autoload_register(function ($class) {
            $prefix = 'Reut\\Models\\';
            $baseDir = $this->testProjectDir . '/models/';
            
            if (strpos($class, $prefix) === 0) {
                $relativeClass = substr($class, strlen($prefix));
                $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
                if (file_exists($file)) {
                    require_once $file;
                }
            }
        });
        
        // Load core classes
        $vendorPath = __DIR__ . '/../../../vendor';
        if (file_exists($vendorPath . '/autoload.php')) {
            require_once $vendorPath . '/autoload.php';
        } else {
            // Fallback: try packages/core
            $corePath = __DIR__ . '/../../../packages/core/src';
            if (is_dir($corePath)) {
                $this->loadCoreClasses($corePath);
            }
        }
    }

    private function loadCoreClasses(string $basePath): void
    {
        $classes = [
            'DB/DataBase.php',
            'DB/Types/Integer.php',
            'DB/Types/Varchar.php',
            'DB/Types/Timestamp.php',
            'Auth/AuthRouter.php',
            'Support/ProjectPath.php',
        ];
        
        foreach ($classes as $class) {
            $file = $basePath . '/' . $class;
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }

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
                unlink($path);
            }
        }
        rmdir($dir);
    }

    /**
     * Test 1: Auth model generation creates UsersTable with correct structure
     */
    public function testAuthModelGenerationCreatesCorrectStructure(): void
    {
        // Ensure REUT_PROJECT_ROOT is set
        if (!defined('REUT_PROJECT_ROOT')) {
            define('REUT_PROJECT_ROOT', $this->testProjectDir);
        }
        
        // Clean up any existing model file first
        $modelFile = $this->testProjectDir . '/models/UsersTable.php';
        if (file_exists($modelFile)) {
            unlink($modelFile);
        }
        
        // Ensure models directory exists
        $modelsDir = dirname($modelFile);
        if (!is_dir($modelsDir)) {
            mkdir($modelsDir, 0755, true);
        }
        
        // Simulate auth model generation
        require_once __DIR__ . '/../../../src/createAuthModel.php';
        
        $result = createAuthModel('Users', 'email', false);
        $this->assertTrue($result, 'Auth model should be created successfully');
        
        $this->assertFileExists($modelFile, 'UsersTable.php should exist');
        
        // Verify model file content
        $content = file_get_contents($modelFile);
        $this->assertStringContainsString('class UsersTable extends DataBase', $content);
        $this->assertStringContainsString("addColumn('id'", $content);
        $this->assertStringContainsString("addColumn('email'", $content);
        $this->assertStringContainsString("addColumn('password'", $content);
        $this->assertStringContainsString("addColumn('created_at'", $content);
        $this->assertStringContainsString("addColumn('updated_at'", $content);
        
        // Verify updated_at has correct Timestamp definition
        // Extract the section with updated_at column definition
        $updatedAtStart = strpos($content, "addColumn('updated_at'");
        $this->assertNotFalse($updatedAtStart, 'updated_at column definition should exist');
        
        // Get a chunk of content around updated_at (600 chars should be enough)
        $updatedAtSection = substr($content, $updatedAtStart, 600);
        
        // Check that it contains "new Timestamp" followed by three parameters
        // The pattern should have: false, true, true (with possible comments/whitespace)
        $this->assertStringContainsString('new Timestamp', $updatedAtSection, 'Should contain new Timestamp');
        $this->assertStringContainsString('false', $updatedAtSection, 'Should contain false parameter');
        
        // Count commas after "new Timestamp(" - should have 2 commas (3 parameters)
        $timestampStart = strpos($updatedAtSection, 'new Timestamp(');
        $this->assertNotFalse($timestampStart, 'Should find new Timestamp(');
        $afterTimestamp = substr($updatedAtSection, $timestampStart);
        $closingParen = strpos($afterTimestamp, '));');
        $this->assertNotFalse($closingParen, 'Should find closing parens');
        $paramsSection = substr($afterTimestamp, 0, $closingParen);
        
        // Count commas in the parameters section
        $commaCount = substr_count($paramsSection, ',');
        $this->assertGreaterThanOrEqual(2, $commaCount, 
            'updated_at should have at least 2 commas (3 parameters). Found: ' . $commaCount . '. Section: ' . substr($paramsSection, 0, 200));
        
        // Verify created_at has correct Timestamp definition (two parameters)
        $createdAtStart = strpos($content, "addColumn('created_at'");
        $this->assertNotFalse($createdAtStart, 'created_at column definition should exist');
        $createdAtSection = substr($content, $createdAtStart, 400);
        
        $createdTimestampStart = strpos($createdAtSection, 'new Timestamp(');
        $this->assertNotFalse($createdTimestampStart, 'Should find new Timestamp for created_at');
        $afterCreatedTimestamp = substr($createdAtSection, $createdTimestampStart);
        $createdClosingParen = strpos($afterCreatedTimestamp, '));');
        $this->assertNotFalse($createdClosingParen, 'Should find closing parens for created_at');
        $createdParamsSection = substr($afterCreatedTimestamp, 0, $createdClosingParen);
        
        // Count commas - should have 1 comma (2 parameters)
        $createdCommaCount = substr_count($createdParamsSection, ',');
        $this->assertGreaterThanOrEqual(1, $createdCommaCount, 
            'created_at should have at least 1 comma (2 parameters). Found: ' . $createdCommaCount);
    }

    /**
     * Test 2: Auth model can be instantiated and has correct columns
     */
    public function testAuthModelInstantiationAndColumns(): void
    {
        // Generate model
        require_once __DIR__ . '/../../../src/createAuthModel.php';
        createAuthModel('Users', 'email', true); // Use force to overwrite if exists
        
        // Load config
        require $this->testProjectDir . '/config.php';
        
        // Instantiate model
        $modelClass = "Reut\\Models\\UsersTable";
        $model = new $modelClass($config);
        
        $this->assertInstanceOf(DataBase::class, $model);
        $this->assertArrayHasKey('id', $model->columns);
        $this->assertArrayHasKey('email', $model->columns);
        $this->assertArrayHasKey('password', $model->columns);
        $this->assertArrayHasKey('created_at', $model->columns);
        $this->assertArrayHasKey('updated_at', $model->columns);
    }

    /**
     * Test 3: Auth model table creation with correct updated_at definition (bug fix)
     */
    public function testAuthModelTableCreationWithCorrectUpdatedAt(): void
    {
        // Generate model
        require_once __DIR__ . '/../../../src/createAuthModel.php';
        createAuthModel('Users', 'email', true); // Use force to overwrite if exists
        
        // Load config
        require $this->testProjectDir . '/config.php';
        
        // Instantiate model
        $modelClass = "Reut\\Models\\UsersTable";
        $model = new $modelClass($config);
        
        // Create table - this should not throw an error about invalid default value
        try {
            $model->createTable();
            $this->assertTrue(true, 'Table creation should succeed without invalid default value error');
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'Invalid default value') !== false) {
                $this->fail('Table creation failed with invalid default value error - bug not fixed!');
            }
            throw $e;
        }
        
        // Verify table exists
        $this->assertTrue($model->tableExists('Users'), 'Users table should exist');
        
        // Verify column definitions in database
        $columns = $this->pdo->query("DESCRIBE Users")->fetchAll(PDO::FETCH_ASSOC);
        $updatedAtColumn = null;
        foreach ($columns as $column) {
            if ($column['Field'] === 'updated_at') {
                $updatedAtColumn = $column;
                break;
            }
        }
        
        $this->assertNotNull($updatedAtColumn, 'updated_at column should exist');
        $this->assertEquals('NO', $updatedAtColumn['Null'], 'updated_at should be NOT NULL');
        $this->assertStringContainsString('CURRENT_TIMESTAMP', strtoupper($updatedAtColumn['Default'] ?? ''), 
            'updated_at should have DEFAULT CURRENT_TIMESTAMP');
        $extra = strtolower($updatedAtColumn['Extra'] ?? '');
        $this->assertStringContainsString('on update', $extra, 
            'updated_at should have ON UPDATE CURRENT_TIMESTAMP');
        $this->assertStringContainsString('current_timestamp', $extra, 
            'updated_at Extra should contain current_timestamp');
    }

    /**
     * Test 4: Test user creation function works correctly
     */
    public function testCreateAuthUserFunction(): void
    {
        // Generate model and create table
        require_once __DIR__ . '/../../../src/createAuthModel.php';
        createAuthModel('Users', 'email', true); // Use force to overwrite if exists
        
        require $this->testProjectDir . '/config.php';
        $authConfig = require $this->testProjectDir . '/auth.php';
        
        $modelClass = "Reut\\Models\\UsersTable";
        $model = new $modelClass($config);
        $model->createTable();
        
        // Test user creation
        require_once __DIR__ . '/../../../src/createAuthUser.php';
        
        $result = createAuthUser('test@example.com', 'password123', $config, $authConfig);
        
        $this->assertTrue($result['success'], 'User creation should succeed');
        $this->assertStringContainsString('created successfully', $result['message']);
        
        // Verify user exists in database
        $user = $model->findOne(['email' => 'test@example.com']);
        $this->assertNotNull($user);
        $this->assertNotNull($user->results);
        $this->assertEquals('test@example.com', $user->results['email']);
        $this->assertNotEquals('password123', $user->results['password'], 'Password should be hashed');
        $this->assertTrue(password_verify('password123', $user->results['password']), 
            'Password should verify correctly');
    }

    /**
     * Test 5: .auth-setup.json file is created and read correctly
     */
    public function testAuthSetupJsonFileCreation(): void
    {
        $authSetupData = [
            'identifier' => 'testuser@example.com',
            'password' => 'testpass123',
            'table' => 'Users',
            'identifierField' => 'email'
        ];
        
        $setupFile = $this->testProjectDir . '/.auth-setup.json';
        file_put_contents($setupFile, json_encode($authSetupData, JSON_PRETTY_PRINT));
        
        $this->assertFileExists($setupFile, '.auth-setup.json should exist');
        
        $loaded = json_decode(file_get_contents($setupFile), true);
        $this->assertEquals($authSetupData, $loaded, 'Loaded data should match saved data');
    }

    /**
     * Test 6: AuthRouter prefers existing model files over auto-creation
     */
    public function testAuthRouterPrefersExistingModelFiles(): void
    {
        // Ensure REUT_PROJECT_ROOT is set
        if (!defined('REUT_PROJECT_ROOT')) {
            define('REUT_PROJECT_ROOT', $this->testProjectDir);
        }
        
        // Clean up any existing model file first
        $modelFile = $this->testProjectDir . '/models/UsersTable.php';
        if (file_exists($modelFile)) {
            unlink($modelFile);
        }
        
        // Ensure models directory exists
        $modelsDir = dirname($modelFile);
        if (!is_dir($modelsDir)) {
            mkdir($modelsDir, 0755, true);
        }
        
        // Verify file doesn't exist before creation
        $this->assertFileDoesNotExist($modelFile, 'Model file should not exist before creation');
        
        // Get resolved path (this is what createAuthModel will use)
        require_once __DIR__ . '/../../../packages/core/src/Support/ProjectPath.php';
        $resolvedPath = \Reut\Support\ProjectPath::resolve('models');
        $resolvedModelFile = $resolvedPath . '/UsersTable.php';
        
        // Clean up file at resolved path (this is where createAuthModel will create it)
        if (file_exists($resolvedModelFile)) {
            unlink($resolvedModelFile);
        }
        
        // Generate model file first
        require_once __DIR__ . '/../../../src/createAuthModel.php';
        
        // Double-check file doesn't exist at resolved path before calling
        $this->assertFileDoesNotExist($resolvedModelFile, 'Model file should not exist at resolved path before creation');
        
        // Use force=true to ensure we overwrite any existing file
        try {
            $result = createAuthModel('Users', 'email', true);
        } catch (\Exception $e) {
            $this->fail('createAuthModel threw exception: ' . $e->getMessage());
        }
        
        $this->assertTrue($result, 'Model should be created successfully');
        
        // Verify file exists after creation (check resolved path - this is where it was actually created)
        $this->assertFileExists($resolvedModelFile, 'Model file should exist at resolved path: ' . $resolvedModelFile);
        
        require $this->testProjectDir . '/config.php';
        $authConfig = require $this->testProjectDir . '/auth.php';
        
        // Create table using the model
        $modelClass = "Reut\\Models\\UsersTable";
        $model = new $modelClass($config);
        $model->createTable();
        
        // Create AuthRouter instance
        $app = $this->createMock(\Slim\App::class);
        $authRouter = new AuthRouter($app, $config, $authConfig);
        
        // Use reflection to call getAuthModel
        $reflection = new \ReflectionClass($authRouter);
        $method = $reflection->getMethod('getAuthModel');
        $method->setAccessible(true);
        
        $authModel = $method->invoke($authRouter);
        
        // Verify it's using the file-based model, not auto-created
        $this->assertInstanceOf(DataBase::class, $authModel);
        $this->assertEquals('Users', $authModel->tableName);
        
        // Verify the model file still exists (wasn't replaced by auto-creation)
        // Check at resolved path where it was actually created
        $this->assertFileExists($resolvedModelFile, 'Model file should still exist after AuthRouter at: ' . $resolvedModelFile);
        
        // Verify model has correct structure from file (check that it's the file-based version)
        $content = file_get_contents($resolvedModelFile);
        $this->assertStringContainsString("addColumn('email'", $content,
            'Model file should contain email column definition');
        
        // Check for updated_at with three parameters using the same method as test 1
        $updatedAtStart = strpos($content, "addColumn('updated_at'");
        $this->assertNotFalse($updatedAtStart, 'updated_at column definition should exist');
        $updatedAtSection = substr($content, $updatedAtStart, 600);
        
        $timestampStart = strpos($updatedAtSection, 'new Timestamp(');
        $this->assertNotFalse($timestampStart, 'Should find new Timestamp(');
        $afterTimestamp = substr($updatedAtSection, $timestampStart);
        $closingParen = strpos($afterTimestamp, '));');
        $this->assertNotFalse($closingParen, 'Should find closing parens');
        $paramsSection = substr($afterTimestamp, 0, $closingParen);
        
        // Count commas in the parameters section - should have at least 2 (3 parameters)
        $commaCount = substr_count($paramsSection, ',');
        $this->assertGreaterThanOrEqual(2, $commaCount,
            'updated_at should have at least 2 commas (3 parameters). Found: ' . $commaCount);
    }

    /**
     * Test 7: AuthRouter auto-creation uses correct updated_at definition (bug fix)
     */
    public function testAuthRouterAutoCreationUsesCorrectUpdatedAt(): void
    {
        // Don't create model file - let AuthRouter auto-create
        require $this->testProjectDir . '/config.php';
        $authConfig = require $this->testProjectDir . '/auth.php';
        
        // Create AuthRouter instance
        $app = $this->createMock(\Slim\App::class);
        $authRouter = new AuthRouter($app, $config, $authConfig);
        
        // Use reflection to call getAuthModel
        $reflection = new \ReflectionClass($authRouter);
        $method = $reflection->getMethod('getAuthModel');
        $method->setAccessible(true);
        
        $authModel = $method->invoke($authRouter);
        
        // Try to create table - should not fail with invalid default value
        try {
            $authModel->createTable();
            $this->assertTrue(true, 'Auto-created model table creation should succeed');
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'Invalid default value') !== false) {
                $this->fail('Auto-created model has invalid updated_at definition - bug not fixed!');
            }
            throw $e;
        }
        
        // Verify table exists
        $this->assertTrue($authModel->tableExists('Users'), 'Users table should exist');
    }

    /**
     * Test 8: Post-migration user creation from .auth-setup.json
     */
    public function testPostMigrationUserCreation(): void
    {
        // Generate model
        require_once __DIR__ . '/../../../src/createAuthModel.php';
        createAuthModel('Users', 'email', true); // Use force to overwrite if exists
        
        require $this->testProjectDir . '/config.php';
        $authConfig = require $this->testProjectDir . '/auth.php';
        
        // Create table (simulate migration)
        $modelClass = "Reut\\Models\\UsersTable";
        $model = new $modelClass($config);
        $model->createTable();
        
        // Create .auth-setup.json file
        $authSetupData = [
            'identifier' => 'migrationtest@example.com',
            'password' => 'migrationpass123',
            'table' => 'Users',
            'identifierField' => 'email'
        ];
        
        $setupFile = $this->testProjectDir . '/.auth-setup.json';
        file_put_contents($setupFile, json_encode($authSetupData, JSON_PRETTY_PRINT));
        
        // Simulate post-migration user creation
        require_once __DIR__ . '/../../../src/createAuthUser.php';
        
        $result = createAuthUser(
            $authSetupData['identifier'],
            $authSetupData['password'],
            $config,
            $authConfig
        );
        
        $this->assertTrue($result['success'], 'User should be created successfully');
        
        // Verify user exists
        $user = $model->findOne(['email' => 'migrationtest@example.com']);
        $this->assertNotNull($user);
        $this->assertNotNull($user->results);
        $this->assertEquals('migrationtest@example.com', $user->results['email']);
        
        // Verify setup file is deleted after successful creation (simulated)
        // In actual migrate.php, the file would be deleted
        // For this test, we just verify the user was created
    }

    /**
     * Test 9: Auth model generation handles different identifier fields
     */
    public function testAuthModelGenerationWithUsernameIdentifier(): void
    {
        // Ensure REUT_PROJECT_ROOT is set
        if (!defined('REUT_PROJECT_ROOT')) {
            define('REUT_PROJECT_ROOT', $this->testProjectDir);
        }
        
        // Delete existing UsersTable if it exists from previous tests
        $modelFile = $this->testProjectDir . '/models/UsersTable.php';
        if (file_exists($modelFile)) {
            unlink($modelFile);
        }
        
        // Ensure models directory exists
        $modelsDir = dirname($modelFile);
        if (!is_dir($modelsDir)) {
            mkdir($modelsDir, 0755, true);
        }
        
        // Verify file doesn't exist before creation
        $this->assertFileDoesNotExist($modelFile, 'Model file should not exist before creation');
        
        // Get resolved path (this is what createAuthModel will use)
        require_once __DIR__ . '/../../../packages/core/src/Support/ProjectPath.php';
        $resolvedPath = \Reut\Support\ProjectPath::resolve('models');
        $resolvedModelFile = $resolvedPath . '/UsersTable.php';
        
        // Clean up file at resolved path (this is where createAuthModel will create it)
        if (file_exists($resolvedModelFile)) {
            unlink($resolvedModelFile);
        }
        
        // Also clean up at expected path
        if (file_exists($modelFile) && $resolvedModelFile !== $modelFile) {
            unlink($modelFile);
        }
        
        require_once __DIR__ . '/../../../src/createAuthModel.php';
        
        // Double-check file doesn't exist at resolved path before calling
        $this->assertFileDoesNotExist($resolvedModelFile, 'Model file should not exist at resolved path before creation');
        
        // Use force=true to ensure we overwrite any existing file
        try {
            $result = createAuthModel('Users', 'username', true);
        } catch (\Exception $e) {
            $this->fail('createAuthModel threw exception: ' . $e->getMessage());
        }
        
        $this->assertTrue($result, 'Auth model with username should be created');
        
        // Verify file exists after creation (check resolved path)
        $this->assertFileExists($resolvedModelFile, 'Model file should exist at resolved path: ' . $resolvedModelFile);
        
        // Read from resolved path where file was actually created
        $content = file_get_contents($resolvedModelFile);
        $this->assertNotFalse($content, 'Should be able to read model file content');
        
        $this->assertStringContainsString("addColumn('username'", $content);
        $this->assertStringNotContainsString("addColumn('email'", $content);
    }

    /**
     * Test 10: Auth model generation respects existing files (no overwrite)
     */
    public function testAuthModelGenerationRespectsExistingFiles(): void
    {
        // Create existing model file
        $modelFile = $this->testProjectDir . '/models/UsersTable.php';
        file_put_contents($modelFile, '<?php // Existing model');
        
        require_once __DIR__ . '/../../../src/createAuthModel.php';
        
        $result = createAuthModel('Users', 'email', false);
        $this->assertFalse($result, 'Should not overwrite existing model');
        
        $content = file_get_contents($modelFile);
        $this->assertEquals('<?php // Existing model', $content, 'Existing file should not be modified');
    }
}


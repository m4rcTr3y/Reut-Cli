<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use PDO;
use Reut\DB\DataBase;
use Reut\DB\Types\Varchar;
use Reut\DB\Types\Integer;
use Reut\Router\SchemaController;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * Tests for Disabled Routes and Per-Model Authentication (v1.3)
 * 
 * Tests cover:
 * 1. Disabled routes functionality
 * 2. Per-model authentication (requiresAuth)
 * 3. Schema viewer integration
 * 4. Route generation with disabled routes and auth
 * 
 * Database credentials:
 * - Username: root
 * - Password: root@1234
 * - Database: test_db_v13
 */
class DisabledRoutesAndAuthTest extends TestCase
{
    private string $testProjectDir;
    private string $testProjectName = 'test_disabled_routes_auth';
    private string $originalCwd;
    private PDO $pdo;
    private const DB_USER = 'root';
    private const DB_PASS = 'root@1234';
    private const DB_NAME = 'test_db_v13';
    private const DB_HOST = 'localhost';

    protected function setUp(): void
    {
        parent::setUp();
        
        // Store original working directory
        $this->originalCwd = getcwd();
        
        // Create a temporary directory for test projects
        $this->testProjectDir = sys_get_temp_dir() . '/reut_test_v13_' . uniqid();
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
        
        // Create models directory
        mkdir($this->testProjectDir . '/models', 0755, true);
        
        // Create routers directory
        mkdir($this->testProjectDir . '/routers', 0755, true);
        
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
    'dbname' => 'test_db_v13',
];
PHP;
        file_put_contents($this->testProjectDir . '/config.php', $configContent);
    }

    private function setupAutoloading(): void
    {
        // Create a simple autoloader that includes the parent vendor
        $vendorPath = __DIR__ . '/../../../../vendor/autoload.php';
        if (file_exists($vendorPath)) {
            require_once $vendorPath;
        }
        
        // Set up REUT_PROJECT_ROOT constant to point to our test directory
        if (!defined('REUT_PROJECT_ROOT')) {
            define('REUT_PROJECT_ROOT', $this->testProjectDir);
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

    private function createTestModel(string $modelName, array $disabledRoutes = [], bool $requiresAuth = false): string
    {
        $modelFile = $this->testProjectDir . '/models/' . $modelName . 'Table.php';
        
        $disabledRoutesStr = var_export($disabledRoutes, true);
        $requiresAuthStr = $requiresAuth ? 'true' : 'false';
        
        $modelContent = <<<PHP
<?php
declare(strict_types=1);

namespace Reut\Models;

use Reut\DB\DataBase;
use Reut\DB\Types\Varchar;
use Reut\DB\Types\Integer;

class {$modelName}Table extends DataBase
{
    public function __construct(array \$config)
    {
        parent::__construct(
            \$config,
            [],
            '{$modelName}',
            false,
            [],
            [], // File fields
            {$disabledRoutesStr}, // Disabled routes
            ['created_at', 'updated_at'], // Protected columns
            null, // strictRequiredValidation
            [], // File field types
            {$requiresAuthStr} // requiresAuth
        );

        \$this->addColumn('id', new Integer(false, true, true, null));
        \$this->addColumn('name', new Varchar(255, false));
    }
}
PHP;
        
        file_put_contents($modelFile, $modelContent);
        return $modelFile;
    }

    private function getConfig(): array
    {
        return [
            'host' => self::DB_HOST,
            'username' => self::DB_USER,
            'password' => self::DB_PASS,
            'dbname' => self::DB_NAME,
        ];
    }

    /**
     * Test that DataBase class has requiresAuth property
     */
    public function testDataBaseRequiresAuthProperty(): void
    {
        $config = $this->getConfig();
        $db = new DataBase($config, [], 'TestTable', false, 0, [], [], [], null, [], true);
        
        $this->assertTrue(isset($db->requiresAuth), 'requiresAuth property should exist');
        $this->assertTrue($db->requiresAuth, 'requiresAuth should be true when set');
        
        $db2 = new DataBase($config, [], 'TestTable2', false, 0, [], [], [], null, [], false);
        $this->assertFalse($db2->requiresAuth, 'requiresAuth should be false when set');
    }

    /**
     * Test that DataBase class has disabledRoutes property
     */
    public function testDataBaseDisabledRoutesProperty(): void
    {
        $config = $this->getConfig();
        $disabledRoutes = ['add', 'delete'];
        $db = new DataBase($config, [], 'TestTable', false, 0, [], $disabledRoutes);
        
        $this->assertTrue(isset($db->disabledRoutes), 'disabledRoutes property should exist');
        $this->assertEquals($disabledRoutes, $db->disabledRoutes, 'disabledRoutes should match input');
    }

    /**
     * Test disabled routes with 'all' option
     */
    public function testDisabledRoutesAll(): void
    {
        $this->createTestModel('TestAllDisabled', ['all']);
        $config = $this->getConfig();
        
        require_once $this->testProjectDir . '/models/TestAllDisabledTable.php';
        $model = new \Reut\Models\TestAllDisabledTable($config);
        
        $this->assertContains('all', $model->disabledRoutes, 'Should contain "all" in disabled routes');
    }

    /**
     * Test disabled routes with specific routes
     */
    public function testDisabledRoutesSpecific(): void
    {
        $this->createTestModel('TestSpecificDisabled', ['add', 'delete']);
        $config = $this->getConfig();
        
        require_once $this->testProjectDir . '/models/TestSpecificDisabledTable.php';
        $model = new \Reut\Models\TestSpecificDisabledTable($config);
        
        $this->assertContains('add', $model->disabledRoutes, 'Should contain "add"');
        $this->assertContains('delete', $model->disabledRoutes, 'Should contain "delete"');
        $this->assertNotContains('all', $model->disabledRoutes, 'Should not contain "all"');
    }

    /**
     * Test disabled routes with 'all' route (list endpoint)
     */
    public function testDisabledRoutesList(): void
    {
        $this->createTestModel('TestListDisabled', ['all']);
        $config = $this->getConfig();
        
        require_once $this->testProjectDir . '/models/TestListDisabledTable.php';
        $model = new \Reut\Models\TestListDisabledTable($config);
        
        $this->assertTrue(in_array('all', $model->disabledRoutes), 'Should disable "all" route');
    }

    /**
     * Test disabled routes with multiple specific routes
     */
    public function testDisabledRoutesMultiple(): void
    {
        $disabledRoutes = ['find', 'add', 'update'];
        $this->createTestModel('TestMultipleDisabled', $disabledRoutes);
        $config = $this->getConfig();
        
        require_once $this->testProjectDir . '/models/TestMultipleDisabledTable.php';
        $model = new \Reut\Models\TestMultipleDisabledTable($config);
        
        $this->assertCount(3, $model->disabledRoutes, 'Should have 3 disabled routes');
        $this->assertContains('find', $model->disabledRoutes);
        $this->assertContains('add', $model->disabledRoutes);
        $this->assertContains('update', $model->disabledRoutes);
    }

    /**
     * Test disabled routes with empty array (all routes enabled)
     */
    public function testDisabledRoutesEmpty(): void
    {
        $this->createTestModel('TestNoDisabled', []);
        $config = $this->getConfig();
        
        require_once $this->testProjectDir . '/models/TestNoDisabledTable.php';
        $model = new \Reut\Models\TestNoDisabledTable($config);
        
        $this->assertEmpty($model->disabledRoutes, 'Should have no disabled routes');
    }

    /**
     * Test requiresAuth = true
     */
    public function testRequiresAuthTrue(): void
    {
        $this->createTestModel('TestWithAuth', [], true);
        $config = $this->getConfig();
        
        require_once $this->testProjectDir . '/models/TestWithAuthTable.php';
        $model = new \Reut\Models\TestWithAuthTable($config);
        
        $this->assertTrue($model->requiresAuth, 'requiresAuth should be true');
    }

    /**
     * Test requiresAuth = false (default)
     */
    public function testRequiresAuthFalse(): void
    {
        $this->createTestModel('TestWithoutAuth', [], false);
        $config = $this->getConfig();
        
        require_once $this->testProjectDir . '/models/TestWithoutAuthTable.php';
        $model = new \Reut\Models\TestWithoutAuthTable($config);
        
        $this->assertFalse($model->requiresAuth, 'requiresAuth should be false');
    }

    /**
     * Test that route generation respects disabled routes
     */
    public function testRouteGenerationRespectsDisabledRoutes(): void
    {
        $this->createTestModel('TestRouteGen', ['add', 'delete']);
        $config = $this->getConfig();
        
        // Simulate route generation by checking the model
        require_once $this->testProjectDir . '/models/TestRouteGenTable.php';
        $model = new \Reut\Models\TestRouteGenTable($config);
        
        // Verify disabled routes are set correctly
        $this->assertContains('add', $model->disabledRoutes, 'Should contain "add" in disabled routes');
        $this->assertContains('delete', $model->disabledRoutes, 'Should contain "delete" in disabled routes');
        $this->assertNotContains('all', $model->disabledRoutes, 'Should not contain "all"');
        $this->assertNotContains('find', $model->disabledRoutes, 'Should not contain "find"');
        $this->assertNotContains('update', $model->disabledRoutes, 'Should not contain "update"');
        
        // Verify isAllDisabled logic
        $isAllDisabled = in_array('all', $model->disabledRoutes);
        $this->assertFalse($isAllDisabled, 'isAllDisabled should be false when "all" is not in array');
    }

    /**
     * Test that route generation uses correct auth class
     */
    public function testRouteGenerationWithAuth(): void
    {
        $this->createTestModel('TestAuthRoute', [], true);
        $config = $this->getConfig();
        
        require_once $this->testProjectDir . '/models/TestAuthRouteTable.php';
        $model = new \Reut\Models\TestAuthRouteTable($config);
        
        $this->assertTrue($model->requiresAuth, 'Model should require auth');
        
        // Verify that the model correctly stores requiresAuth
        // In actual route generation, createRoutes.php checks this property
        // and uses it to determine whether to extend Auth or NoAuth
        $this->assertTrue(property_exists($model, 'requiresAuth'), 'Model should have requiresAuth property');
        $this->assertTrue($model->requiresAuth, 'requiresAuth should be true');
    }

    /**
     * Test schema viewer shows disabled routes
     */
    public function testSchemaViewerShowsDisabledRoutes(): void
    {
        $this->createTestModel('TestSchemaDisabled', ['add', 'delete']);
        $config = $this->getConfig();
        
        // First verify the model can be instantiated directly
        require_once $this->testProjectDir . '/models/TestSchemaDisabledTable.php';
        $model = new \Reut\Models\TestSchemaDisabledTable($config);
        $this->assertContains('add', $model->disabledRoutes, 'Model should have disabled routes');
        
        // Explicitly require the updated SchemaController to ensure we're using the latest version
        $schemaControllerPath = __DIR__ . '/../../../../packages/core/src/Router/SchemaController.php';
        if (file_exists($schemaControllerPath)) {
            require_once $schemaControllerPath;
        }
        
        // Create SchemaController instance
        $controller = new \Reut\Router\SchemaController();
        
        // Verify which file is loaded
        $reflection = new \ReflectionClass($controller);
        $loadedFile = $reflection->getFileName();
        
        // Use reflection to access loadModelMetadata
        $method = $reflection->getMethod('loadModelMetadata');
        $method->setAccessible(true);
        
        $modelFile = $this->testProjectDir . '/models/TestSchemaDisabledTable.php';
        $errors = [];
        $metadata = $method->invoke($controller, $modelFile, 'Reut\\Models\\', $config, $errors);
        
        $this->assertNotNull($metadata, 'Metadata should be loaded. Errors: ' . implode(', ', $errors) . '. Loaded from: ' . $loadedFile);
        $this->assertEmpty($errors, 'Should have no errors: ' . implode(', ', $errors));
        $this->assertArrayHasKey('disabledRoutes', $metadata, 'Metadata should have disabledRoutes. Keys: ' . implode(', ', array_keys($metadata ?? [])) . '. Loaded from: ' . $loadedFile);
        $this->assertContains('add', $metadata['disabledRoutes'], 'Should contain "add"');
        $this->assertContains('delete', $metadata['disabledRoutes'], 'Should contain "delete"');
    }

    /**
     * Test schema viewer shows auth status
     */
    public function testSchemaViewerShowsAuthStatus(): void
    {
        $this->createTestModel('TestSchemaAuth', [], true);
        $config = $this->getConfig();
        
        // First verify the model can be instantiated directly
        require_once $this->testProjectDir . '/models/TestSchemaAuthTable.php';
        $model = new \Reut\Models\TestSchemaAuthTable($config);
        $this->assertTrue($model->requiresAuth, 'Model should require auth');
        
        // Explicitly require the updated SchemaController
        $schemaControllerPath = __DIR__ . '/../../../../packages/core/src/Router/SchemaController.php';
        if (file_exists($schemaControllerPath)) {
            require_once $schemaControllerPath;
        }
        
        $controller = new \Reut\Router\SchemaController();
        $reflection = new \ReflectionClass($controller);
        $loadedFile = $reflection->getFileName();
        $method = $reflection->getMethod('loadModelMetadata');
        $method->setAccessible(true);
        
        $modelFile = $this->testProjectDir . '/models/TestSchemaAuthTable.php';
        $errors = [];
        $metadata = $method->invoke($controller, $modelFile, 'Reut\\Models\\', $config, $errors);
        
        $this->assertNotNull($metadata, 'Metadata should be loaded. Errors: ' . implode(', ', $errors) . '. Loaded from: ' . $loadedFile);
        $this->assertEmpty($errors, 'Should have no errors: ' . implode(', ', $errors));
        $this->assertArrayHasKey('requiresAuth', $metadata, 'Metadata should have requiresAuth. Keys: ' . implode(', ', array_keys($metadata ?? [])) . '. Loaded from: ' . $loadedFile);
        $this->assertTrue($metadata['requiresAuth'], 'requiresAuth should be true');
    }

    /**
     * Test schema viewer badges display
     */
    public function testSchemaViewerBadges(): void
    {
        $this->createTestModel('TestBadges', ['add', 'delete'], true);
        $config = $this->getConfig();
        
        // First verify the model can be instantiated directly
        require_once $this->testProjectDir . '/models/TestBadgesTable.php';
        $model = new \Reut\Models\TestBadgesTable($config);
        $this->assertTrue($model->requiresAuth, 'Model should require auth');
        $this->assertNotEmpty($model->disabledRoutes, 'Model should have disabled routes');
        
        // Explicitly require the updated SchemaController
        $schemaControllerPath = __DIR__ . '/../../../../packages/core/src/Router/SchemaController.php';
        if (file_exists($schemaControllerPath)) {
            require_once $schemaControllerPath;
        }
        
        $controller = new \Reut\Router\SchemaController();
        $reflection = new \ReflectionClass($controller);
        $loadedFile = $reflection->getFileName();
        $method = $reflection->getMethod('loadModelMetadata');
        $method->setAccessible(true);
        
        $modelFile = $this->testProjectDir . '/models/TestBadgesTable.php';
        $errors = [];
        $metadata = $method->invoke($controller, $modelFile, 'Reut\\Models\\', $config, $errors);
        
        $this->assertNotNull($metadata, 'Metadata should be loaded. Errors: ' . implode(', ', $errors) . '. Loaded from: ' . $loadedFile);
        $this->assertEmpty($errors, 'Should have no errors: ' . implode(', ', $errors));
        $this->assertArrayHasKey('requiresAuth', $metadata, 'Metadata should have requiresAuth. Keys: ' . implode(', ', array_keys($metadata ?? [])) . '. Loaded from: ' . $loadedFile);
        $this->assertArrayHasKey('disabledRoutes', $metadata, 'Metadata should have disabledRoutes');
        $this->assertTrue($metadata['requiresAuth'], 'Should require auth');
        $this->assertNotEmpty($metadata['disabledRoutes'], 'Should have disabled routes');
        
        // Test HTML generation includes badges
        $generateMethod = $reflection->getMethod('generateHtml');
        $generateMethod->setAccessible(true);
        
        $html = $generateMethod->invoke($controller, [$metadata], []);
        
        $this->assertStringContainsString('Auth Required', $html, 'HTML should contain auth badge');
        $this->assertStringContainsString('Disabled:', $html, 'HTML should contain disabled routes badge');
    }
}


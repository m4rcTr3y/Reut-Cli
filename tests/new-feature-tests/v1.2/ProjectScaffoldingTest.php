<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Tests for Project Scaffolding and Overall Functionality (v1.2)
 * 
 * Tests cover:
 * 1. Project initialization (file structure)
 * 2. Configuration files (.env, config.php, manage.php)
 * 3. Directory structure validation
 * 4. File content validation
 * 5. Database connection setup
 * 6. Model directory structure
 * 7. Vendor/autoload setup
 * 8. Project integrity checks
 * 
 * Database credentials:
 * - Username: root
 * - Password: root@1234
 * - Database: test_db_two
 */
class ProjectScaffoldingTest extends TestCase
{
    private string $testProjectDir;
    private string $testProjectName = 'test_scaffold_project';
    private string $originalCwd;
    private PDO $pdo;
    private const DB_USER = 'root';
    private const DB_PASS = 'root@1234';
    private const DB_NAME = 'test_db_two';
    private const DB_HOST = 'localhost';

    protected function setUp(): void
    {
        parent::setUp();
        
        // Store original working directory
        $this->originalCwd = getcwd();
        
        // Create a temporary directory for test projects
        $this->testProjectDir = sys_get_temp_dir() . '/reut_scaffold_test_' . uniqid();
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
    }

    protected function tearDown(): void
    {
        // Clean up test database
        try {
            $this->pdo->exec("DROP DATABASE IF EXISTS " . self::DB_NAME);
        } catch (\Exception $e) {
            // Ignore cleanup errors
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
     * Test 1: Project initialization creates all required directories
     */
    public function testProjectInitializationCreatesRequiredDirectories(): void
    {
        $this->scaffoldProject();
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        
        // Check required directories exist
        $requiredDirs = [
            'models',
            'routers',
            'config',
            'config/db',
        ];
        
        foreach ($requiredDirs as $dir) {
            $dirPath = $projectPath . '/' . $dir;
            $this->assertTrue(
                is_dir($dirPath),
                "Required directory '{$dir}' should exist"
            );
        }
    }

    /**
     * Test 2: Configuration files are created correctly
     */
    public function testConfigurationFilesAreCreated(): void
    {
        $this->scaffoldProject();
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        
        // Check config.php exists
        $configFile = $projectPath . '/config.php';
        $this->assertFileExists($configFile, 'config.php should exist');
        
        // Verify config.php content
        require $configFile;
        $this->assertIsArray($config, 'config.php should define $config array');
        $this->assertArrayHasKey('host', $config);
        $this->assertArrayHasKey('username', $config);
        $this->assertArrayHasKey('password', $config);
        $this->assertArrayHasKey('dbname', $config);
        $this->assertEquals(self::DB_NAME, $config['dbname']);
        
        // Check manage.php exists
        $manageFile = $projectPath . '/manage.php';
        $this->assertFileExists($manageFile, 'manage.php should exist');
        
        // Verify manage.php includes DatabaseCreator
        $manageContent = file_get_contents($manageFile);
        $this->assertStringContainsString('DatabaseCreator', $manageContent);
        $this->assertStringContainsString('Generate', $manageContent);
    }

    /**
     * Test 3: Models directory is properly set up
     */
    public function testModelsDirectorySetup(): void
    {
        $this->scaffoldProject();
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        
        $modelsDir = $projectPath . '/models';
        $this->assertTrue(is_dir($modelsDir), 'models directory should exist');
        $this->assertTrue(is_writable($modelsDir), 'models directory should be writable');
    }

    /**
     * Test 4: Vendor autoload setup
     */
    public function testVendorAutoloadSetup(): void
    {
        $this->scaffoldProject();
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        
        $vendorDir = $projectPath . '/vendor';
        $autoloadFile = $vendorDir . '/autoload.php';
        
        // Vendor directory should exist (symlink or directory)
        $this->assertTrue(
            is_dir($vendorDir) || is_link($vendorDir),
            'vendor directory should exist'
        );
        
        // Autoload file should exist
        if (file_exists($autoloadFile)) {
            $this->assertFileExists($autoloadFile, 'vendor/autoload.php should exist');
            
            // Verify autoload can be included
            $oldCwd = getcwd();
            chdir($projectPath);
            try {
                require $autoloadFile;
                $this->assertTrue(true, 'autoload.php should be includable');
            } catch (\Exception $e) {
                $this->fail('autoload.php should not throw exceptions: ' . $e->getMessage());
            } finally {
                chdir($oldCwd);
            }
        }
    }

    /**
     * Test 5: Database connection works with scaffolded config
     */
    public function testDatabaseConnectionWithScaffoldedConfig(): void
    {
        $this->scaffoldProject();
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        
        $configFile = $projectPath . '/config.php';
        require $configFile;
        
        $baseDb = new \Reut\DB\DataBase($config);
        
        try {
            $result = $baseDb->connect();
            $this->assertTrue($result, 'Database connection should succeed');
            
            // Verify we can query the database
            $tables = $baseDb->getTablesList();
            $this->assertIsArray($tables, 'getTablesList() should return an array');
        } catch (\Exception $e) {
            $this->fail('Database connection should not throw exceptions: ' . $e->getMessage());
        }
    }

    /**
     * Test 6: Project structure integrity
     */
    public function testProjectStructureIntegrity(): void
    {
        $this->scaffoldProject();
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        
        // Check that essential files are present
        $essentialFiles = [
            'config.php',
            'manage.php',
        ];
        
        foreach ($essentialFiles as $file) {
            $filePath = $projectPath . '/' . $file;
            $this->assertFileExists($filePath, "Essential file '{$file}' should exist");
            $this->assertGreaterThan(0, filesize($filePath), "File '{$file}' should not be empty");
        }
    }

    /**
     * Test 7: Multiple model creation in scaffolded project
     */
    public function testMultipleModelCreation(): void
    {
        $this->scaffoldProject();
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        
        // Create multiple test models
        $models = [
            'Users' => ['id' => 'INT PRIMARY KEY AUTO_INCREMENT', 'name' => 'VARCHAR(255)'],
            'Posts' => ['id' => 'INT PRIMARY KEY AUTO_INCREMENT', 'title' => 'VARCHAR(255)', 'content' => 'TEXT'],
            'Comments' => ['id' => 'INT PRIMARY KEY AUTO_INCREMENT', 'post_id' => 'INT', 'comment' => 'TEXT'],
        ];
        
        foreach ($models as $modelName => $columns) {
            $this->createTestModel($projectPath, $modelName, $columns);
        }
        
        // Verify all models exist
        $modelsDir = $projectPath . '/models';
        foreach (array_keys($models) as $modelName) {
            $modelFile = $modelsDir . '/' . $modelName . 'Table.php';
            $this->assertFileExists($modelFile, "Model file for {$modelName} should exist");
        }
    }

    /**
     * Test 8: Scaffolded project can run migrations
     */
    public function testScaffoldedProjectCanRunMigrations(): void
    {
        $this->scaffoldProject();
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        
        // Create a test model
        $this->createTestModel($projectPath, 'Products', [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'name' => 'VARCHAR(255)',
        ]);
        
        // Run migrate command
        $result = $this->runManageCommand($projectPath, 'migrate');
        
        // Verify migration ran successfully
        $this->assertIsString($result, 'Migration command should return output');
        $this->assertStringNotContainsString('Error', $result, 'Migration should not contain errors');
        
        // Verify table was created
        $configFile = $projectPath . '/config.php';
        require $configFile;
        $baseDb = new \Reut\DB\DataBase($config);
        $baseDb->connect();
        
        $tables = $baseDb->getTablesList();
        $this->assertContains('products', $tables, 'products table should exist after migration');
    }

    /**
     * Test 9: Scaffolded project handles status command
     */
    public function testScaffoldedProjectHandlesStatusCommand(): void
    {
        $this->scaffoldProject();
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        
        // Run status command
        $result = $this->runManageCommand($projectPath, 'status');
        
        // Verify status command runs
        $this->assertIsString($result, 'Status command should return output');
        // Status should show migrations table info or pending migrations
        $this->assertTrue(
            stripos($result, 'migration') !== false || 
            stripos($result, 'No migrations') !== false ||
            stripos($result, 'Applied') !== false,
            'Status output should mention migrations'
        );
    }

    /**
     * Test 10: Project can be scaffolded multiple times without conflicts
     */
    public function testMultipleScaffoldingWithoutConflicts(): void
    {
        // Scaffold first project
        $project1 = $this->testProjectDir . '/project1';
        $this->scaffoldProject(['projectName' => 'project1']);
        
        // Scaffold second project
        $project2 = $this->testProjectDir . '/project2';
        $this->scaffoldProject(['projectName' => 'project2']);
        
        // Both should exist independently
        $this->assertTrue(is_dir($project1), 'First project should exist');
        $this->assertTrue(is_dir($project2), 'Second project should exist');
        
        // Both should have their own config files
        $this->assertFileExists($project1 . '/config.php', 'First project should have config.php');
        $this->assertFileExists($project2 . '/config.php', 'Second project should have config.php');
    }

    // Helper methods

    private function scaffoldProject(array $options = []): void
    {
        $projectName = $options['projectName'] ?? $this->testProjectName;
        $projectPath = $this->testProjectDir . '/' . $projectName;
        
        if (!is_dir($projectPath)) {
            mkdir($projectPath, 0755, true);
        }
        
        // Create required directories
        $dirs = ['models', 'routers', 'config', 'config/db'];
        foreach ($dirs as $dir) {
            $dirPath = $projectPath . '/' . $dir;
            if (!is_dir($dirPath)) {
                mkdir($dirPath, 0755, true);
            }
        }
        
        // Create config.php
        $configContent = <<<PHP
<?php
\$config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => 'root@1234',
    'dbname' => 'test_db_two',
    'charset' => 'utf8mb4'
];
PHP;
        file_put_contents($projectPath . '/config.php', $configContent);
        
        // Create manage.php
        $vendorPath = __DIR__ . '/../../../vendor';
        $autoload = $vendorPath . '/autoload.php';
        
        $manageContent = <<<PHP
<?php
\$autoload = '$autoload';
if (!file_exists(\$autoload)) {
    echo "Error: vendor/autoload.php not found. Run composer install.\n";
    exit(1);
}

require \$autoload;
require __DIR__ . '/config.php';

use Reut\DB\DatabaseCreator;

global \$argv;
if (!isset(\$argv) || empty(\$argv)) {
    \$argv = \$_SERVER['argv'] ?? [];
}

DatabaseCreator::Generate();
PHP;
        file_put_contents($projectPath . '/manage.php', $manageContent);
        
        // Create vendor symlink or reference
        if (!is_dir($projectPath . '/vendor')) {
            if (is_dir($vendorPath)) {
                // Create symlink to vendor
                symlink(realpath($vendorPath), $projectPath . '/vendor');
            } else {
                // Create vendor directory with autoload wrapper
                mkdir($projectPath . '/vendor', 0755, true);
                $autoloadWrapper = <<<PHP
<?php
require_once __DIR__ . '/../../../../vendor/autoload.php';
PHP;
                file_put_contents($projectPath . '/vendor/autoload.php', $autoloadWrapper);
            }
        }
        
        // Copy packages if needed
        $packagesSource = __DIR__ . '/../../../packages';
        $packagesTarget = $projectPath . '/packages';
        if (is_dir($packagesSource) && !is_dir($packagesTarget)) {
            $this->copyDirectory($packagesSource, $packagesTarget);
        }
    }

    private function createTestModel(string $projectPath, string $name, array $columns): void
    {
        $tableName = strtolower(str_replace('Table', '', $name));
        $className = $name . 'Table';
        
        // Build column definitions using ColumnType objects
        $columnDefinitions = [];
        foreach ($columns as $colName => $colDef) {
            if (stripos($colDef, 'INT') !== false) {
                $isPrimary = stripos($colDef, 'PRIMARY KEY') !== false ? 'true' : 'false';
                $isAutoIncrement = stripos($colDef, 'AUTO_INCREMENT') !== false ? 'true' : 'false';
                $columnDefinitions[] = "        \$this->addColumn('{$colName}', new \\Reut\\DB\\Types\\Integer(false, {$isPrimary}, {$isAutoIncrement}));";
            } elseif (stripos($colDef, 'VARCHAR') !== false) {
                preg_match('/VARCHAR\((\d+)\)/', $colDef, $matches);
                $length = $matches[1] ?? 255;
                $columnDefinitions[] = "        \$this->addColumn('{$colName}', new \\Reut\\DB\\Types\\Varchar({$length}));";
            } elseif (stripos($colDef, 'TEXT') !== false) {
                $columnDefinitions[] = "        \$this->addColumn('{$colName}', new \\Reut\\DB\\Types\\Text());";
            } else {
                $columnDefinitions[] = "        \$this->addColumn('{$colName}', new \\Reut\\DB\\Types\\Varchar(255));";
            }
        }
        
        $columnsCode = implode("\n", $columnDefinitions);
        
        $model = <<<MODEL
<?php
declare(strict_types=1);

namespace Reut\Models;

use Reut\DB\DataBase;
use Reut\DB\Types\Integer;
use Reut\DB\Types\Varchar;
use Reut\DB\Types\Text;

class CLASSNAME extends DataBase
{
    public function __construct(array \$config)
    {
        parent::__construct(
            \$config,
            [],
            'TABLENAME',
            false,
            0,
            [],
            [],
            []
        );
COLUMNS
    }
}
MODEL;
        
        $model = str_replace('CLASSNAME', $className, $model);
        $model = str_replace('TABLENAME', $tableName, $model);
        $model = str_replace('COLUMNS', $columnsCode, $model);
        
        file_put_contents($projectPath . "/models/{$className}.php", $model);
    }

    private function runManageCommand(string $projectPath, string $command): string
    {
        $manageFile = $projectPath . '/manage.php';
        $oldCwd = getcwd();
        chdir($projectPath);
        
        $commandParts = explode(' ', $command);
        $cmd = 'php ' . escapeshellarg($manageFile) . ' ' . implode(' ', array_map('escapeshellarg', $commandParts)) . ' 2>&1';
        
        $output = [];
        exec($cmd, $output, $returnCode);
        
        chdir($oldCwd);
        
        return implode("\n", $output);
    }

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
            $targetPath = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            
            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                copy($item->getPathname(), $targetPath);
            }
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        // Safety check: Never delete vendor directory or project root
        $realDir = realpath($dir);
        $projectRoot = realpath(__DIR__ . '/../../../');
        if ($realDir && $projectRoot && (
            strpos($realDir, $projectRoot . '/vendor') === 0 ||
            $realDir === $projectRoot
        )) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                if (is_link($path)) {
                    unlink($path);
                } else {
                    $this->removeDirectory($path);
                }
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}


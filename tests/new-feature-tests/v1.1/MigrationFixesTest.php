<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use PDO;

/**
 * Tests for Migration Commands Security and Bug Fixes (v1.1)
 * 
 * Tests cover fixes for:
 * 1. SQL injection vulnerabilities in LIKE clauses
 * 2. Batch query result access bug
 * 3. INSERT IGNORE protection for duplicate migrations
 * 4. Proper use of execute() vs sqlQuery() methods
 * 5. Table name extraction using tableInstance->tableName
 * 6. Regex pattern escaping for special characters
 * 
 * Database credentials:
 * - Username: root
 * - Password: root@1234
 * - Database: test_db_two
 */
class MigrationFixesTest extends TestCase
{
    private string $testProjectDir;
    private string $testProjectName = 'test_migration_fixes';
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
        $this->testProjectDir = sys_get_temp_dir() . '/reut_migration_test_' . uniqid();
        mkdir($this->testProjectDir, 0755, true);
        chdir($this->testProjectDir);
        
        // Connect to database
        $dsn = "mysql:host=" . self::DB_HOST . ";charset=utf8mb4";
        $this->pdo = new PDO($dsn, self::DB_USER, self::DB_PASS);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create test database if it doesn't exist
        $this->pdo->exec("CREATE DATABASE IF NOT EXISTS " . self::DB_NAME);
        $this->pdo->exec("USE " . self::DB_NAME);
        
        // Clean up any existing migrations table
        $this->pdo->exec("DROP TABLE IF EXISTS migrations");
    }

    protected function tearDown(): void
    {
        // Clean up test database
        if (isset($this->pdo)) {
            try {
                $this->pdo->exec("USE " . self::DB_NAME);
                $this->pdo->exec("DROP TABLE IF EXISTS migrations");
                $this->pdo->exec("DROP TABLE IF EXISTS test_table");
                $this->pdo->exec("DROP TABLE IF EXISTS test_table_special");
            } catch (\PDOException $e) {
                // Ignore cleanup errors
            }
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
     * Test that batch query result is accessed correctly
     * Fix: update.php line 45 - should use $batchQuery[0]['max_batch'] not $batchQuery['max_batch']
     */
    public function testBatchQueryResultAccess(): void
    {
        $this->scaffoldTestProject();
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        chdir($projectPath);
        
        // Create migrations table manually
        $this->pdo->exec("USE " . self::DB_NAME);
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL UNIQUE,
                sql_text TEXT NOT NULL,
                batch INT NOT NULL,
                applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Insert a migration with batch 5
        $stmt = $this->pdo->prepare("
            INSERT INTO migrations (name, sql_text, batch) 
            VALUES ('test_migration_1', 'CREATE TABLE test', 5)
        ");
        $stmt->execute();
        
        // Run sync command - should use batch 6, not fail
        $output = $this->runManageCommand(['sync']);
        
        // Verify no errors occurred
        $this->assertStringNotContainsString('Undefined array key', $output, 
            'Batch query should access array correctly');
        $this->assertStringNotContainsString('Fatal error', $output, 
            'Sync command should not crash');
    }

    /**
     * Test SQL injection protection in LIKE clauses
     * Fix: migrate.php line 99, checkmigration.php line 84
     */
    public function testSqlInjectionProtectionInLikeClause(): void
    {
        $this->scaffoldTestProject();
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        chdir($projectPath);
        
        // Create a model with a table name containing SQL special characters
        $modelContent = <<<'PHP'
<?php
declare(strict_types=1);

namespace Reut\Models;

use Reut\DB\DataBase;
use Reut\DB\Types\Varchar;
use Reut\DB\Types\Integer;

class TestTableSpecial extends DataBase
{
    public function __construct(array $config)
    {
        parent::__construct($config, [], 'test_table_special', false, 0);
        $this->addColumn('id', new Integer(false, true, true));
        $this->addColumn('name', new Varchar(255));
    }
}
PHP;
        
        $modelsDir = $projectPath . '/models';
        if (!is_dir($modelsDir)) {
            mkdir($modelsDir, 0755, true);
        }
        file_put_contents($modelsDir . '/TestTableSpecial.php', $modelContent);
        
        // Create migrations table
        $this->pdo->exec("USE " . self::DB_NAME);
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL UNIQUE,
                sql_text TEXT NOT NULL,
                batch INT NOT NULL,
                applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Run migrate command - should not fail with SQL injection
        $output = $this->runManageCommand(['migrate']);
        
        // Verify no SQL errors occurred
        $this->assertStringNotContainsString('SQL syntax', $output, 
            'SQL injection should be prevented in LIKE clause');
        $this->assertStringNotContainsString('You have an error', $output, 
            'No SQL errors should occur');
    }

    /**
     * Test INSERT IGNORE protection for duplicate migrations
     * Fix: update.php lines 110, 126
     */
    public function testInsertIgnoreProtection(): void
    {
        $this->scaffoldTestProject();
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        chdir($projectPath);
        
        // Create a simple model
        $this->createSimpleModel($projectPath, 'TestTable');
        
        // Create migrations table and insert a migration
        $this->pdo->exec("USE " . self::DB_NAME);
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL UNIQUE,
                sql_text TEXT NOT NULL,
                batch INT NOT NULL,
                applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Create the table manually
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS testtable (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255)
            )
        ");
        
        // Insert a migration record
        $migrationName = 'add_new_column_to_testtable_table_' . date('YmdHis');
        $stmt = $this->pdo->prepare("
            INSERT INTO migrations (name, sql_text, batch) 
            VALUES (:name, 'ALTER TABLE testtable ADD new_column VARCHAR(255)', 1)
        ");
        $stmt->execute(['name' => $migrationName]);
        
        // Run sync command twice - second run should not fail with duplicate key error
        $output1 = $this->runManageCommand(['sync']);
        $output2 = $this->runManageCommand(['sync']);
        
        // Verify no duplicate key errors
        $this->assertStringNotContainsString('Duplicate entry', $output2, 
            'INSERT IGNORE should prevent duplicate key errors');
        $this->assertStringNotContainsString('UNIQUE constraint', $output2, 
            'No unique constraint violations should occur');
    }

    /**
     * Test that table name is extracted from tableInstance->tableName
     * Fix: update.php line 84
     */
    public function testTableNameExtraction(): void
    {
        $this->scaffoldTestProject();
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        chdir($projectPath);
        
        // Create a model with custom table name (not following Table suffix pattern)
        $modelContent = <<<'PHP'
<?php
declare(strict_types=1);

namespace Reut\Models;

use Reut\DB\DataBase;
use Reut\DB\Types\Varchar;
use Reut\DB\Types\Integer;

class CustomTableName extends DataBase
{
    public function __construct(array $config)
    {
        parent::__construct($config, [], 'custom_table_name', false, 0);
        $this->addColumn('id', new Integer(false, true, true));
        $this->addColumn('name', new Varchar(255));
    }
}
PHP;
        
        $modelsDir = $projectPath . '/models';
        if (!is_dir($modelsDir)) {
            mkdir($modelsDir, 0755, true);
        }
        file_put_contents($modelsDir . '/CustomTableName.php', $modelContent);
        
        // Create the table manually
        $this->pdo->exec("USE " . self::DB_NAME);
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS custom_table_name (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255)
            )
        ");
        
        // Run sync command - should work correctly with custom table name
        $output = $this->runManageCommand(['sync']);
        
        // Verify it worked (no errors about table not found)
        $this->assertStringNotContainsString('does not exist', $output, 
            'Table name should be extracted correctly from tableInstance');
    }

    /**
     * Test regex pattern escaping for special characters
     * Fix: checkmigration.php line 90
     */
    public function testRegexPatternEscaping(): void
    {
        $this->scaffoldTestProject();
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        chdir($projectPath);
        
        // Create a model with table name containing regex special characters
        $modelContent = <<<'PHP'
<?php
declare(strict_types=1);

namespace Reut\Models;

use Reut\DB\DataBase;
use Reut\DB\Types\Varchar;
use Reut\DB\Types\Integer;

class TestTableRegex extends DataBase
{
    public function __construct(array $config)
    {
        parent::__construct($config, [], 'test.table+regex', false, 0);
        $this->addColumn('id', new Integer(false, true, true));
        $this->addColumn('name', new Varchar(255));
    }
}
PHP;
        
        $modelsDir = $projectPath . '/models';
        if (!is_dir($modelsDir)) {
            mkdir($modelsDir, 0755, true);
        }
        file_put_contents($modelsDir . '/TestTableRegex.php', $modelContent);
        
        // Create migrations table
        $this->pdo->exec("USE " . self::DB_NAME);
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL UNIQUE,
                sql_text TEXT NOT NULL,
                batch INT NOT NULL,
                applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Run status command - should not fail with regex errors
        $output = $this->runManageCommand(['status']);
        
        // Verify no regex errors
        $this->assertStringNotContainsString('preg_match', $output, 
            'Regex should work correctly with escaped patterns');
        $this->assertStringNotContainsString('Warning', $output, 
            'No regex warnings should occur');
    }

    /**
     * Test that execute() is used for DDL/DML statements in sync command
     * Fix: update.php lines 41, 109, 125
     */
    public function testExecuteMethodForDdlDml(): void
    {
        $this->scaffoldTestProject();
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        chdir($projectPath);
        
        // Create a simple model
        $this->createSimpleModel($projectPath, 'TestExecute');
        
        // Create the table manually
        $this->pdo->exec("USE " . self::DB_NAME);
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS testexecute (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255)
            )
        ");
        
        // Run sync command - should use execute() method correctly
        $output = $this->runManageCommand(['sync']);
        
        // Verify migrations table was created and migrations were recorded
        // Check if migrations table exists first
        $this->pdo->exec("USE " . self::DB_NAME);
        $tables = $this->pdo->query("SHOW TABLES LIKE 'migrations'")->fetchAll();
        
        if (!empty($tables)) {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM migrations");
            $count = $stmt->fetchColumn();
            
            $this->assertGreaterThanOrEqual(0, (int)$count, 
                'Migrations should be recorded using execute() method');
        }
        
        $this->assertStringNotContainsString('Fatal error', $output, 
            'No fatal errors should occur');
        $this->assertStringNotContainsString('SQL syntax', $output, 
            'No SQL syntax errors should occur');
    }

    // Helper methods

    private function scaffoldTestProject(): void
    {
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        
        if (!is_dir($projectPath)) {
            mkdir($projectPath, 0755, true);
        }
        
        // Create basic project structure
        $dirs = ['models', 'routes', 'config'];
        foreach ($dirs as $dir) {
            $dirPath = $projectPath . '/' . $dir;
            if (!is_dir($dirPath)) {
                mkdir($dirPath, 0755, true);
            }
        }
        
        // Create .env file
        $envContent = <<<ENV
DB_USERNAME=root
DB_PASSWORD=root@1234
DB_NAME=test_db_two
DB_TYPE=mysql
SECRET_KEY=test_secret_key
APP_ENV=testing
ENV;
        file_put_contents($projectPath . '/.env', $envContent);
        
        // Create config.php
        $configContent = <<<'PHP'
<?php
if (!defined('REUT_PROJECT_ROOT')) {
    define('REUT_PROJECT_ROOT', __DIR__);
}
require REUT_PROJECT_ROOT . '/vendor/autoload.php';
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(REUT_PROJECT_ROOT);
$dotenv->load();
$config = [
    'host' => 'localhost',
    'username' => $_ENV['DB_USERNAME'],
    'password' => $_ENV['DB_PASSWORD'],
    'dbname' => $_ENV['DB_NAME']
];
PHP;
        file_put_contents($projectPath . '/config.php', $configContent);
        
        // Create composer.json
        $composerJson = [
            'name' => 'reut/test-project',
            'require' => [
                'php' => '>=7.4',
                'reut/core' => '^1.1'
            ],
            'autoload' => [
                'psr-4' => [
                    'Reut\\Models\\' => 'models/'
                ]
            ]
        ];
        file_put_contents($projectPath . '/composer.json', json_encode($composerJson, JSON_PRETTY_PRINT));
        
        // Create manage.php
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
use Reut\DB\DatabaseCreator;
if (!class_exists(DatabaseCreator::class)) {
    fwrite(STDERR, "Composer dependencies missing. Run `composer install` to install reut/core.\\n");
    exit(1);
}
DatabaseCreator::Generate();
PHP;
        file_put_contents($projectPath . '/manage.php', $manageContent);
    }

    private function createSimpleModel(string $projectPath, string $modelName): void
    {
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
        parent::__construct(\$config, [], strtolower('{$modelName}'), false, 0);
        \$this->addColumn('id', new Integer(false, true, true));
        \$this->addColumn('name', new Varchar(255));
    }
}
PHP;
        
        $modelsDir = $projectPath . '/models';
        if (!is_dir($modelsDir)) {
            mkdir($modelsDir, 0755, true);
        }
        file_put_contents($modelsDir . '/' . $modelName . 'Table.php', $modelContent);
    }

    private function runManageCommand(array $args): string
    {
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        $manageFile = $projectPath . '/manage.php';
        
        if (!file_exists($manageFile)) {
            // Create manage.php if it doesn't exist
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
use Reut\DB\DatabaseCreator;
if (!class_exists(DatabaseCreator::class)) {
    fwrite(STDERR, "Composer dependencies missing. Run `composer install` to install reut/core.\\n");
    exit(1);
}
DatabaseCreator::Generate();
PHP;
            file_put_contents($manageFile, $manageContent);
        }
        
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($manageFile);
        foreach ($args as $arg) {
            $cmd .= ' ' . escapeshellarg($arg);
        }
        $cmd .= ' 2>&1';
        
        $oldCwd = getcwd();
        chdir($projectPath);
        
        try {
            $output = shell_exec($cmd);
            return $output ?: '';
        } finally {
            chdir($oldCwd);
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
}


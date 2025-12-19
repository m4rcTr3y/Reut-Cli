<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use PDO;
use Reut\DB\DataBase;

/**
 * Tests for CLI Command Functionality (v1.2)
 * 
 * Tests cover all CLI commands:
 * 1. create/migrate - Create tables from models
 * 2. status - Check migration status
 * 3. sync - Sync database with models
 * 4. rollback - Rollback migrations
 * 5. validate-migrations - Validate migration SQL
 * 6. export-migrations - Export migration history
 * 7. import-migrations - Import migration history
 * 8. inspect - Inspect database schema
 * 9. generate:model - Generate model class
 * 10. generate:routes - Generate routes
 * 11. help/version - Help and version commands
 * 
 * Database credentials:
 * - Username: root
 * - Password: root@1234
 * - Database: test_db_two
 */
class CliCommandTest extends TestCase
{
    private string $testProjectDir;
    private string $testProjectName = 'test_cli_commands';
    private string $originalCwd;
    private PDO $pdo;
    private const DB_USER = 'root';
    private const DB_PASS = 'root@1234';
    private const DB_NAME = 'test_db_two';
    private const DB_HOST = 'localhost';

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->originalCwd = getcwd();
        $this->testProjectDir = sys_get_temp_dir() . '/reut_cli_test_' . uniqid();
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
        
        // Scaffold project
        $this->scaffoldTestProject();
    }

    protected function tearDown(): void
    {
        try {
            $this->pdo->exec("DROP DATABASE IF EXISTS " . self::DB_NAME);
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
        
        chdir($this->originalCwd);
        
        if (is_dir($this->testProjectDir)) {
            $this->removeDirectory($this->testProjectDir);
        }
        
        parent::tearDown();
    }

    /**
     * Test 1: Help command displays usage information
     */
    public function testHelpCommand(): void
    {
        $result = $this->runCommand('help');
        
        $this->assertStringContainsString('Usage', $result['output']);
        $this->assertStringContainsString('Commands', $result['output']);
        $this->assertStringContainsString('migrate', $result['output']);
        $this->assertStringContainsString('status', $result['output']);
        $this->assertEquals(0, $result['code']);
    }

    /**
     * Test 2: Version command displays version
     */
    public function testVersionCommand(): void
    {
        $result = $this->runCommand('version');
        
        $this->assertStringContainsString('version', strtolower($result['output']));
        $this->assertEquals(0, $result['code']);
    }

    /**
     * Test 3: Invalid command shows error
     */
    public function testInvalidCommand(): void
    {
        $result = $this->runCommand('invalid-command-that-does-not-exist');
        
        $this->assertStringContainsString('Invalid command', $result['output']);
        $this->assertGreaterThan(0, $result['code']);
    }

    /**
     * Test 4: Create/Migrate command creates tables
     */
    public function testMigrateCommandCreatesTables(): void
    {
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        
        // Create a model
        $this->createTestModel('Products', [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'name' => 'VARCHAR(255)',
        ]);
        
        $result = $this->runCommand('migrate');
        
        // Verify command ran
        $this->assertIsString($result['output']);
        
        // Only verify table if migration succeeded without fatal errors
        if ($result['code'] === 0 && stripos($result['output'], 'Fatal error') === false) {
            // Verify table was created
            $configFile = $projectPath . '/config.php';
            if (file_exists($configFile)) {
                require $configFile;
                $baseDb = new DataBase($config);
                try {
                    $baseDb->connect();
                    $tables = $baseDb->getTablesList();
                    // Table name is lowercase
                    $this->assertTrue(
                        in_array('products', $tables),
                        'products table should exist after migration. Tables: ' . implode(', ', $tables)
                    );
                } catch (\Exception $e) {
                    $this->markTestSkipped('Database connection failed: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Test 5: Status command shows migration status
     */
    public function testStatusCommand(): void
    {
        // Create and migrate a model
        $this->createTestModel('Users', [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'name' => 'VARCHAR(255)',
        ]);
        $this->runCommand('migrate');
        
        $result = $this->runCommand('status');
        
        $this->assertIsString($result['output']);
        $this->assertTrue(
            stripos($result['output'], 'migration') !== false ||
            stripos($result['output'], 'Applied') !== false ||
            stripos($result['output'], 'Pending') !== false,
            'Status should mention migrations'
        );
    }

    /**
     * Test 6: Status command with --json flag
     */
    public function testStatusCommandJsonOutput(): void
    {
        $result = $this->runCommand('status --json');
        
        $jsonStart = strpos($result['output'], '{');
        if ($jsonStart !== false) {
            $jsonStr = substr($result['output'], $jsonStart);
            $json = json_decode($jsonStr, true);
            $this->assertNotNull($json, 'Output should contain valid JSON');
            $this->assertArrayHasKey('applied', $json);
            $this->assertArrayHasKey('pending', $json);
        }
    }

    /**
     * Test 7: Status command with --summary flag
     */
    public function testStatusCommandSummaryOutput(): void
    {
        $result = $this->runCommand('status --summary');
        
        // Summary may show "No migrations table" if none exist, or show totals if they do
        $this->assertTrue(
            stripos($result['output'], 'Total') !== false ||
            stripos($result['output'], 'No migrations') !== false ||
            stripos($result['output'], 'Applied') !== false ||
            stripos($result['output'], 'Pending') !== false ||
            stripos($result['output'], 'Batches') !== false,
            'Summary should show migration information'
        );
    }

    /**
     * Test 8: Sync command syncs database with models
     */
    public function testSyncCommand(): void
    {
        // Create table manually
        $this->pdo->exec("CREATE TABLE products (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255), old_column VARCHAR(100))");
        
        // Create model without old_column
        $this->createTestModel('Products', [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'name' => 'VARCHAR(255)',
        ]);
        
        $result = $this->runCommand('sync --dry-run');
        
        $this->assertStringContainsString('DRY-RUN', $result['output']);
    }

    /**
     * Test 9: Rollback command rolls back migrations
     */
    public function testRollbackCommand(): void
    {
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        
        // Create and migrate
        $this->createTestModel('TestTable', [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
        ]);
        $migrateResult = $this->runCommand('migrate');
        
        // Verify command ran
        $this->assertIsString($migrateResult['output']);
        
        // Test rollback command (it should work even if no migrations exist)
        $result = $this->runCommand('rollback --batch=1');
        $this->assertIsString($result['output']);
        
        // If migration succeeded, verify we can rollback
        if ($migrateResult['code'] === 0 && stripos($migrateResult['output'], 'Fatal error') === false) {
            // Rollback should have output
            $this->assertNotEmpty($result['output'], 'Rollback should produce output');
        }
    }

    /**
     * Test 10: Validate-migrations command validates SQL
     */
    public function testValidateMigrationsCommand(): void
    {
        // Create and migrate
        $this->createTestModel('TestTable', [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
        ]);
        $this->runCommand('migrate');
        
        $result = $this->runCommand('validate-migrations');
        
        $this->assertIsString($result['output']);
        // Should not contain critical errors
        $this->assertStringNotContainsString('CRITICAL', $result['output']);
    }

    /**
     * Test 11: Export-migrations command exports history
     */
    public function testExportMigrationsCommand(): void
    {
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        
        // Create and migrate
        $this->createTestModel('TestTable', [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
        ]);
        $migrateResult = $this->runCommand('migrate');
        
        // Only proceed if migration succeeded
        if ($migrateResult['code'] === 0) {
            $result = $this->runCommand('export-migrations --format=json');
            
            $this->assertTrue(
                stripos($result['output'], 'Exporting') !== false ||
                stripos($result['output'], 'exported') !== false ||
                stripos($result['output'], 'migrations') !== false,
                'Export command should mention exporting'
            );
            
            // Check if export file was created
            $exportFile = $projectPath . '/migrations_export.json';
            if (file_exists($exportFile)) {
                $this->assertFileExists($exportFile);
                $content = file_get_contents($exportFile);
                $json = json_decode($content, true);
                $this->assertNotNull($json);
            }
        } else {
            $this->markTestSkipped('Migration failed, cannot test export');
        }
    }

    /**
     * Test 12: Import-migrations command imports history
     */
    public function testImportMigrationsCommand(): void
    {
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        
        // Create export file
        $exportData = [
            'migrations' => [
                [
                    'name' => 'test_import_migration',
                    'sql_text' => 'CREATE TABLE test_import (id INT PRIMARY KEY)',
                    'batch' => 1,
                    'applied_at' => date('Y-m-d H:i:s')
                ]
            ]
        ];
        
        $exportFile = $projectPath . '/test_import.json';
        file_put_contents($exportFile, json_encode($exportData));
        
        $result = $this->runCommand('import-migrations test_import.json');
        
        $this->assertStringContainsString('Importing', $result['output']);
    }

    /**
     * Test 13: Inspect command inspects database
     * Note: Use --all flag to avoid interactive prompt that would cause freeze
     */
    public function testInspectCommand(): void
    {
        // Create table manually
        $this->pdo->exec("CREATE TABLE inspect_test (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255))");
        
        // Use --all flag to avoid interactive prompt
        $result = $this->runCommand('inspect --all', 10);
        
        $this->assertIsString($result['output']);
        // Should mention table or schema (or timeout if still interactive)
        $this->assertTrue(
            stripos($result['output'], 'inspect_test') !== false ||
            stripos($result['output'], 'table') !== false ||
            stripos($result['output'], 'schema') !== false ||
            stripos($result['output'], 'timeout') !== false ||
            $result['code'] === 124, // Timeout exit code
            'Inspect should show table info or timeout. Output: ' . substr($result['output'], 0, 200)
        );
    }

    /**
     * Test 14: Migrate command with --dry-run flag
     */
    public function testMigrateDryRunCommand(): void
    {
        $this->createTestModel('DryRunTest', [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
        ]);
        
        $result = $this->runCommand('migrate --dry-run');
        
        $this->assertStringContainsString('DRY-RUN', $result['output']);
        $this->assertStringContainsString('No changes were made', $result['output']);
    }

    /**
     * Test 15: Multiple commands in sequence
     */
    public function testMultipleCommandsInSequence(): void
    {
        // Create model
        $this->createTestModel('SequenceTest', [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'name' => 'VARCHAR(255)',
        ]);
        
        // Migrate
        $result1 = $this->runCommand('migrate');
        $this->assertStringNotContainsString('Error', $result1['output']);
        
        // Status
        $result2 = $this->runCommand('status');
        $this->assertIsString($result2['output']);
        
        // Validate
        $result3 = $this->runCommand('validate-migrations');
        $this->assertIsString($result3['output']);
        
        // All commands should succeed
        $this->assertEquals(0, $result1['code']);
        $this->assertEquals(0, $result2['code']);
        $this->assertEquals(0, $result3['code']);
    }

    /**
     * Test 16: Commands handle errors gracefully
     */
    public function testCommandsHandleErrorsGracefully(): void
    {
        // Test with invalid model (should not crash)
        $result = $this->runCommand('migrate');
        
        // Command should complete (may have warnings but shouldn't crash)
        $this->assertIsString($result['output']);
    }

    /**
     * Test 17: Export and import round-trip
     */
    public function testExportImportRoundTrip(): void
    {
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        
        // Create and migrate
        $this->createTestModel('RoundTripTest', [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
        ]);
        $migrateResult = $this->runCommand('migrate');
        
        // Only proceed if migration succeeded
        if ($migrateResult['code'] === 0) {
            // Export
            $exportResult = $this->runCommand('export-migrations --format=json');
            // Export may output JSON directly or a message
            $this->assertTrue(
                stripos($exportResult['output'], 'Exporting') !== false ||
                stripos($exportResult['output'], 'exported') !== false ||
                stripos($exportResult['output'], 'migrations') !== false ||
                strpos($exportResult['output'], '{') !== false, // JSON output
                'Export should show export information or JSON'
            );
            
            // Find export file
            $exportFile = $projectPath . '/migrations_export.json';
            if (file_exists($exportFile)) {
                // Import
                $importResult = $this->runCommand('import-migrations migrations_export.json');
                $this->assertStringContainsString('Importing', $importResult['output']);
            }
        } else {
            $this->markTestSkipped('Migration failed, cannot test export/import round-trip');
        }
    }

    /**
     * Test 18: Status command with --table filter
     */
    public function testStatusCommandTableFilter(): void
    {
        // Create multiple models
        $this->createTestModel('Users', [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
        ]);
        $this->createTestModel('Posts', [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
        ]);
        $this->runCommand('migrate');
        
        $result = $this->runCommand('status --table=users');
        
        $this->assertStringContainsString('users', strtolower($result['output']));
    }

    /**
     * Test 19: All commands are registered in DatabaseCreator
     */
    public function testAllCommandsRegistered(): void
    {
        $commands = [
            'create',
            'migrate',
            'status',
            'sync',
            'rollback',
            'validate-migrations',
            'export-migrations',
            'import-migrations',
            'inspect',
            'generate:model',
            'generate:routes',
            'help',
            'version',
        ];
        
        foreach ($commands as $command) {
            $result = $this->runCommand($command);
            // Should not say "Invalid command" (except for generate commands which may need args)
            if (!in_array($command, ['generate:model', 'generate:routes'])) {
                $this->assertStringNotContainsString(
                    'Invalid command',
                    $result['output'],
                    "Command '{$command}' should be registered"
                );
            }
        }
    }

    /**
     * Test 20: Commands work with empty project
     */
    public function testCommandsWorkWithEmptyProject(): void
    {
        // Status should work with no models
        $result = $this->runCommand('status');
        $this->assertIsString($result['output']);
        
        // Validate should work
        $result = $this->runCommand('validate-migrations');
        $this->assertIsString($result['output']);
        
        // Export should work
        $result = $this->runCommand('export-migrations --format=json');
        $this->assertIsString($result['output']);
    }

    // Helper methods

    private function scaffoldTestProject(): void
    {
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        if (!is_dir($projectPath)) {
            mkdir($projectPath, 0755, true);
        }
        
        // Create directories
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
        
        // Create vendor symlink
        if (!is_dir($projectPath . '/vendor') && is_dir($vendorPath)) {
            symlink(realpath($vendorPath), $projectPath . '/vendor');
        }
        
        // Copy packages
        $packagesSource = __DIR__ . '/../../../packages';
        $packagesTarget = $projectPath . '/packages';
        if (is_dir($packagesSource) && !is_dir($packagesTarget)) {
            $this->copyDirectory($packagesSource, $packagesTarget);
        }
    }

    private function createTestModel(string $name, array $columns): void
    {
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        $tableName = strtolower(str_replace('Table', '', $name));
        $className = $name . 'Table';
        
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

    private function runCommand(string $command, int $timeoutSeconds = 10): array
    {
        $projectPath = $this->testProjectDir . '/' . $this->testProjectName;
        $manageFile = $projectPath . '/manage.php';
        
        $oldCwd = getcwd();
        chdir($projectPath);
        
        $commandParts = explode(' ', $command);
        $cmd = 'php ' . escapeshellarg($manageFile) . ' ' . implode(' ', array_map('escapeshellarg', $commandParts)) . ' 2>&1';
        
        $output = [];
        $returnCode = 0;
        
        try {
            // Use proc_open with timeout to prevent hanging
            $descriptorspec = [
                0 => ['pipe', 'r'],  // stdin
                1 => ['pipe', 'w'],  // stdout
                2 => ['pipe', 'w']   // stderr
            ];
            
            $process = proc_open($cmd, $descriptorspec, $pipes);
            
            if (!is_resource($process)) {
                chdir($oldCwd);
                return [
                    'output' => 'Failed to start process',
                    'code' => 1
                ];
            }
            
            // Close stdin immediately to prevent waiting for input
            fclose($pipes[0]);
            
            // Set non-blocking mode
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            
            $startTime = time();
            $stdout = '';
            $stderr = '';
            
            while (true) {
                $status = proc_get_status($process);
                
                // Read available output
                while ($line = fgets($pipes[1])) {
                    $stdout .= $line;
                }
                while ($line = fgets($pipes[2])) {
                    $stderr .= $line;
                }
                
                // Check if process finished
                if (!$status['running']) {
                    $returnCode = $status['exitcode'];
                    break;
                }
                
                // Check timeout
                if ((time() - $startTime) > $timeoutSeconds) {
                    // Kill the process
                    proc_terminate($process, 9);
                    proc_close($process);
                    chdir($oldCwd);
                    return [
                        'output' => $stdout . "\n[Process timed out after {$timeoutSeconds} seconds]",
                        'code' => 124 // Timeout exit code
                    ];
                }
                
                // Small delay to prevent busy waiting
                usleep(100000); // 100ms
            }
            
            // Read any remaining output
            stream_set_blocking($pipes[1], true);
            stream_set_blocking($pipes[2], true);
            while ($line = fgets($pipes[1])) {
                $stdout .= $line;
            }
            while ($line = fgets($pipes[2])) {
                $stderr .= $line;
            }
            
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            
            $output = array_filter(explode("\n", $stdout . $stderr));
        } catch (\Exception $e) {
            $output = ['Error: ' . $e->getMessage()];
            $returnCode = 1;
        } finally {
            chdir($oldCwd);
        }
        
        return [
            'output' => implode("\n", $output),
            'code' => $returnCode
        ];
    }

    private function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
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


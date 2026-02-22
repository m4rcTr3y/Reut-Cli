<?php
declare(strict_types=1);

namespace Reut\CLI\Commands;

/**
 * Admin Setup Command
 * Sets up the admin dashboard (only available when reut-admin is installed)
 */
class AdminSetupCommand extends Command
{
    public function getName(): string
    {
        return 'admin:setup';
    }

    public function getDescription(): string
    {
        return 'Set up the admin dashboard';
    }

    public function getUsage(): string
    {
        return 'admin:setup';
    }

    public function getExamples(): array
    {
        return [
            'Reut admin:setup',
        ];
    }

    public function execute(array $args = []): int
    {
        // Find project root first
        $projectRoot = $this->findProjectRoot();
        if (!$projectRoot) {
            $this->error('Could not find project root. Please run this command from your project directory.');
            return 1;
        }

        // Set project root constant if not already set
        if (!defined('REUT_PROJECT_ROOT')) {
            define('REUT_PROJECT_ROOT', $projectRoot);
        }

        // Change to project root
        $oldCwd = getcwd();
        chdir($projectRoot);

        // Load project's autoloader (important for finding project-specific classes)
        $projectAutoloader = $projectRoot . '/vendor/autoload.php';
        if (file_exists($projectAutoloader)) {
            require_once $projectAutoloader;
        }

        // Check if reut-admin package is installed (after loading project autoloader)
        if (!class_exists(\Reut\Admin\Commands\AdminSetupCommand::class)) {
            chdir($oldCwd); // Restore original directory
            $this->error('Admin package (reut-admin) is not installed.');
            $this->writeln();
            $this->writeln('To install the admin package, run:');
            $this->writeln($this->formatter->info('  composer require m4rc/reut-admin'));
            $this->writeln();
            $this->writeln('Then run this command again to set up the admin dashboard.');
            return 1;
        }

        // Load config (required by AdminSetupCommand constructor)
        $configPath = $projectRoot . '/config.php';
        if (!file_exists($configPath)) {
            chdir($oldCwd);
            $this->error('config.php not found in project root.');
            $this->writeln();
            $this->writeln('Please ensure you have a valid config.php file in your project root.');
            $this->writeln('You can create one by running: Reut init');
            return 1;
        }

        // Load config (this sets $config variable that AdminSetupCommand expects)
        require $configPath;
        if (!isset($config) || !is_array($config)) {
            chdir($oldCwd);
            $this->error('config.php does not define a $config array.');
            $this->writeln();
            $this->writeln('Please ensure config.php defines $config as an array.');
            return 1;
        }

        // Execute the admin setup command
        try {
            $adminSetupCommand = new \Reut\Admin\Commands\AdminSetupCommand();
            $result = $adminSetupCommand->execute();
            chdir($oldCwd); // Restore original directory
            return $result;
        } catch (\Throwable $e) {
            chdir($oldCwd); // Restore original directory on error
            $this->error('Failed to execute admin setup: ' . $e->getMessage());
            $this->writeln();
            $this->writeln('File: ' . $e->getFile() . ':' . $e->getLine());
            if ($this->hasOption('verbose') || $this->hasOption('v')) {
                $this->writeln();
                $this->writeln('Stack trace:');
                $this->writeln($e->getTraceAsString());
            } else {
                $this->writeln();
                $this->writeln('Run with --verbose flag for more details.');
            }
            return 1;
        }
    }

    /**
     * Find project root by looking for composer.json
     */
    private function findProjectRoot(): ?string
    {
        $currentDir = getcwd();
        $dir = $currentDir;

        // Go up directories until we find composer.json or hit root
        while ($dir !== dirname($dir)) {
            if (file_exists($dir . '/composer.json')) {
                return $dir;
            }
            $dir = dirname($dir);
        }

        // If not found, try current directory
        if (file_exists($currentDir . '/composer.json')) {
            return $currentDir;
        }

        return null;
    }
}

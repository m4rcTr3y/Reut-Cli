<?php

declare(strict_types=1);

namespace Reut\DB;

use PDO;
use PDOException;
use Reut\DB\Exceptions\ConnectionError;

/**
 * ConnectionPool - Singleton connection pool for database connections
 * Reuses PDO connections across requests to reduce overhead
 * 
 * Feature flag: REUT_DB_POOL_ENABLED (default: true)
 * Pool size: REUT_DB_POOL_SIZE (default: 10)
 */
class ConnectionPool
{
    private static ?self $instance = null;
    
    /**
     * Pool of available connections
     * Key: config hash (host+dbname+username)
     * Value: array of PDO connections
     */
    private array $pool = [];
    
    /**
     * Active connections in use
     * Key: config hash
     * Value: array of PDO connections
     */
    private array $active = [];
    
    /**
     * Connection timestamps for timeout handling
     * Key: connection hash
     * Value: timestamp when connection was created/used
     */
    private array $connectionTimestamps = [];
    
    /**
     * Maximum pool size per config
     */
    private int $maxPoolSize;
    
    /**
     * Connection timeout in seconds (default: 300 = 5 minutes)
     */
    private int $connectionTimeout;
    
    /**
     * Whether connection pool is enabled
     */
    private bool $enabled;
    
    private function __construct()
    {
        $this->enabled = filter_var($_ENV['REUT_DB_POOL_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
        $this->maxPoolSize = (int)($_ENV['REUT_DB_POOL_SIZE'] ?? 10);
        $this->connectionTimeout = (int)($_ENV['REUT_DB_CONNECTION_TIMEOUT'] ?? 300);
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get a connection from the pool or create a new one
     * 
     * @param array $config Database configuration
     * @return PDO
     * @throws ConnectionError
     */
    public function getConnection(array $config): PDO
    {
        // If pool disabled, create new connection directly
        if (!$this->enabled) {
            return $this->createNewConnection($config);
        }
        
        $configHash = $this->getConfigHash($config);
        
        // Check for available connection in pool
        if (!empty($this->pool[$configHash])) {
            $connection = array_pop($this->pool[$configHash]);
            
            // Check if connection is still valid
            if ($this->isConnectionValid($connection)) {
                // Move to active
                if (!isset($this->active[$configHash])) {
                    $this->active[$configHash] = [];
                }
                $this->active[$configHash][] = $connection;
                $this->connectionTimestamps[$this->getConnectionHash($connection)] = time();
                return $connection;
            }
        }
        
        // No available connection, create new one
        // Check if we've reached max pool size
        $activeCount = isset($this->active[$configHash]) ? count($this->active[$configHash]) : 0;
        if ($activeCount >= $this->maxPoolSize) {
            // Pool exhausted, create new connection anyway (graceful degradation)
            error_log("Connection pool exhausted for {$configHash}, creating new connection");
            return $this->createNewConnection($config);
        }
        
        // Create new connection
        $connection = $this->createNewConnection($config);
        
        // Add to active connections
        if (!isset($this->active[$configHash])) {
            $this->active[$configHash] = [];
        }
        $this->active[$configHash][] = $connection;
        $this->connectionTimestamps[$this->getConnectionHash($connection)] = time();
        
        return $connection;
    }
    
    /**
     * Release a connection back to the pool
     * 
     * @param PDO $connection
     * @param array $config Database configuration
     */
    public function releaseConnection(PDO $connection, array $config): void
    {
        if (!$this->enabled) {
            // If pool disabled, just close the connection
            $connection = null;
            return;
        }
        
        $configHash = $this->getConfigHash($config);
        $connectionHash = $this->getConnectionHash($connection);
        
        // Remove from active connections
        if (isset($this->active[$configHash])) {
            $this->active[$configHash] = array_filter(
                $this->active[$configHash],
                fn($conn) => $this->getConnectionHash($conn) !== $connectionHash
            );
            $this->active[$configHash] = array_values($this->active[$configHash]);
        }
        
        // Check if connection is still valid before returning to pool
        if ($this->isConnectionValid($connection)) {
            // Return to pool
            if (!isset($this->pool[$configHash])) {
                $this->pool[$configHash] = [];
            }
            $this->pool[$configHash][] = $connection;
            $this->connectionTimestamps[$connectionHash] = time();
        } else {
            // Connection invalid, discard it
            $connection = null;
        }
    }
    
    /**
     * Close all connections in the pool
     */
    public function closeAll(): void
    {
        foreach ($this->pool as $configHash => $connections) {
            foreach ($connections as $connection) {
                $connection = null;
            }
        }
        foreach ($this->active as $configHash => $connections) {
            foreach ($connections as $connection) {
                $connection = null;
            }
        }
        $this->pool = [];
        $this->active = [];
        $this->connectionTimestamps = [];
    }
    
    /**
     * Clean up stale connections (older than timeout)
     */
    public function cleanupStaleConnections(): void
    {
        $now = time();
        foreach ($this->pool as $configHash => $connections) {
            $this->pool[$configHash] = array_filter($connections, function($connection) use ($now) {
                $connectionHash = $this->getConnectionHash($connection);
                $timestamp = $this->connectionTimestamps[$connectionHash] ?? 0;
                
                if ($now - $timestamp > $this->connectionTimeout) {
                    // Connection expired, discard it
                    $connection = null;
                    unset($this->connectionTimestamps[$connectionHash]);
                    return false;
                }
                return true;
            });
            $this->pool[$configHash] = array_values($this->pool[$configHash]);
        }
    }
    
    /**
     * Create a new PDO connection
     */
    private function createNewConnection(array $config): PDO
    {
        try {
            $dsn = "mysql:host={$config['host']};dbname={$config['dbname']}";
            if (isset($config['port'])) {
                $dsn .= ";port={$config['port']}";
            }
            
            $pdo = new PDO(
                $dsn,
                $config['username'],
                $config['password']
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            
            return $pdo;
        } catch (PDOException $e) {
            throw new ConnectionError("\nFailed to create database connection: " . $e->getMessage());
        }
    }
    
    /**
     * Generate hash for config to group connections
     */
    private function getConfigHash(array $config): string
    {
        return md5($config['host'] . '|' . $config['dbname'] . '|' . $config['username']);
    }
    
    /**
     * Generate hash for a connection (for tracking)
     */
    private function getConnectionHash(PDO $connection): string
    {
        return spl_object_hash($connection);
    }
    
    /**
     * Check if a connection is still valid
     */
    private function isConnectionValid(PDO $connection): bool
    {
        try {
            // Try a simple query to check connection
            $connection->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Get pool statistics (for debugging)
     */
    public function getStats(): array
    {
        $stats = [];
        foreach ($this->pool as $configHash => $connections) {
            $stats[$configHash] = [
                'pooled' => count($connections),
                'active' => isset($this->active[$configHash]) ? count($this->active[$configHash]) : 0
            ];
        }
        return $stats;
    }
}














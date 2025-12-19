<?php
declare(strict_types=1);
namespace Reut\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class RateLimitMiddleware
{
    protected $app;
    private bool $enabled;
    private int $maxRequests;
    private int $windowSeconds;
    private string $storageDir;

    public function __construct($app)
    {
        $this->app = $app;
        $this->enabled = filter_var($_ENV['REUT_RATE_LIMIT_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
        $this->maxRequests = (int)($_ENV['REUT_RATE_LIMIT_MAX_REQUESTS'] ?? 100);
        $this->windowSeconds = (int)($_ENV['REUT_RATE_LIMIT_WINDOW_SECONDS'] ?? 60);
        $this->storageDir = sys_get_temp_dir() . '/reut_rate_limit';
        
        // Create storage directory if it doesn't exist
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0750, true);
        }
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        // Skip rate limiting if disabled
        if (!$this->enabled) {
            return $handler->handle($request);
        }

        // Get client identifier (IP address)
        $clientId = $this->getClientIdentifier($request);
        $rateLimitKey = $this->getRateLimitKey($clientId);

        // Check current rate limit
        if (!$this->checkRateLimit($rateLimitKey)) {
            return $this->rateLimitExceededResponse();
        }

        // Increment request count
        $this->incrementRequestCount($rateLimitKey);

        return $handler->handle($request);
    }

    /**
     * Get client identifier (IP address)
     */
    private function getClientIdentifier(Request $request): string
    {
        // Check for forwarded IP (for proxies/load balancers)
        $forwardedFor = $request->getHeaderLine('X-Forwarded-For');
        if (!empty($forwardedFor)) {
            // Take the first IP in the chain
            $ips = explode(',', $forwardedFor);
            return trim($ips[0]);
        }

        // Check for real IP header
        $realIp = $request->getHeaderLine('X-Real-IP');
        if (!empty($realIp)) {
            return trim($realIp);
        }

        // Fallback to server remote address
        $serverParams = $request->getServerParams();
        return $serverParams['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Get rate limit storage key
     */
    private function getRateLimitKey(string $clientId): string
    {
        $currentWindow = floor(time() / $this->windowSeconds);
        return md5($clientId . '_' . $currentWindow);
    }

    /**
     * Check if rate limit is exceeded
     */
    private function checkRateLimit(string $key): bool
    {
        $filePath = $this->storageDir . '/' . $key . '.json';
        
        if (!file_exists($filePath)) {
            return true; // No previous requests in this window
        }

        $data = json_decode(file_get_contents($filePath), true);
        if (!is_array($data)) {
            return true;
        }

        $requestCount = $data['count'] ?? 0;
        return $requestCount < $this->maxRequests;
    }

    /**
     * Increment request count for current window
     */
    private function incrementRequestCount(string $key): void
    {
        $filePath = $this->storageDir . '/' . $key . '.json';
        
        $data = [
            'count' => 1,
            'window_start' => time()
        ];

        if (file_exists($filePath)) {
            $existing = json_decode(file_get_contents($filePath), true);
            if (is_array($existing)) {
                $data['count'] = ($existing['count'] ?? 0) + 1;
            }
        }

        file_put_contents($filePath, json_encode($data), LOCK_EX);
        
        // Clean up old files (older than 2 windows)
        $this->cleanupOldFiles();
    }

    /**
     * Clean up old rate limit files
     */
    private function cleanupOldFiles(): void
    {
        // Only cleanup occasionally (1% chance) to avoid overhead
        if (rand(1, 100) !== 1) {
            return;
        }

        $files = glob($this->storageDir . '/*.json');
        $cutoffTime = time() - (2 * $this->windowSeconds);

        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                @unlink($file);
            }
        }
    }

    /**
     * Return rate limit exceeded response
     */
    private function rateLimitExceededResponse(): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode([
            'error' => 'Rate limit exceeded',
            'message' => "Maximum {$this->maxRequests} requests per {$this->windowSeconds} seconds allowed"
        ]));
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-RateLimit-Limit', (string)$this->maxRequests)
            ->withHeader('X-RateLimit-Window', (string)$this->windowSeconds)
            ->withHeader('Retry-After', (string)$this->windowSeconds)
            ->withStatus(429);
    }
}


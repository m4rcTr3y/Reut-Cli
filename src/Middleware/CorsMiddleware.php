<?php
declare(strict_types=1);

namespace Reut\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * CORS (Cross-Origin Resource Sharing) Middleware
 * 
 * Handles CORS headers and OPTIONS preflight requests
 */
class CorsMiddleware
{
    protected $app;
    private bool $enabled;
    private array $allowedOrigins;
    private array $allowedMethods;
    private array $allowedHeaders;
    private bool $allowCredentials;
    private int $maxAge;

    public function __construct($app)
    {
        $this->app = $app;
        $this->enabled = filter_var($_ENV['REUT_CORS_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
        
        // Parse allowed origins (comma-separated or * for all)
        $origins = $_ENV['REUT_CORS_ALLOWED_ORIGINS'] ?? '*';
        if ($origins === '*') {
            $this->allowedOrigins = ['*'];
        } else {
            $this->allowedOrigins = array_map('trim', explode(',', $origins));
        }
        
        // Parse allowed methods
        $methods = $_ENV['REUT_CORS_ALLOWED_METHODS'] ?? 'GET, POST, PUT, DELETE, PATCH, OPTIONS';
        $this->allowedMethods = array_map('trim', explode(',', $methods));
        
        // Parse allowed headers
        $headers = $_ENV['REUT_CORS_ALLOWED_HEADERS'] ?? 'Content-Type, Authorization, X-Requested-With, X-CSRF-Token';
        $this->allowedHeaders = array_map('trim', explode(',', $headers));
        
        // Allow credentials only if origin is not wildcard
        $allowCreds = filter_var($_ENV['REUT_CORS_ALLOW_CREDENTIALS'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
        if ($this->allowedOrigins === ['*'] && $allowCreds) {
            // Security: Can't use * with credentials, so disable credentials
            $this->allowCredentials = false;
        } else {
            $this->allowCredentials = $allowCreds;
        }
        
        // Max age for preflight cache (in seconds)
        $this->maxAge = (int)($_ENV['REUT_CORS_MAX_AGE'] ?? 86400); // 24 hours default
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        // Skip CORS if disabled
        if (!$this->enabled) {
            return $handler->handle($request);
        }

        $method = $request->getMethod();
        $origin = $request->getHeaderLine('Origin');

        // Handle OPTIONS preflight request
        if ($method === 'OPTIONS') {
            return $this->handlePreflight($request, $origin);
        }

        // Handle actual request - add CORS headers to response
        $response = $handler->handle($request);
        return $this->addCorsHeaders($response, $origin);
    }

    /**
     * Handle OPTIONS preflight request
     */
    private function handlePreflight(Request $request, string $origin): Response
    {
        $response = new SlimResponse();
        
        // Check if origin is allowed
        if (!$this->isOriginAllowed($origin)) {
            return $response->withStatus(403);
        }

        // Add CORS headers
        $response = $this->addCorsHeaders($response, $origin);
        
        // Add preflight-specific headers
        $response = $response->withHeader('Access-Control-Max-Age', (string)$this->maxAge);
        
        return $response->withStatus(204); // No Content
    }

    /**
     * Add CORS headers to response
     */
    private function addCorsHeaders(Response $response, string $origin): Response
    {
        // Determine allowed origin
        $allowedOrigin = $this->getAllowedOrigin($origin);
        if ($allowedOrigin) {
            $response = $response->withHeader('Access-Control-Allow-Origin', $allowedOrigin);
        }

        // Add allowed methods
        $response = $response->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods));

        // Add allowed headers
        $response = $response->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders));

        // Add credentials header if enabled
        if ($this->allowCredentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        // Add exposed headers if needed
        $exposedHeaders = $_ENV['REUT_CORS_EXPOSED_HEADERS'] ?? '';
        if (!empty($exposedHeaders)) {
            $response = $response->withHeader('Access-Control-Expose-Headers', $exposedHeaders);
        }

        return $response;
    }

    /**
     * Check if origin is allowed
     */
    private function isOriginAllowed(string $origin): bool
    {
        if (empty($origin)) {
            return false; // No origin header means same-origin request, CORS doesn't apply
        }

        // Wildcard allows all origins
        if ($this->allowedOrigins === ['*']) {
            return true;
        }

        // Check if origin is in allowed list
        return in_array($origin, $this->allowedOrigins, true);
    }

    /**
     * Get the allowed origin value for the response header
     */
    private function getAllowedOrigin(string $origin): ?string
    {
        if (empty($origin)) {
            // No origin header - return null (don't add header for same-origin requests)
            return null;
        }

        // Wildcard
        if ($this->allowedOrigins === ['*']) {
            return '*';
        }

        // Return the origin if it's allowed, otherwise null
        if (in_array($origin, $this->allowedOrigins, true)) {
            return $origin;
        }

        return null;
    }
}


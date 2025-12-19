<?php
declare(strict_types=1);
namespace Reut\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class CsrfMiddleware
{
    protected $app;
    private bool $enabled;
    private string $tokenName;
    private int $tokenLength;
    private int $tokenLifetime;

    public function __construct($app)
    {
        $this->app = $app;
        $this->enabled = filter_var($_ENV['REUT_CSRF_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
        $this->tokenName = $_ENV['REUT_CSRF_TOKEN_NAME'] ?? 'csrf_token';
        $this->tokenLength = (int)($_ENV['REUT_CSRF_TOKEN_LENGTH'] ?? 32);
        $this->tokenLifetime = (int)($_ENV['REUT_CSRF_TOKEN_LIFETIME'] ?? 3600); // 1 hour default
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        // Skip CSRF protection if disabled
        if (!$this->enabled) {
            return $handler->handle($request);
        }

        $method = $request->getMethod();

        // Only validate CSRF for state-changing methods
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            if (!$this->validateCsrfToken($request)) {
                return $this->csrfErrorResponse();
            }
        }

        // Generate and attach CSRF token to response
        $response = $handler->handle($request);
        return $this->attachCsrfToken($request, $response);
    }

    /**
     * Validate CSRF token from request
     */
    private function validateCsrfToken(Request $request): bool
    {
        // Get token from header or body
        $token = $this->getTokenFromRequest($request);
        
        if (empty($token)) {
            return false;
        }

        // Get session token
        $sessionToken = $this->getSessionToken();
        
        if (empty($sessionToken)) {
            return false;
        }

        // Compare tokens using timing-safe comparison
        return hash_equals($sessionToken, $token);
    }

    /**
     * Get CSRF token from request (header or body)
     */
    private function getTokenFromRequest(Request $request): ?string
    {
        // Try header first (X-CSRF-Token)
        $headerToken = $request->getHeaderLine('X-CSRF-Token');
        if (!empty($headerToken)) {
            return $headerToken;
        }

        // Try body parameter
        $body = $request->getParsedBody();
        if (is_array($body) && isset($body[$this->tokenName])) {
            return (string)$body[$this->tokenName];
        }

        // Try query parameter (less secure, but sometimes needed)
        $queryParams = $request->getQueryParams();
        if (isset($queryParams[$this->tokenName])) {
            return (string)$queryParams[$this->tokenName];
        }

        return null;
    }

    /**
     * Get CSRF token from session
     */
    private function getSessionToken(): ?string
    {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check if token exists and is not expired
        if (isset($_SESSION['csrf_token']) && isset($_SESSION['csrf_token_time'])) {
            $tokenAge = time() - $_SESSION['csrf_token_time'];
            
            // Token expired
            if ($tokenAge > $this->tokenLifetime) {
                unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
                return null;
            }

            return $_SESSION['csrf_token'];
        }

        return null;
    }

    /**
     * Generate a new CSRF token
     */
    private function generateToken(): string
    {
        return bin2hex(random_bytes($this->tokenLength));
    }

    /**
     * Store CSRF token in session
     */
    private function storeTokenInSession(string $token): void
    {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
    }

    /**
     * Attach CSRF token to response (for GET requests or when token is missing)
     */
    private function attachCsrfToken(Request $request, Response $response): Response
    {
        $method = $request->getMethod();

        // Generate token if it doesn't exist or is expired
        $currentToken = $this->getSessionToken();
        if (empty($currentToken)) {
            $newToken = $this->generateToken();
            $this->storeTokenInSession($newToken);
            $currentToken = $newToken;
        }

        // Add token to response header for easy access
        $response = $response->withHeader('X-CSRF-Token', $currentToken);
        
        // For JSON responses, include token in body if it's a GET request
        if ($method === 'GET') {
            $contentType = $response->getHeaderLine('Content-Type');
            if (str_contains($contentType, 'application/json')) {
                $body = (string)$response->getBody();
                $data = json_decode($body, true);
                
                if (is_array($data)) {
                    $data['csrf_token'] = $currentToken;
                    $response->getBody()->rewind();
                    $response->getBody()->write(json_encode($data));
                }
            }
        }

        return $response;
    }

    /**
     * Return CSRF error response
     */
    private function csrfErrorResponse(): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode([
            'error' => 'CSRF token validation failed',
            'message' => 'Invalid or missing CSRF token. Please refresh the page and try again.'
        ]));
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(403);
    }
}


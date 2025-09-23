<?php
declare(strict_types=1);
namespace Reut\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class AuthMiddleware
{
    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $authHeader = $request->getHeader('Authorization');

        if (empty($authHeader) || !isset($authHeader[0])) {
            return $this->unauthorizedResponse();
        }

        $token = str_replace('Bearer ', '', $authHeader[0]);

        try {
            $decoded = JWT::decode($token, new Key('your_secret_key', 'HS256'));
            $request = $request->withAttribute('decoded_token_data', (array) $decoded);
        } catch (\Exception $e) {
            return $this->unauthorizedResponse();
        }

        return $handler->handle($request);
    }

    private function unauthorizedResponse(): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }
}

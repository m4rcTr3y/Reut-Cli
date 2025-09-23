<?php
declare(strict_types=1);
namespace Reut\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Response as SlimResponse;

class JwtAuth
{
    private $secretKey;
    private $pdo;

    public function __construct($config){
        
        $this->secretKey = $_ENV['SECRET_KEY'];
        
        $this->pdo = new \PDO(
                "mysql:host={$config['host']}; dbname={$config['dbname']}",
                $config['username'],
                $config['password']
            );
    }

    // Generate a new JWT
    public function generateToken($userId, $expiry = 3600){
        $payload = [
            'sub' => $userId,
            'iat' => \time(),
            'exp' => \time() + $expiry
        ];

        return JWT::encode($payload, $this->secretKey, 'HS256');
    }

    // Validate the JWT and return the decoded payload
    public function validateToken($token)
    {
        try {
            return JWT::decode($token, new Key($this->secretKey, 'HS256'));
        } catch (\Exception $e) {
            return null;
        }
    }

    // Generate and store a refresh token
    

    public function generateRefreshToken($userId){
        $refreshToken = bin2hex(random_bytes(32)); // Generate a random refresh token
        $expiresAt = new \DateTime('+1 days'); // Set the refresh token expiry

        // Check if the user already has a refresh token
        $stmt = $this->pdo->prepare('SELECT id FROM sessions WHERE user_id = ?');
        $stmt->execute([$userId]);
        $existingSession = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($existingSession) {
            // Update the existing refresh token
            $stmt = $this->pdo->prepare('UPDATE sessions SET refresh_token = ?, expires_at = ? WHERE id = ?');
            $stmt->execute([$refreshToken, $expiresAt->format('Y-m-d H:i:s'), $existingSession['id']]);
        } else {
            // Insert a new refresh token if none exists
            $stmt = $this->pdo->prepare('INSERT INTO sessions (user_id, refresh_token, expires_at) VALUES (?, ?, ?)');
            $stmt->execute([$userId, $refreshToken, $expiresAt->format('Y-m-d H:i:s')]);
        }

        return $refreshToken;
}

    // Validate a refresh token from the database
    public function validateRefreshToken($userId, $refreshToken){
        $stmt = $this->pdo->prepare('SELECT expires_at FROM sessions WHERE user_id = ? AND refresh_token = ?');
        $stmt->execute([$userId, $refreshToken]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($result && new \DateTime() < new \DateTime($result['expires_at'])) {
            return true;
        }

        return false;
    }

    // Middleware function to authenticate requests
    public function __invoke(Request $request, RequestHandler $handler): Response{
        $response = new SlimResponse();
        $authHeader = $request->getHeader('Authorization');
        if (!$authHeader) {
            $response->getBody()->write(json_encode(['error' => 'Not allowed','action'=>'login']));
            return $response->withStatus(403)->withHeader('Constent-Type','application/json');
        }

        $token = str_replace('Bearer ', '', $authHeader[0]);
        $decoded = $this->validateToken($token);

        if (!$decoded) {
            $response->getBody()->write( json_encode(['error' => 'Token expired or invalid', 'action' => 'refresh_token']));
            return $response->withStatus(401)->withHeader('Constent-Type','application/json');
        }
        
        //$stmt = $this->pdo->prepare('SELECT role FROM accounts WHERE userID = ?');
        //$stmt->execute([$decoded->sub]);
       // $data = $stmt->fetchAll();
         

        // Add user data to the request for further use in your application
       // $request = $request->withAttribute('userId', $decoded->sub);

       // $final = array_map(function($item){return $item['role'];},$data);

       // $request = $request->withAttribute('userRoles', $final );

        return $handler->handle($request);
    }



    // Remove refresh tokens, for example during logout
    public function revokeRefreshToken($userId, $refreshToken = null){
        if ($refreshToken) {
            $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE user_id = ? AND refresh_token = ?');
            $stmt->execute([$userId, $refreshToken]);
        } else {
            // Revoke all tokens for the user
            $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE user_id = ?');
            $stmt->execute([$userId]);
        }
    }
}

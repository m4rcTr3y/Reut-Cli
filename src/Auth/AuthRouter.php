<?php
declare(strict_types=1);

namespace Reut\Auth;

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Reut\DB\DataBase;
use Reut\DB\Types\Integer;
use Reut\DB\Types\Varchar;
use Reut\DB\Types\Timestamp;
use Reut\Middleware\JwtAuth;
use Reut\Router\ReuteRoute;
use Reut\Support\ProjectPath;

/**
 * Built-in authentication router
 * Provides login, register, refresh, and logout endpoints
 */
class AuthRouter extends NoAuth
{
    protected array $config;
    protected array $authConfig;
    protected AuthController $authController;

    public function __construct(App $app, array $config, array $authConfig)
    {
        $this->config = $config;
        $this->authConfig = $authConfig;
        parent::__construct($app);
    }

    protected function genRoutes()
    {
        $routes = ReuteRoute::use($this->app);

        // Initialize auth model and controller
        $authModel = $this->getAuthModel();
        $jwtAuth = new JwtAuth($this->config);
        $this->authController = new class($authModel, $jwtAuth, $this->authConfig) extends AuthController {};

        $authController = $this->authController;
        $authConfig = $this->authConfig;
        
        $routes->group('/auth', 'Authentication', function ($group) use ($authModel, $jwtAuth, $authController, $authConfig) {
            // Login endpoint
            $group->post('/login', function (Request $request, Response $response) use ($authController) {
                $input = $request->getParsedBody();
                $user = $this->authController->validateLogin($input ?? []);

                if (!$user) {
                    $response->getBody()->write(json_encode([
                        'error' => true,
                        'message' => 'Invalid credentials'
                    ]));
                    return $response
                        ->withHeader('Content-Type', 'application/json')
                        ->withStatus(401);
                }

                try {
                    $responseData = $this->authController->prepareLoginResponse($user);
                    $response->getBody()->write(json_encode($responseData));
                    return $response->withHeader('Content-Type', 'application/json');
                } catch (\Exception $e) {
                    $response->getBody()->write(json_encode([
                        'error' => true,
                        'message' => 'Authentication failed: ' . $e->getMessage()
                    ]));
                    return $response
                        ->withHeader('Content-Type', 'application/json')
                        ->withStatus(500);
                }
            }, 'Login and receive JWT token');

            // Register endpoint
            $group->post('/register', function (Request $request, Response $response) use ($authController, $authModel) {
                $input = $request->getParsedBody();
                $errors = $authController->validateRegister($input ?? []);

                if (!empty($errors)) {
                    $response->getBody()->write(json_encode([
                        'error' => true,
                        'message' => 'Validation failed',
                        'errors' => $errors
                    ]));
                    return $response
                        ->withHeader('Content-Type', 'application/json')
                        ->withStatus(400);
                }

                $preparedData = $authController->prepareRegisterData($input);
                $result = $authModel->addOne($preparedData);

                if ($result) {
                    $response->getBody()->write(json_encode([
                        'success' => true,
                        'message' => 'User registered successfully'
                    ]));
                    return $response->withHeader('Content-Type', 'application/json');
                } else {
                    $response->getBody()->write(json_encode([
                        'error' => true,
                        'message' => 'Registration failed'
                    ]));
                    return $response
                        ->withHeader('Content-Type', 'application/json')
                        ->withStatus(500);
                }
            }, 'Register a new user account');

            // Refresh token endpoint
            $group->post('/refresh', function (Request $request, Response $response) use ($jwtAuth, $authConfig) {
                $input = $request->getParsedBody();
                $userId = $input['user_id'] ?? null;
                $refreshToken = $input['refresh_token'] ?? null;

                if (!$userId || !$refreshToken) {
                    $response->getBody()->write(json_encode([
                        'error' => true,
                        'message' => 'user_id and refresh_token are required'
                    ]));
                    return $response
                        ->withHeader('Content-Type', 'application/json')
                        ->withStatus(400);
                }

                if (!$jwtAuth->validateRefreshToken($userId, $refreshToken)) {
                    $response->getBody()->write(json_encode([
                        'error' => true,
                        'message' => 'Invalid or expired refresh token'
                    ]));
                    return $response
                        ->withHeader('Content-Type', 'application/json')
                        ->withStatus(401);
                }

                $newToken = $jwtAuth->generateToken($userId, $authConfig['token_expiry']);
                $newRefreshToken = $jwtAuth->generateRefreshToken($userId);

                $response->getBody()->write(json_encode([
                    'token' => $newToken,
                    'refresh_token' => $newRefreshToken,
                    'expires_in' => $authConfig['token_expiry']
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }, 'Refresh JWT token using refresh token');

            // Logout endpoint
            $group->post('/logout', function (Request $request, Response $response) use ($jwtAuth) {
                $input = $request->getParsedBody();
                $userId = $input['user_id'] ?? null;
                $refreshToken = $input['refresh_token'] ?? null;

                if (!$userId) {
                    $response->getBody()->write(json_encode([
                        'error' => true,
                        'message' => 'user_id is required'
                    ]));
                    return $response
                        ->withHeader('Content-Type', 'application/json')
                        ->withStatus(400);
                }

                $jwtAuth->revokeRefreshToken($userId, $refreshToken);

                $response->getBody()->write(json_encode([
                    'success' => true,
                    'message' => 'Logged out successfully'
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }, 'Logout and revoke tokens');
        });
    }

    /**
     * Get or create the authentication model
     */
    protected function getAuthModel(): DataBase
    {
        $tableName = $this->authConfig['table'];
        $identifierField = $this->authConfig['fields']['identifier'];

        // Try to load existing model
        $modelClass = "Reut\\Models\\{$tableName}Table";
        
        // First check if class already exists
        if (class_exists($modelClass)) {
            return new $modelClass($this->config);
        }
        
        // If class doesn't exist, try to require the model file
        $modelsDir = ProjectPath::resolve('models');
        $modelFile = rtrim($modelsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $tableName . 'Table.php';
        
        if (file_exists($modelFile)) {
            require_once $modelFile;
            if (class_exists($modelClass)) {
                return new $modelClass($this->config);
            }
        }

        // Auto-create model if enabled (only if model file doesn't exist)
        if ($this->authConfig['auto_create_table']) {
            return $this->createDefaultAuthModel($tableName, $identifierField);
        }

        throw new \RuntimeException("Auth table '{$tableName}' not found. Create a model or enable auto_create_table.");
    }

    /**
     * Create default authentication model
     */
    protected function createDefaultAuthModel(string $tableName, string $identifierField): DataBase
    {
        $model = new DataBase(
            $this->config,
            [],
            $tableName,
            false,
            0,
            [],
            []
        );

        // Primary key
        $model->addColumn('id', new Integer(false, true, true, null));

        // Identifier field (email or username)
        $model->addColumn($identifierField, new Varchar(255, false));

        // Password field
        $model->addColumn('password', new Varchar(255, false));

        // Timestamps
        $model->addColumn('created_at', new Timestamp(false, true));
        $model->addColumn('updated_at', new Timestamp(false, true, true));

        // Create table if it doesn't exist
        if (!$model->tableExists($tableName)) {
            $model->createTable();
        }

        return $model;
    }
}



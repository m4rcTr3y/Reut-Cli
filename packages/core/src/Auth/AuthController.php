<?php
declare(strict_types=1);

namespace Reut\Auth;

use Reut\DB\DataBase;
use Reut\Middleware\JwtAuth;

/**
 * Base authentication controller for extensibility
 * Extend this class to customize authentication behavior
 */
abstract class AuthController
{
    protected DataBase $authModel;
    protected JwtAuth $jwtAuth;
    protected array $authConfig;

    public function __construct(DataBase $authModel, JwtAuth $jwtAuth, array $authConfig)
    {
        $this->authModel = $authModel;
        $this->jwtAuth = $jwtAuth;
        $this->authConfig = $authConfig;
    }

    /**
     * Validate login credentials
     * Override to add custom validation (e.g., check if user is active)
     */
    public function validateLogin(array $credentials): ?array
    {
        $identifierField = $this->authConfig['fields']['identifier'];
        $passwordField = $this->authConfig['fields']['password'];

        if (!isset($credentials[$identifierField]) || !isset($credentials[$passwordField])) {
            return null;
        }

        $user = $this->authModel->findOne([$identifierField => $credentials[$identifierField]]);
        
        if (!$user || !$user->results) {
            return null;
        }

        $storedPassword = $user->results[$passwordField] ?? null;
        if (!$storedPassword || !password_verify($credentials[$passwordField], $storedPassword)) {
            return null;
        }

        return $user->results;
    }

    /**
     * Validate registration data
     * Override to add custom validation rules
     */
    public function validateRegister(array $data): array
    {
        $identifierField = $this->authConfig['fields']['identifier'];
        $passwordField = $this->authConfig['fields']['password'];

        $errors = [];

        if (empty($data[$identifierField])) {
            $errors[] = "{$identifierField} is required";
        }

        if (empty($data[$passwordField])) {
            $errors[] = "{$passwordField} is required";
        } elseif (strlen($data[$passwordField]) < 6) {
            $errors[] = "{$passwordField} must be at least 6 characters";
        }

        // Check if user already exists
        $existing = $this->authModel->findOne([$identifierField => $data[$identifierField]]);
        if ($existing && $existing->results) {
            $errors[] = "User with this {$identifierField} already exists";
        }

        return $errors;
    }

    /**
     * Hash password before storing
     * Override to use different hashing algorithm
     */
    protected function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Prepare user data for registration
     * Override to add custom fields or transformations
     */
    public function prepareRegisterData(array $data): array
    {
        $passwordField = $this->authConfig['fields']['password'];
        $data[$passwordField] = $this->hashPassword($data[$passwordField]);
        return $data;
    }

    /**
     * Generate response data after successful login
     * Override to add custom fields to response
     */
    public function prepareLoginResponse(array $user): array
    {
        $idField = $this->authConfig['fields']['id'];
        $userId = $user[$idField] ?? null;

        if (!$userId) {
            throw new \RuntimeException('User ID not found');
        }

        $token = $this->jwtAuth->generateToken($userId, $this->authConfig['token_expiry']);
        $refreshToken = $this->jwtAuth->generateRefreshToken($userId);

        return [
            'token' => $token,
            'refresh_token' => $refreshToken,
            'expires_in' => $this->authConfig['token_expiry'],
            'user' => $this->sanitizeUserData($user),
        ];
    }

    /**
     * Remove sensitive data from user object
     * Override to customize which fields are returned
     */
    protected function sanitizeUserData(array $user): array
    {
        $passwordField = $this->authConfig['fields']['password'];
        unset($user[$passwordField]);
        return $user;
    }
}


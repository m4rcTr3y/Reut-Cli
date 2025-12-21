<?php
declare(strict_types=1);

use Reut\DB\DataBase;
use Reut\Support\ProjectPath;

/**
 * Create a test user in the authentication table
 * 
 * @param string $identifier The user identifier (email or username)
 * @param string $password The plain text password (will be hashed)
 * @param array $config Database configuration
 * @param array $authConfig Authentication configuration from auth.php
 * @return array Result array with 'success' (bool) and 'message' (string)
 */
function createAuthUser(string $identifier, string $password, array $config, array $authConfig): array
{
    try {
        // Load the auth model
        $tableName = $authConfig['table'];
        $identifierField = $authConfig['fields']['identifier'];
        $passwordField = $authConfig['fields']['password'];
        
        // Try to load existing model
        $modelClass = "Reut\\Models\\{$tableName}Table";
        if (!class_exists($modelClass)) {
            // Try to require the model file
            $modelsDir = rtrim(ProjectPath::resolve('models'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $modelFile = $modelsDir . $tableName . 'Table.php';
            if (file_exists($modelFile)) {
                require_once $modelFile;
            }
        }
        
        if (!class_exists($modelClass)) {
            return [
                'success' => false,
                'message' => "Auth model class {$modelClass} not found. Please ensure the model file exists."
            ];
        }
        
        $authModel = new $modelClass($config);
        
        // Check if user already exists
        $existing = $authModel->findOne([$identifierField => $identifier]);
        if ($existing && $existing->results) {
            return [
                'success' => false,
                'message' => "User with {$identifierField} '{$identifier}' already exists."
            ];
        }
        
        // Validate password length
        if (strlen($password) < 6) {
            return [
                'success' => false,
                'message' => 'Password must be at least 6 characters long.'
            ];
        }
        
        // Prepare user data
        $userData = [
            $identifierField => $identifier,
            $passwordField => password_hash($password, PASSWORD_DEFAULT)
        ];
        
        // Create the user
        $result = $authModel->addOne($userData);
        
        if ($result === true) {
            return [
                'success' => true,
                'message' => "Test user '{$identifier}' created successfully."
            ];
        } else {
            // addOne returns error message string on failure
            $errorMsg = is_string($result) ? $result : 'Unknown error occurred';
            return [
                'success' => false,
                'message' => "Failed to create user: {$errorMsg}"
            ];
        }
    } catch (\Exception $e) {
        return [
            'success' => false,
            'message' => "Error creating user: " . $e->getMessage()
        ];
    }
}


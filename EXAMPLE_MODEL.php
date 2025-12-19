<?php
declare(strict_types=1);

namespace Reut\Models;

use Reut\DB\DataBase;
use Reut\DB\Types\Varchar;
use Reut\DB\Types\Integer;
use Reut\DB\Types\Text;
use Reut\DB\Types\Timestamp;

/**
 * Example model showing all new security features:
 * - File type validation per field
 * - Required field validation (configurable via REUT_STRICT_REQUIRED_VALIDATION)
 * - Protected columns
 */
class UsersTable extends DataBase
{
    public function __construct(array $config)
    {
        parent::__construct(
            $config,
            [], // Columns array (will be populated below)
            'Users', // Table name
            false, // hasRelationships (will be true if addForeignKey is called)
            [], // relationships array
            ['avatar', 'resume'], // File fields - fields that accept file uploads
            [], // Disabled routes - routes to disable for this model
                  // Options: 'all' (disables all routes), 'find', 'add', 'update', 'delete'
                  // Example: ['add', 'delete'] to disable only create and delete routes
            ['created_at', 'updated_at'], // Protected columns - cannot be updated directly
            null, // strictRequiredValidation - null = use REUT_STRICT_REQUIRED_VALIDATION from .env
                  // Set to true/false to override env setting for this model
            [
                // File field types - allowed file extensions per field
                'avatar' => ['jpg', 'jpeg', 'png', 'gif', 'webp'], // Only image files
                'resume' => ['pdf', 'docx', 'txt'] // Only document files
            ],
            true // requiresAuth - set to true to require authentication for all routes of this model
                 // When true, all CRUD endpoints will require a valid JWT token
        );

        // Define table columns with their properties
        // id: Auto-incrementing primary key (required, not nullable)
        $this->addColumn('id', new Integer(
            false, // Not nullable (required)
            true,  // Is primary key
            true,  // Auto-increment
            null   // Default value
        ));

        // email: Required field (not nullable)
        $this->addColumn('email', new Varchar(
            255,   // Length
            false, // Not nullable (required)
            null   // Default value
        ));

        // username: Required field (not nullable)
        $this->addColumn('username', new Varchar(
            100,   // Length
            false, // Not nullable (required)
            null   // Default value
        ));

        // password: Required field (not nullable)
        $this->addColumn('password', new Varchar(
            255,   // Length
            false, // Not nullable (required)
            null   // Default value
        ));

        // bio: Optional field (nullable)
        $this->addColumn('bio', new Text(
            true,  // Nullable (optional)
            null   // Default value
        ));

        // avatar: File field with type restrictions (defined in fileFieldTypes above)
        $this->addColumn('avatar', new Varchar(
            255,   // Length (stores filename)
            true,  // Nullable (optional)
            null   // Default value
        ));

        // resume: File field with type restrictions (defined in fileFieldTypes above)
        $this->addColumn('resume', new Varchar(
            255,   // Length (stores filename)
            true,  // Nullable (optional)
            null   // Default value
        ));

        // created_at: Protected column (cannot be updated directly)
        $this->addColumn('created_at', new Timestamp(
            false, // Not nullable
            false  // Not primary key
        ));

        // updated_at: Protected column (cannot be updated directly)
        $this->addColumn('updated_at', new Timestamp(
            false, // Not nullable
            false  // Not primary key
        ));

        // TODO: Define your relationships using the addForeignKey helper, for example:
        // $this->addForeignKey('profile_id', 'Profiles');
    }

    // TODO: Add your custom methods here (e.g., custom queries, business logic)
}

/**
 * USAGE EXAMPLES:
 * 
 * 1. Creating a user (with file uploads):
 *    $userModel = new UsersTable($config);
 *    $data = [
 *        'email' => 'user@example.com',
 *        'username' => 'johndoe',
 *        'password' => password_hash('secret', PASSWORD_DEFAULT),
 *        'bio' => 'Software developer'
 *    ];
 *    // Files are handled automatically via $_FILES['avatar'] and $_FILES['resume']
 *    // File types are validated: avatar must be jpg/jpeg/png/gif/webp, resume must be pdf/docx/txt
 *    $result = $userModel->addOne($data);
 * 
 * 2. Updating a user (with strictRequiredValidation):
 *    // If REUT_STRICT_REQUIRED_VALIDATION=true, all required fields must be present
 *    // If REUT_STRICT_REQUIRED_VALIDATION=false, only provided fields are updated
 *    $updateData = ['bio' => 'Updated bio'];
 *    $condition = ['id' => 1];
 *    $result = $userModel->update($updateData, $condition);
 * 
 * 3. Finding a user:
 *    // Required fields validation applies based on REUT_STRICT_REQUIRED_VALIDATION setting
 *    $user = $userModel->findOne(['email' => 'user@example.com']);
 * 
 * SECURITY FEATURES:
 * 
 * 1. File Type Validation:
 *    - Only allowed file extensions are accepted per field
 *    - Dangerous extensions (php, exe, sh, etc.) are always rejected
 *    - MIME type validation ensures file extension matches content
 *    - File size limit: 5MB default
 * 
 * 2. Required Field Validation:
 *    - Controlled by REUT_STRICT_REQUIRED_VALIDATION env var
 *    - When true: All non-nullable fields must be present in addOne/update
 *    - When false: Only provided fields are validated (partial updates allowed)
 * 
 * 3. SQL Injection Prevention:
 *    - All identifiers (table/column names) are validated
 *    - Prepared statements used for all queries
 *    - Input sanitization for file names
 * 
 * 4. Rate Limiting:
 *    - Configured via REUT_RATE_LIMIT_ENABLED, REUT_RATE_LIMIT_MAX_REQUESTS, REUT_RATE_LIMIT_WINDOW_SECONDS
 *    - Applied globally via RateLimitMiddleware
 * 
 * 5. CSRF Protection:
 *    - Configured via REUT_CSRF_ENABLED, REUT_CSRF_TOKEN_NAME, etc.
 *    - Applied globally via CsrfMiddleware for POST/PUT/PATCH/DELETE requests
 * 
 * 6. Disabled Routes:
 *    - Control which CRUD routes are generated for this model
 *    - Options: 'all' (disables all routes), 'find', 'add', 'update', 'delete'
 *    - Example: ['add', 'delete'] disables only POST /add and DELETE /delete/{id}
 *    - Example: ['all'] disables all CRUD routes (useful for read-only models)
 *    - Disabled routes are shown in the schema viewer at /schema
 * 
 * 7. Per-Model Authentication:
 *    - Set requiresAuth to true to require JWT authentication for all routes
 *    - When enabled, all CRUD endpoints require a valid JWT token in the Authorization header
 *    - Auth status is shown in the schema viewer at /schema
 *    - Example: requiresAuth = true means all routes need authentication
 *    - Example: requiresAuth = false means routes are publicly accessible (default)
 */


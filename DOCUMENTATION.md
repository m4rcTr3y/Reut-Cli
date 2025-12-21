# REUT Framework - Complete Documentation

## Table of Contents

1. [Overview](#overview)
2. [Installation](#installation)
3. [Getting Started](#getting-started)
4. [Core Features](#core-features)
5. [CLI Commands Reference](#cli-commands-reference)
6. [API Documentation](#api-documentation)
7. [Authentication](#authentication)
8. [Database & Models](#database--models)
9. [Migrations](#migrations)
10. [Routing](#routing)
11. [Middleware](#middleware)
12. [Plugins](#plugins)
13. [Configuration](#configuration)
14. [Troubleshooting](#troubleshooting)
15. [Contributing](#contributing)

---

## Overview

**REUT** is a lightweight PHP framework that streamlines web development with intuitive routing, database management, and authentication. Built on Slim PHP for routing, REUT uses JWT (JSON Web Tokens) for secure authentication and introduces a model-based approach to database interaction—define your data structure in a PHP class, and REUT automatically generates CRUD APIs and manages tables for you.

### Key Features

- **Slim PHP Routing**: Fast, flexible routing powered by Slim
- **Model-Based Database Management**: Define tables as PHP classes—no manual SQL required
- **Automatic CRUD API**: Default CRUD endpoints for each model
- **Built-in Authentication**: Ready-to-use login, register, refresh, and logout endpoints with JWT tokens
- **File Upload Handling**: Manages file uploads defined in model fields
- **Customizable Routes**: Add custom routes in the `routers` directory, with optional authentication middleware
- **Runtime API Docs**: Built-in `/docs` endpoint (HTML or JSON) lists every registered route
- **Advanced Migration System**: Comprehensive migration management with rollback, validation, and export/import
- **Plugin System**: Extensible architecture with official plugins

### Requirements

- **PHP**: 7.4 or higher
- **Composer**: [getcomposer.org](https://getcomposer.org)
- **Database**: MySQL 5.7+ or PostgreSQL 9.6+

---

## Installation

### 1. Install the REUT CLI Tool

Install globally via Composer:

```bash
composer global require m4rc/reut_cli
```

### 2. Add Composer's `vendor/bin` to Your PATH

#### Linux/macOS

Composer may use either the traditional location (`~/.composer`) or the XDG location (`~/.config/composer`). Check which one you're using:

```bash
composer global config home
```

**For traditional location (`~/.composer`):**

Edit your shell config:
- **Bash/Zsh**: `~/.bashrc` or `~/.zshrc`
- **Fish**: `~/.config/fish/config.fish`

```bash
export PATH="$HOME/.composer/vendor/bin:$PATH"
```

**For XDG location (`~/.config/composer`):**

```bash
export PATH="$HOME/.config/composer/vendor/bin:$PATH"
```

**Fish shell specific:**

Add to `~/.config/fish/config.fish`:

```fish
# Check which Composer location exists
if test -d "$HOME/.config/composer/vendor/bin"
    set -gx PATH "$HOME/.config/composer/vendor/bin" $PATH
else if test -d "$HOME/.composer/vendor/bin"
    set -gx PATH "$HOME/.composer/vendor/bin" $PATH
end
```

Reload your shell:

```bash
# Bash/Zsh
source ~/.bashrc  # or source ~/.zshrc

# Fish
source ~/.config/fish/config.fish
```

Verify installation:

```bash
Reut -v
```

#### Windows

- Edit your user `Path` variable in Environment Variables
- Add: `%APPDATA%\Composer\vendor\bin`
- Open a new terminal and run:

```cmd
Reut -v
```

#### Troubleshooting Stability Errors

If you see a stability error, install the development version:

```bash
composer global require m4rc/reut_cli:dev-main
```

> **Note:** Current version (`v1.3.7`). See [Packagist](https://packagist.org/packages/m4rc/reut_cli).

### 3. Updating REUT CLI

Update to the latest version with a single command:

```bash
Reut update
```

This automatically clears the composer cache and updates to the latest version.

---

## Getting Started

### Initialize a New REUT Project

Create a new project:

```bash
Reut init
```

You'll be prompted for:

- **Project name** (default: `myproject`)
- **Database type** (`mysql` or `postgresql`)
- **Database name** (default: `test_db`)
- **Database username** (default: `root`)
- **Database password** (optional)
- **Secret key** (default: `12345678`)
- **Enable built-in authentication** (y/n, default: y)

This sets up your project directory with all necessary files.

### Set Up Your Project

Navigate to your project:

```bash
cd myproject
```

Install dependencies:

```bash
composer install
```

### Generate Your First Model

Generate a model class:

```bash
Reut generate:model Users
```

This creates a model file in the `models/` directory that you can customize.

### Run Migrations

Apply migrations to create database tables:

```bash
Reut migrate
```

### Start Development Server

Start the built-in PHP development server:

```bash
Reut dev --port=9000
```

Your API will be available at `http://localhost:9000`.

---

## Core Features

### Model-Based Database Management

REUT uses a model-based approach where you define your database structure as PHP classes. The framework automatically:

- Creates database tables from model definitions
- Generates CRUD API endpoints
- Handles relationships between models
- Manages migrations

### Automatic CRUD API

Each model automatically gets the following endpoints:

- `GET /{model}/all` - List all records
- `GET /{model}/find/{id}` - Get a specific record
- `POST /{model}/add` - Create a new record
- `PUT /{model}/update/{id}` - Update a record
- `DELETE /{model}/delete/{id}` - Delete a record

### Built-in Authentication

REUT provides ready-to-use authentication endpoints:

- `POST /auth/login` - Login with email/username and password
- `POST /auth/register` - Register new user account
- `POST /auth/refresh` - Refresh JWT token
- `POST /auth/logout` - Revoke tokens

### Runtime API Documentation

Visit `/docs` in your running project to see a generated list of all registered routes. Append `?format=json` for JSON output.

---

## CLI Commands Reference

### Global Commands

These commands can be run from anywhere:

```bash
Reut init              # Initialize a new project
Reut update            # Update CLI to latest version
Reut -v                # Show version
Reut -h                # Show help
```

### Project Commands

These commands must be run inside your project directory:

#### Migration Commands

```bash
# Basic migration commands
Reut create                # Alias of migrate; ensures tables exist from models
Reut migrate               # Apply migrations from model definitions to the database
Reut migrate --dry-run     # Preview migrations without executing (v1.3.0+)
Reut sync                  # Reconcile existing tables with models (may drop extra columns)
Reut sync --dry-run        # Preview sync changes without executing (v1.3.0+)
Reut status                # Check for pending migrations in models
Reut status --json         # Output migration status as JSON (v1.3.0+)
Reut status --summary      # Show summary of migration status (v1.3.0+)
Reut status --table=users  # Check status for specific table (v1.3.0+)

# Rollback commands
Reut rollback              # Rollback last batch of migrations (v1.3.0+)
Reut rollback --batch=2    # Rollback specific batch number (v1.3.0+)
Reut rollback --migration=name # Rollback specific migration (v1.3.0+)
Reut rollback --dry-run    # Preview rollback without executing (v1.3.0+)

# Migration validation and management
Reut validate-migrations   # Validate migration SQL syntax and check conflicts (v1.3.0+)
Reut export-migrations     # Export migration history to JSON/SQL file (v1.3.0+)
Reut import-migrations file.json # Import migration history from JSON/SQL file (v1.3.0+)
```

#### Model & Route Generation

```bash
Reut generate:routes       # Generate routes for each model into the route/ folder
Reut generate:model Users  # Generate a model class (replace 'Users' with your model name)
```

#### Development & Inspection

```bash
Reut dev --port=9000       # Start the built-in PHP dev server (host defaults to 0.0.0.0)
Reut view --port=8088      # Start the HTML schema viewer (optional host/port flags)
Reut inspect --table=users # Inspect DB schema and sync model definitions (use --all/--apply)
```

#### Utility Commands

```bash
Reut -v                    # Show CLI version
Reut -h                    # Show help message
```

---

## API Documentation

### Endpoints Overview

REUT automatically generates CRUD endpoints for each model. All endpoints follow RESTful conventions.

### Model Endpoints

For a model named `Users`, the following endpoints are automatically created:

#### List All Records

```http
GET /Users/all
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com"
    }
  ]
}
```

#### Get Single Record

```http
GET /Users/find/1
```

**Response:**
```json
{
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com"
  }
}
```

#### Create Record

```http
POST /Users/add
Content-Type: application/json

{
  "name": "Jane Doe",
  "email": "jane@example.com"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 2,
    "name": "Jane Doe",
    "email": "jane@example.com"
  }
}
```

#### Update Record

```http
PUT /Users/update/1
Content-Type: application/json

{
  "name": "John Updated"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "John Updated",
    "email": "john@example.com"
  }
}
```

#### Delete Record

```http
DELETE /Users/delete/1
```

**Response:**
```json
{
  "success": true,
  "message": "Record deleted successfully"
}
```

### API Documentation Endpoint

```http
GET /docs
GET /docs?format=json
```

Returns a list of all registered routes. Can be disabled by setting `REUT_DOCS_ENABLED=false` in `.env`.

### Schema Viewer

```http
GET /schema
```

Visual schema viewer showing all models, their fields, relationships, and route information. Can be disabled by setting `REUT_SCHEMA_ENABLED=false` in `.env`.

---

## Authentication

### Built-in Authentication Endpoints

REUT provides ready-to-use authentication endpoints when enabled (default during `Reut init`):

#### Login

```http
POST /auth/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password123"
}
```

**Response:**
```json
{
  "success": true,
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "user": {
    "id": 1,
    "email": "user@example.com"
  }
}
```

#### Register

```http
POST /auth/register
Content-Type: application/json

{
  "email": "newuser@example.com",
  "password": "password123",
  "name": "New User"
}
```

**Response:**
```json
{
  "success": true,
  "message": "User registered successfully",
  "user": {
    "id": 2,
    "email": "newuser@example.com",
    "name": "New User"
  }
}
```

#### Refresh Token

```http
POST /auth/refresh
Content-Type: application/json

{
  "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

**Response:**
```json
{
  "success": true,
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

#### Logout

```http
POST /auth/logout
Authorization: Bearer <token>
```

**Response:**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

### Configuration

Authentication is configured via `auth.php` (generated during init):

```php
<?php

return [
    'table' => 'Users',  // or your custom table
    'fields' => [
        'identifier' => 'email',  // 'email' or 'username'
        'password' => 'password',
    ],
    'token_expiry' => 3600,  // seconds
    'refresh_token_expiry' => 86400,  // 24 hours
    'auto_create_table' => true,  // Auto-create Users table if it doesn't exist
];
```

### Using JWT Tokens

Include the JWT token in the `Authorization` header:

```http
Authorization: Bearer <your-jwt-token>
```

### Disabling Authentication

Disable authentication endpoints by setting `REUT_AUTH_ENABLED=false` in `.env`.

### Customizing Authentication

Extend `Reut\Auth\AuthController` to customize validation, password hashing, or response formatting:

```php
<?php

use Reut\Auth\AuthController;

class CustomAuthController extends AuthController
{
    protected function validateLogin(array $credentials): ?array
    {
        // Add custom checks (e.g., check if user is active)
        $user = parent::validateLogin($credentials);
        if ($user && $user['is_active'] === false) {
            return null; // Reject inactive users
        }
        return $user;
    }
}
```

### Per-Model Authentication

Enable authentication for specific models by setting `requiresAuth` to `true` in the model constructor:

```php
<?php

use Reut\DB\DataBase;

class Users extends DataBase
{
    public function __construct($config)
    {
        parent::__construct(
            $config,
            [],
            'Users',
            false,
            [],
            [], // File fields
            [], // Disabled routes
            ['created_at', 'updated_at'], // Protected columns
            null, // strictRequiredValidation
            [], // File field types
            true // requiresAuth - enables JWT authentication for all routes
        );
    }
}
```

When `requiresAuth` is `true`:
- All CRUD endpoints for that model require a valid JWT token
- The `Authorization: Bearer <token>` header must be included in requests
- Auth status is displayed in the schema viewer at `/schema`

---

## Database & Models

### Creating a Model

Generate a model using the CLI:

```bash
Reut generate:model Products
```

This creates a file `models/Products.php`:

```php
<?php

use Reut\DB\DataBase;

class Products extends DataBase
{
    public function __construct($config)
    {
        parent::__construct(
            $config,
            [
                // Define your fields here
                'name' => new \Reut\DB\Types\Varchar(255),
                'price' => new \Reut\DB\Types\Decimal(10, 2),
                'description' => new \Reut\DB\Types\Text(),
                'created_at' => new \Reut\DB\Types\Timestamp(),
            ],
            'Products',
            false,
            [],
            [], // File fields
            [], // Disabled routes
            ['created_at', 'updated_at'], // Protected columns
            null, // strictRequiredValidation
            [], // File field types
            false // requiresAuth
        );
    }
}
```

### Available Field Types

REUT provides various field types:

- `Varchar($length)` - Variable-length string
- `Text()` - Long text
- `Integer()` - Integer
- `BigInteger()` - Big integer
- `SmallInteger()` - Small integer
- `TinyInteger()` - Tiny integer
- `Decimal($precision, $scale)` - Decimal number
- `FloatType()` - Floating point number
- `DoubleType()` - Double precision number
- `Boolean()` - Boolean
- `Date()` - Date
- `DateTimeType()` - Date and time
- `TimeType()` - Time
- `Timestamp()` - Timestamp
- `Json()` - JSON data
- `Blob()` - Binary large object
- `EnumType($values)` - Enumeration

### Defining Relationships

Define foreign keys directly inside your model classes:

```php
<?php

use Reut\DB\DataBase;

class Orders extends DataBase
{
    public function __construct($config)
    {
        parent::__construct(
            $config,
            [
                'user_id' => new \Reut\DB\Types\Integer(),
                'total' => new \Reut\DB\Types\Decimal(10, 2),
            ],
            'Orders',
            false,
            [],
            [],
            [],
            ['created_at', 'updated_at'],
            null,
            [],
            false
        );
        
        // Define foreign key relationship
        $this->addForeignKey('user_id', 'Users');
    }
}
```

Each call to `addForeignKey` automatically marks the table as relational and contributes to the relationship count so migrations know to create parent tables first.

### File Upload Fields

Define file upload fields in your model:

```php
<?php

use Reut\DB\DataBase;

class Products extends DataBase
{
    public function __construct($config)
    {
        parent::__construct(
            $config,
            [
                'name' => new \Reut\DB\Types\Varchar(255),
                'image' => new \Reut\DB\Types\Varchar(255), // Store file path
            ],
            'Products',
            false,
            [],
            ['image'], // File fields - these will be handled as file uploads
            [],
            ['created_at', 'updated_at'],
            null,
            ['image' => 'image'], // File field types: 'image', 'file', etc.
            false
        );
    }
}
```

### Disabled Routes

Control which CRUD routes are generated for each model:

```php
<?php

parent::__construct(
    $config,
    [],
    'Users',
    false,
    [],
    [], // File fields
    ['add', 'delete'], // Disabled routes: 'all', 'find', 'add', 'update', 'delete'
    ['created_at', 'updated_at'], // Protected columns
    null, // strictRequiredValidation
    [], // File field types
    false // requiresAuth
);
```

Route options:
- `'all'` - Disables all CRUD routes (useful for read-only models)
- `'find'` - Disables `GET /{model}/find/{id}`
- `'add'` - Disables `POST /{model}/add`
- `'update'` - Disables `PUT /{model}/update/{id}`
- `'delete'` - Disables `DELETE /{model}/delete/{id}`

Disabled routes are automatically excluded from route generation and shown in the schema viewer at `/schema`.

### Protected Columns

Protected columns are automatically managed by REUT and cannot be modified through API calls:

```php
['created_at', 'updated_at'] // Protected columns
```

Common protected columns include:
- `created_at` - Automatically set on record creation
- `updated_at` - Automatically updated on record modification
- `id` - Primary key (always protected)

---

## Migrations

REUT provides a comprehensive migration system that automatically manages database schema changes based on your model definitions.

### Basic Migration Commands

#### Apply Migrations

```bash
Reut migrate
```

Applies all pending migrations from models to the database. Creates tables, adds columns, and respects protected columns.

#### Preview Migrations (Dry-Run)

```bash
Reut migrate --dry-run
```

Preview migrations without executing them. Shows what changes would be made.

#### Sync Database

```bash
Reut sync
```

Aggressively reconciles database with models. Can drop columns and orphan tables (use with caution).

```bash
Reut sync --dry-run
```

Preview sync changes without executing.

#### Check Migration Status

```bash
Reut status
```

Check pending migrations without applying them.

```bash
Reut status --json
```

Output migration status as JSON for scripting.

```bash
Reut status --summary
```

Show summary of migration status.

```bash
Reut status --table=users
```

Check status for specific table.

### Advanced Migration Features

#### Rollback Migrations

```bash
Reut rollback
```

Rollback last batch of migrations.

```bash
Reut rollback --batch=2
```

Rollback specific batch number.

```bash
Reut rollback --migration=create_users_table_20240101120000
```

Rollback specific migration.

```bash
Reut rollback --dry-run
```

Preview rollback without executing.

#### Validate Migrations

```bash
Reut validate-migrations
```

Check migration SQL syntax and detect conflicts before applying.

#### Export/Import Migrations

```bash
Reut export-migrations
```

Export migration history to `migrations.json`.

```bash
Reut export-migrations --format=sql
```

Export to `migrations.sql`.

```bash
Reut import-migrations migrations.json
```

Import migration history from JSON file.

```bash
Reut import-migrations migrations.sql
```

Import migration history from SQL file.

### Migration Features (v1.3.0+)

- **Dry-run mode**: Preview migrations before executing
- **Rollback support**: Rollback migrations by batch or specific migration
- **Migration validation**: Validate SQL syntax and detect conflicts before applying
- **Export/Import**: Export and import migration history for backup or sharing
- **Enhanced status**: JSON output, summary mode, and table-specific status checks
- **Protected columns**: Automatic protection of common columns (`created_at`, `updated_at`, etc.)

---

## Routing

### Custom Routes

Add custom routes in the `routers` directory. REUT automatically scans for `*Router.php` files and registers them.

### Using ReuteRoute

Import `Reut\Router\ReuteRoute` inside your router classes; it wraps Slim's router and auto-records metadata for `/docs`:

```php
<?php

use Reut\Router\ReuteRoute;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CustomRouter
{
    protected $app;
    
    public function __construct($app, $config)
    {
        $this->app = $app;
        $this->registerRoutes();
    }
    
    protected function registerRoutes()
    {
        $routes = ReuteRoute::use($this->app);
        
        // Grouped endpoints
        $routes->group('/invoices', 'Invoices', function (ReuteRoute $group) {
            $group->get('/all', function (Request $request, Response $response) {
                // Handler logic
                return $response;
            }, 'List invoices');
            
            $group->post('/pay/{id}', function (Request $request, Response $response) {
                // Handler logic
                return $response;
            }, 'Pay invoice', true); // true = requires authentication
        });
        
        // Standalone route
        $routes->get('/health', function (Request $request, Response $response) {
            $response->getBody()->write(json_encode(['status' => 'ok']));
            return $response->withHeader('Content-Type', 'application/json');
        }, 'Service healthcheck');
    }
}
```

### Route Documentation

Generated routers already use `ReuteRoute`, so CRUD endpoints appear automatically. Custom routes simply adopt the helper to stay documented in the `/docs` endpoint.

### Route Groups

Group related routes together:

```php
$routes->group('/api/v1', 'API v1', function (ReuteRoute $group) {
    $group->get('/users', $handler, 'Get users');
    $group->post('/users', $handler, 'Create user');
});
```

---

## Middleware

REUT includes several built-in middleware components:

### JWT Authentication Middleware

Automatically applied when `requiresAuth` is enabled on routes or models:

```php
use Reut\Middleware\JwtAuth;

$authMiddleware = new JwtAuth($config);
$app->add($authMiddleware);
```

### Rate Limiting Middleware

Protects your API from abuse:

```php
use Reut\Middleware\RateLimitMiddleware;

$app->add(new RateLimitMiddleware($app));
```

### CSRF Protection Middleware

Protects against Cross-Site Request Forgery:

```php
use Reut\Middleware\CsrfMiddleware;

$app->add(new CsrfMiddleware($app));
```

### Custom Middleware

Create custom middleware by implementing PSR-15 middleware interface:

```php
<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class CustomMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        // Your middleware logic here
        $response = $handler->handle($request);
        return $response;
    }
}
```

---

## Plugins

REUT features a plugin system that allows you to extend the framework's functionality. Plugins are separate Composer packages that integrate seamlessly with REUT.

### Available Plugins

#### Email Plugin (`reut/email`)

The Email plugin provides SMTP email sending functionality for the REUT framework.

**Installation:**

```bash
composer require reut/email
```

**Configuration:**

Add the following environment variables to your `.env` file:

```env
REUT_EMAIL_ENABLED=true
REUT_EMAIL_REQUIRES_AUTH=false
REUT_EMAIL_SMTP_HOST=smtp.gmail.com
REUT_EMAIL_SMTP_PORT=587
REUT_EMAIL_SMTP_USERNAME=your-email@gmail.com
REUT_EMAIL_SMTP_PASSWORD=your-app-password
REUT_EMAIL_SMTP_ENCRYPTION=tls
REUT_EMAIL_FROM_ADDRESS=noreply@example.com
REUT_EMAIL_FROM_NAME=REUT Framework
```

**Configuration Options:**

- `REUT_EMAIL_ENABLED` - Enable/disable email functionality (default: `false`)
- `REUT_EMAIL_REQUIRES_AUTH` - Require JWT authentication for email endpoints (default: `false`)
- `REUT_EMAIL_SMTP_HOST` - SMTP server hostname (required)
- `REUT_EMAIL_SMTP_PORT` - SMTP server port (default: `587`)
- `REUT_EMAIL_SMTP_USERNAME` - SMTP username (optional if no authentication)
- `REUT_EMAIL_SMTP_PASSWORD` - SMTP password (optional if no authentication)
- `REUT_EMAIL_SMTP_ENCRYPTION` - Encryption type: `tls`, `ssl`, or empty (default: `tls`)
- `REUT_EMAIL_FROM_ADDRESS` - Default from email address (required)
- `REUT_EMAIL_FROM_NAME` - Default from name (default: `REUT Framework`)

**Integration:**

Add the email router to your project's `routers/routes.php`:

```php
<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Reut\Auth\AuthRouter;
use Reut\Router\DocsController;
use Reut\Router\SchemaController;
use Reut\Router\ReuteRoute;
use Reut\Email\EmailRouter;
use Reut\Email\Config\EmailConfig;
use Slim\App;

return function (App $app, array $config): void {
    // ... existing routes ...

    // Register email routes
    if ((strtolower($_ENV['REUT_EMAIL_ENABLED'] ?? 'false')) === 'true') {
        $emailConfig = EmailConfig::load();
        if (EmailConfig::isEnabled($emailConfig)) {
            new EmailRouter($app, $config, $emailConfig);
        }
    }
};
```

**Authentication:**

By default, email endpoints are publicly accessible. To require JWT authentication for all email endpoints, set:

```env
REUT_EMAIL_REQUIRES_AUTH=true
```

When enabled, all requests to email endpoints must include a valid JWT token in the `Authorization` header:

```
Authorization: Bearer <your-jwt-token>
```

**API Endpoints:**

##### Send Email

**POST** `/email/send`

Send an email via SMTP.

**Authentication:** Optional (controlled by `REUT_EMAIL_REQUIRES_AUTH`)

**Request Body:**
```json
{
    "to": "recipient@example.com",
    "subject": "Hello from REUT",
    "body": "<h1>Hello</h1><p>This is a test email.</p>",
    "bodyType": "html",
    "cc": "cc@example.com",
    "bcc": ["bcc1@example.com", "bcc2@example.com"],
    "replyTo": "reply@example.com",
    "replyToName": "Reply Name"
}
```

**Response (Success):**
```json
{
    "success": true,
    "message": "Email sent successfully"
}
```

**Response (Error):**
```json
{
    "error": true,
    "message": "Validation failed",
    "errors": ["to field is required"]
}
```

##### Check Status

**GET** `/email/status`

Check email service status and configuration.

**Authentication:** Optional (controlled by `REUT_EMAIL_REQUIRES_AUTH`)

**Response:**
```json
{
    "status": "ok",
    "smtp_configured": true,
    "smtp_host": "smtp.gmail.com",
    "smtp_port": 587,
    "from_address": "noreply@example.com",
    "errors": []
}
```

**Usage Examples:**

##### Basic Email Sending

```php
use Reut\Email\EmailService;
use Reut\Email\Config\EmailConfig;

$emailConfig = EmailConfig::load();
$emailService = new EmailService($emailConfig);

try {
    $emailService->send(
        'user@example.com',
        'Welcome!',
        '<h1>Welcome to our service</h1>',
        'html'
    );
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

##### Email with Options

```php
$emailService->send(
    'user@example.com',
    'Invoice',
    '<p>Please find your invoice attached.</p>',
    'html',
    [
        'cc' => 'manager@example.com',
        'bcc' => ['archive@example.com'],
        'replyTo' => 'support@example.com',
        'attachments' => [
            '/path/to/invoice.pdf',
            [
                'path' => '/path/to/receipt.pdf',
                'name' => 'receipt.pdf'
            ]
        ]
    ]
);
```

**Requirements:**

- PHP >= 7.4
- REUT Core ^1.1
- PHPMailer ^6.9

**License:** MIT License

**Support:** For issues and questions, please visit: https://github.com/m4rcTr3y/Reut-Email/issues

### Creating Your Own Plugin

To create a REUT plugin:

1. Create a new Composer package with `reut/` prefix
2. Require `reut/core` as a dependency
3. Create a router class that extends `Reut\Auth\NoAuth` or implements your own routing
4. Register routes using `Reut\Router\ReuteRoute`
5. Document your plugin's configuration and usage

Example plugin structure:

```
your-plugin/
├── composer.json
├── README.md
└── src/
    ├── YourPluginRouter.php
    ├── YourPluginService.php
    └── Config/
        └── YourPluginConfig.php
```

---

## Configuration

### Environment Variables

REUT uses environment variables for configuration. Create a `.env` file in your project root:

```env
# Application
APP_ENV=development
APP_DEBUG=true

# Database
DB_TYPE=mysql
DB_HOST=localhost
DB_NAME=test_db
DB_USER=root
DB_PASS=

# Authentication
REUT_AUTH_ENABLED=true
JWT_SECRET=your-secret-key-here

# Documentation
REUT_DOCS_ENABLED=true
REUT_SCHEMA_ENABLED=true

# Plugins
REUT_EMAIL_ENABLED=false
REUT_EMAIL_REQUIRES_AUTH=false
```

### Configuration File

The `config.php` file is generated during project initialization and contains database connection settings:

```php
<?php

return [
    'database' => [
        'type' => $_ENV['DB_TYPE'] ?? 'mysql',
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'name' => $_ENV['DB_NAME'] ?? 'test_db',
        'user' => $_ENV['DB_USER'] ?? 'root',
        'pass' => $_ENV['DB_PASS'] ?? '',
    ],
];
```

### Authentication Configuration

Configure authentication in `auth.php`:

```php
<?php

return [
    'table' => 'Users',
    'fields' => [
        'identifier' => 'email',
        'password' => 'password',
    ],
    'token_expiry' => 3600,
    'refresh_token_expiry' => 86400,
    'auto_create_table' => true,
];
```

---

## Troubleshooting

### Command Not Found

**Problem:** `Reut: command not found`

**Solution:** Ensure Composer's `vendor/bin` is in your PATH. See [Installation](#installation) section.

### Stability Error

**Problem:** Composer stability error when installing

**Solution:** Install the development version:

```bash
composer global require m4rc/reut_cli:dev-main
```

### Missing Files

**Problem:** Missing required files after installation

**Solution:** Ensure your project includes required templates and source files. Contact the maintainer if issues persist.

### Runtime Errors

**Problem:** Errors when running commands

**Solution:** Run commands with `--verbose` for more details. Check that all dependencies are installed:

```bash
composer install
```

### Database Connection Errors

**Problem:** Cannot connect to database

**Solution:** 
1. Verify database credentials in `.env` or `config.php`
2. Ensure database server is running
3. Check that database exists
4. Verify user permissions

### Migration Errors

**Problem:** Migration fails or conflicts

**Solution:**
1. Use `Reut validate-migrations` to check for issues
2. Use `Reut migrate --dry-run` to preview changes
3. Check migration history with `Reut status`
4. Rollback if needed: `Reut rollback`

### Environment Configuration

**Problem:** Errors in production

**Solution:** Set `APP_ENV=production` in `.env` to hide detailed stack traces and enable production optimizations. Never run with debug mode enabled in public environments.

---

## Contributing

Contributions are welcome! Here's how you can help:

### Reporting Issues

If you find a bug or have a feature request, please open an issue on GitHub:

- **Main Repository:** https://github.com/m4rcTr3y/Reut-Cli
- **Email Plugin:** https://github.com/m4rcTr3y/Reut-Email

### Submitting Pull Requests

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Add tests if applicable
5. Commit your changes (`git commit -m 'Add amazing feature'`)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

### Code Style

- Follow PSR-12 coding standards
- Add PHPDoc comments for all classes and methods
- Write tests for new features
- Update documentation as needed

### Plugin Development

If you're creating a plugin:

1. Follow the plugin structure outlined in the [Plugins](#plugins) section
2. Use `reut/` prefix for package name
3. Require `reut/core` as a dependency
4. Document installation, configuration, and usage
5. Submit to Packagist for distribution

---

## License

MIT License

---

## Support

- **Email:** marctrevis61@proton.me
- **GitHub Issues:** https://github.com/m4rcTr3y/Reut-Cli/issues
- **Homepage:** https://github.com/m4rcTr3y/Reut-Cli

---

## Changelog

### v1.3.7 (Current)

- Advanced migration system with rollback support
- Migration validation and export/import
- Enhanced status reporting
- Protected columns support
- Improved error handling

### v1.3.0

- Dry-run mode for migrations
- Rollback support
- Migration validation
- Export/Import functionality
- Enhanced status reporting

### v1.2.0

- Per-model authentication
- Disabled routes support
- Schema viewer improvements
- File upload enhancements

### v1.1.0

- Plugin system
- Email plugin
- Improved routing
- Better documentation

### v1.0.0

- Initial release
- Core framework features
- Model-based database management
- Built-in authentication
- CRUD API generation

---

*Last updated: 2024*


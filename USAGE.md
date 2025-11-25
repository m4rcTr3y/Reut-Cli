# REUT Framework Usage Guide

This guide covers how to use the REUT framework from installation to building production-ready APIs.

## Table of Contents

1. [Installation](#installation)
2. [Project Setup](#project-setup)
3. [Creating Models](#creating-models)
4. [Database Migrations](#database-migrations)
5. [Generating Routes](#generating-routes)
6. [Custom Routes](#custom-routes)
7. [Authentication](#authentication)
8. [API Documentation](#api-documentation)
9. [Schema Inspection](#schema-inspection)
10. [Development Workflow](#development-workflow)
11. [Examples](#examples)

---

## Installation

### Prerequisites

- PHP 7.4 or higher
- Composer installed globally
- MySQL or PostgreSQL database server

### Install REUT CLI Globally

```bash
composer global require m4rc/reut_cli
```

### Add Composer Bin to PATH

**Linux/macOS:**
```bash
# Add to ~/.bashrc or ~/.zshrc
export PATH="$HOME/.composer/vendor/bin:$PATH"

# Reload shell
source ~/.bashrc
```

**Windows:**
- Add `%APPDATA%\Composer\vendor\bin` to your system PATH via Environment Variables

### Verify Installation

```bash
Reut -v
# Should output: Reut CLI v1.1.0 (or newer)
```

---

## Project Setup

### Initialize a New Project

Run `Reut init` anywhere to create a new project:

```bash
Reut init
```

You'll be prompted for:
- **Project name** (default: `myproject`)
- **Database type** (`mysql` or `postgresql`)
- **Database name** (default: `test_db`)
- **Database username** (default: `root`)
- **Database password** (optional)
- **Secret key** (default: `12345678`) - used for JWT tokens

This copies the bundled skeleton into a new directory. The skeleton contains only the files you edit; the framework logic is delivered through the Composer package `reut/core`.

Project layout after init:

```
myproject/
├─ config/            # config.php, auth.php (user editable)
├─ models/            # your models
├─ routers/           # routes.php + custom routers
├─ devserver/, viewer/ (optional scaffolds)
├─ index.php, manage.php  # bootstrap files that define REUT_PROJECT_ROOT
├─ composer.json      # requires "reut/core": "^1.1"
└─ vendor/reut/core   # framework internals (do not edit)
```

### Install Dependencies

Navigate to your project and install Composer dependencies:

```bash
cd myproject
composer install
```

> **Note:** `reut/core` lives entirely under `vendor/`. Use `composer update reut/core` to get framework bug fixes; you only commit your application files.

---

## Creating Models

### Generate a Model

Create a new model class:

```bash
Reut generate:model Users
```

This generates `models/UsersTable.php` with a basic structure:

```php
<?php
declare(strict_types=1);

namespace Reut\Models;

use Reut\DB\DataBase;
use Reut\DB\Types\Varchar;
use Reut\DB\Types\Integer;

class UsersTable extends DataBase
{
    public function __construct(array $config)
    {
        parent::__construct(
            $config,
            [],
            'Users',
            false,
            [],
            ['all']
        );

        // Primary key
        $this->addColumn('id', new Integer(
            false,  // Not nullable
            true,   // Is primary key
            true,   // Auto-increment
            null    // Default value
        ));

        // TODO: Add your columns here
    }
}
```

### Define Columns

Add columns using type classes:

```php
// String column
$this->addColumn('name', new Varchar(255, false)); // length, nullable

// Integer column
$this->addColumn('age', new Integer(true, false, false, 0)); // nullable, primary, auto-increment, default

// Text column
$this->addColumn('bio', new Text(true)); // nullable

// Boolean column
$this->addColumn('is_active', new Boolean(false, true)); // nullable, default

// Date/DateTime columns
$this->addColumn('created_at', new Timestamp(false, true)); // nullable, use CURRENT_TIMESTAMP
$this->addColumn('updated_at', new DateTimeType(true)); // nullable

// Decimal column
$this->addColumn('price', new Decimal(10, 2, false, 0.00)); // precision, scale, nullable, default

// JSON column
$this->addColumn('metadata', new Json(true)); // nullable
```

### Define Relationships

Add foreign keys:

```php
// Simple foreign key
$this->addForeignKey('user_id', 'Users');

// With custom referenced column and cascade behavior
$this->addForeignKey(
    'author_id',
    'Users',
    'id',           // referenced column
    'CASCADE',      // ON DELETE
    'CASCADE'       // ON UPDATE
);
```

### File Upload Fields

Specify which columns handle file uploads:

```php
public function __construct(array $config)
{
    parent::__construct(
        $config,
        [],
        'Products',
        false,
        [],
        ['image', 'thumbnail'], // File upload fields
        ['all']
    );
    // ...
}
```

### Disable Routes

Prevent certain CRUD routes from being generated:

```php
parent::__construct(
    $config,
    [],
    'Users',
    false,
    [],
    [],
    ['delete', 'update'] // Disable DELETE and UPDATE routes
);
```

---

## Database Migrations

### Create Tables

Run migrations to create tables from your models:

```bash
Reut create
# or
Reut migrate
```

This will:
- Create the database if it doesn't exist
- Create tables for all models
- Handle relationship ordering (parent tables before children)
- Record migrations in the `migrations` table

### Check Migration Status

See which models have pending changes:

```bash
Reut status
```

### Sync Schema (Advanced)

Aggressively reconcile database with models (may drop extra columns):

```bash
Reut sync
```

**Warning:** This command will:
- Add missing columns from models
- Drop columns that exist in DB but not in models
- Prompt to drop tables without corresponding models

Use with caution in production!

---

## Generating Routes

### Auto-Generate CRUD Routes

Generate route files for all models:

```bash
Reut generate:routes
```

This creates router files in `routers/` directory:
- `UsersRouter.php`
- `ProductsRouter.php`
- etc.

Each router provides these endpoints:
- `GET /{model}/all` - List all records (paginated)
- `GET /{model}/find/{id}` - Get single record
- `POST /{model}/add` - Create new record
- `PUT /{model}/update/{id}` - Update record
- `DELETE /{model}/delete/{id}` - Delete record

### Register Routes

Routes are automatically registered in `routers/routes.php`. This file is generated/updated when you run `generate:routes`.

---

## Custom Routes

### Create a Custom Router

Create a new router file in `routers/`:

```php
<?php
declare(strict_types=1);

namespace Reut\Routers;

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Reut\Auth\NoAuth;
use Reut\Router\ReuteRoute;

class InvoicesRouter extends NoAuth
{
    protected $config;
    protected $app;

    public function __construct(App $app, array $config)
    {
        $this->app = $app;
        $this->config = $config;
        parent::__construct($app);
    }

    protected function genRoutes()
    {
        $routes = ReuteRoute::use($this->app);

        // Standalone route
        $routes->get('/health', function (Request $request, Response $response) {
            $response->getBody()->write(json_encode(['status' => 'ok']));
            return $response->withHeader('Content-Type', 'application/json');
        }, 'Health check endpoint');

        // Grouped routes
        $routes->group('/invoices', 'Invoices API', function ($group) {
            $group->get('/pending', function (Request $request, Response $response) {
                // Handler logic
            }, 'List pending invoices');

            $group->post('/pay/{id}', function (Request $request, Response $response, array $args) {
                // Handler logic
            }, 'Pay an invoice', true); // true = requires authentication
        });
    }
}
```

### Using Authentication

Extend `Auth` instead of `NoAuth`:

```php
use Reut\Auth\Auth;

class SecureRouter extends Auth
{
    // All routes in this router require JWT authentication
}
```

### Route Methods

Available methods on `ReuteRoute`:
- `get($path, $handler, $description, $authRequired = false)`
- `post($path, $handler, $description, $authRequired = false)`
- `put($path, $handler, $description, $authRequired = false)`
- `patch($path, $handler, $description, $authRequired = false)`
- `delete($path, $handler, $description, $authRequired = false)`
- `group($prefix, $label, $callback)` - Creates a route group

All routes registered via `ReuteRoute` are automatically documented in `/docs`.

---

## Authentication

### JWT Configuration

Set your secret key in `.env`:

```env
SECRET_KEY=your-secret-key-here
```

### Generate Tokens

In your authentication logic:

```php
use Reut\Middleware\JwtAuth;

$auth = new JwtAuth($config);
$token = $auth->generateToken($userId, 3600); // userId, expiry in seconds
```

### Validate Tokens

The `Auth` router class automatically validates JWT tokens from the `Authorization` header:

```
Authorization: Bearer <token>
```

### Refresh Tokens

```php
$refreshToken = $auth->generateRefreshToken($userId);
$isValid = $auth->validateRefreshToken($userId, $refreshToken);
```

### Revoke Tokens

```php
// Revoke specific token
$auth->revokeRefreshToken($userId, $refreshToken);

// Revoke all tokens for user
$auth->revokeRefreshToken($userId);
```

---

## API Documentation

### Access Documentation

Once routes are registered, visit:

```
http://localhost:9000/docs
```

Or with format parameter:

```
http://localhost:9000/docs?format=json
```

### Disable in Production

Set in `.env`:

```env
REUT_DOCS_ENABLED=false
```

The `/docs` endpoint will return 404 when disabled.

### Documentation Features

- Lists all registered endpoints
- Shows HTTP method, path, description
- Indicates authentication requirements
- Groups endpoints by router
- Available in JSON or HTML format

---

## Schema Inspection

### Inspect a Single Table

Preview database schema and sync to model:

```bash
Reut inspect --table=users
```

This will:
1. Read the table structure from the database
2. Generate model column definitions
3. Show a preview of the code
4. Ask for confirmation to apply changes

### Inspect All Tables

```bash
Reut inspect --all
```

### Auto-Apply Changes

Skip confirmation prompt:

```bash
Reut inspect --table=users --apply
```

### Interactive Selection

Run without arguments to select from available tables:

```bash
Reut inspect
```

---

## Development Workflow

### Typical Workflow

1. **Create a model:**
   ```bash
   Reut generate:model Products
   ```

2. **Define columns in the model file:**
   ```php
   $this->addColumn('name', new Varchar(255, false));
   $this->addColumn('price', new Decimal(10, 2, false));
   ```

3. **Run migrations:**
   ```bash
   Reut migrate
   ```

4. **Generate routes:**
   ```bash
   Reut generate:routes
   ```

5. **Start development server:**
   ```bash
   Reut dev --port=9000
   ```

6. **View API documentation:**
   ```
   http://localhost:9000/docs
   ```

### Making Changes

**Adding a column:**
1. Edit model file, add `$this->addColumn(...)`
2. Run `Reut migrate` to apply changes

**Modifying schema manually:**
1. Make changes directly in database
2. Run `Reut inspect --table=<name>` to sync back to model
3. Review preview and apply if correct

**Adding custom routes:**
1. Create router file in `routers/`
2. Use `ReuteRoute` to register routes
3. Routes appear in `/docs` automatically

---

## Examples

---

## Upgrading Existing Projects

Older projects created before `reut/core` was published copied the framework directly into `config/`. To migrate:

1. Run `composer require reut/core:^1.1`.
2. Remove duplicated framework folders (`config/auth`, `config/middleware`, `config/router`, etc.), keeping only the files you actively customize.
3. Make sure `manage.php`, `index.php`, `config.php`, and `auth.php` define `REUT_PROJECT_ROOT` and load `vendor/autoload.php` (copy them from a fresh skeleton if needed).
4. Run `composer install` and test `Reut create`, `Reut dev`, etc.

After migrating, upgrades are as simple as `composer update reut/core`.

### Complete Model Example

```php
<?php
declare(strict_types=1);

namespace Reut\Models;

use Reut\DB\DataBase;
use Reut\DB\Types\Varchar;
use Reut\DB\Types\Integer;
use Reut\DB\Types\Text;
use Reut\DB\Types\Boolean;
use Reut\DB\Types\Timestamp;
use Reut\DB\Types\Decimal;

class ProductsTable extends DataBase
{
    public function __construct(array $config)
    {
        parent::__construct(
            $config,
            [],
            'Products',
            false,
            [],
            ['image'], // File upload field
            [] // No disabled routes
        );

        // Primary key
        $this->addColumn('id', new Integer(false, true, true, null));

        // String fields
        $this->addColumn('name', new Varchar(255, false));
        $this->addColumn('slug', new Varchar(255, false));
        $this->addColumn('description', new Text(true));

        // Numeric fields
        $this->addColumn('price', new Decimal(10, 2, false, 0.00));
        $this->addColumn('stock', new Integer(false, false, false, 0));

        // Boolean field
        $this->addColumn('is_active', new Boolean(false, true));

        // Timestamps
        $this->addColumn('created_at', new Timestamp(false, true));
        $this->addColumn('updated_at', new Timestamp(true, false, true));

        // Foreign key relationship
        $this->addForeignKey('category_id', 'Categories');
    }
}
```

### Custom Router Example

```php
<?php
declare(strict_types=1);

namespace Reut\Routers;

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Reut\Auth\Auth; // Requires authentication
use Reut\Router\ReuteRoute;
use Reut\Models\OrdersTable;

class OrdersRouter extends Auth
{
    protected $config;
    protected $app;

    public function __construct(App $app, array $config)
    {
        $this->app = $app;
        $this->config = $config;
        parent::__construct($app, $config);
    }

    protected function genRoutes()
    {
        $routes = ReuteRoute::use($this->app);

        $routes->group('/orders', 'Orders Management', function ($group) use ($routes) {
            $ordersModel = new OrdersTable($this->config);

            $group->get('/my-orders', function (Request $request, Response $response) use ($ordersModel) {
                // Get user ID from JWT token (set by Auth middleware)
                $userId = $request->getAttribute('userId');
                
                $orders = $ordersModel->findOne(['user_id' => $userId]);
                $response->getBody()->write(json_encode($orders->results));
                return $response->withHeader('Content-Type', 'application/json');
            }, 'Get current user orders', true);

            $group->post('/checkout', function (Request $request, Response $response) use ($ordersModel) {
                $input = $request->getParsedBody();
                $result = $ordersModel->addOne($input);
                
                $response->getBody()->write(json_encode(['status' => $result]));
                return $response->withHeader('Content-Type', 'application/json');
            }, 'Create new order', true);
        });
    }
}
```

### Environment Configuration

`.env` file structure:

```env
SECRET_KEY=your-secret-key-here
DB_USERNAME=root
DB_PASSWORD=your-password
DB_NAME=my_database
DB_TYPE=mysql
APP_ENV=development
REUT_DOCS_ENABLED=true
```

**Production settings:**
```env
APP_ENV=production
REUT_DOCS_ENABLED=false
```

---

## Troubleshooting

### Common Issues

**"Command not found"**
- Ensure Composer's `vendor/bin` is in your PATH
- Reload your shell after adding to PATH

**"Database connection failed"**
- Check `.env` file has correct credentials
- Verify database server is running
- Ensure database exists (or let REUT create it)

**"Class not found"**
- Run `composer install` in project directory
- Check autoload paths in `composer.json`

**Routes not working**
- Ensure `Reut generate:routes` was run
- Check `routers/routes.php` includes your router
- Verify server is running (`Reut dev`)

**Migrations not applying**
- Check `migrations` table exists
- Verify model classes are in `models/` directory
- Run `Reut status` to see pending changes

---

## Best Practices

1. **Always run migrations in development first** before applying to production
2. **Use protected columns** for audit fields (`created_at`, `updated_at`)
3. **Disable docs in production** via `REUT_DOCS_ENABLED=false`
4. **Use environment variables** for sensitive configuration
5. **Version control your models** - they define your schema
6. **Test custom routes** before deploying
7. **Use transactions** for complex operations in custom routes
8. **Validate input** in custom route handlers
9. **Handle errors gracefully** and return appropriate HTTP status codes
10. **Document custom endpoints** with clear descriptions in `ReuteRoute` calls

---

## Additional Resources

- GitHub: [https://github.com/m4rcTr3y/Reut-Cli](https://github.com/m4rcTr3y/Reut-Cli)
- Packagist: [https://packagist.org/packages/m4rc/reut_cli](https://packagist.org/packages/m4rc/reut_cli)

For issues or contributions, please visit the GitHub repository.


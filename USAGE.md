# REUT Framework Usage Guide

This guide covers how to use the REUT framework from installation to building production-ready APIs.

## Table of Contents

1. [Installation](#installation)
2. [Project Setup](#project-setup)
3. [Project Structure](#project-structure)
4. [Creating Models](#creating-models)
5. [Database Migrations](#database-migrations)
6. [Generating Routes](#generating-routes)
7. [Custom Routes](#custom-routes)
8. [Authentication](#authentication)
9. [API Documentation](#api-documentation)
10. [Schema Viewer](#schema-viewer)
11. [Schema Inspection](#schema-inspection)
12. [Development Workflow](#development-workflow)
13. [CLI Commands](#cli-commands)
14. [Environment Variables](#environment-variables)
15. [Examples](#examples)

---

## Installation

### Prerequisites

- PHP 7.4 or higher
- Composer installed globally
- MySQL database server

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
# Should output: Reut CLI v1.1.9
```

### Update REUT CLI

Update to the latest version with a single command:

```bash
Reut update
```

This automatically:
- Clears the composer cache for reut packages
- Runs `composer global update`
- Verifies the new version

---

## Project Setup

### Initialize a New Project

Run `Reut init` anywhere to create a new project:

```bash
Reut init
```

You'll be prompted for:
- **Project name** (default: `myproject`)
- **Database name** (default: `test_db`)
- **Database username** (default: `root`)
- **Database password** (optional)
- **Secret key** (default: auto-generated) - used for JWT tokens
- **Enable authentication** (default: yes)

### Install Dependencies

Navigate to your project and install Composer dependencies:

```bash
cd myproject
composer install
```

---

## Project Structure

After running `Reut init`, your project will have this structure:

```
myproject/
├── .env                  # Environment variables (DB credentials, secrets)
├── auth.php              # Authentication configuration
├── composer.json         # Dependencies and autoload mappings
├── config.php            # Database configuration loader
├── index.php             # Application entry point (Slim app)
├── manage.php            # CLI command handler
├── devserver/            # Development server files (auto-generated)
│   └── router.php        # Dev server router (created on `Reut dev`)
├── models/               # Model classes (database tables)
│   └── UsersTable.php    # Example: Users model
├── routers/              # Route definitions
│   ├── routes.php        # Main routes file (registers all routers)
│   └── UsersRouter.php   # Example: Users CRUD routes
├── uploads/              # File uploads directory (auto-created)
└── viewer/               # Schema viewer files (auto-generated)
    ├── index.php         # Viewer UI (created on `Reut view`)
    ├── router.php        # Viewer router
    └── assets/
        └── style.css     # Viewer styles
```

### Key Files

| File | Purpose |
|------|---------|
| `.env` | Environment variables (DB credentials, feature flags) |
| `config.php` | Loads `.env` and creates `$config` array for database |
| `auth.php` | JWT authentication settings (table, fields, expiry) |
| `index.php` | Slim application setup, middleware, routes |
| `manage.php` | Entry point for CLI commands (`php manage.php <cmd>`) |
| `routers/routes.php` | Main routing file, registers all routers |

### Autoload Mappings

The `composer.json` includes PSR-4 autoload mappings:

```json
{
    "autoload": {
        "psr-4": {
            "Reut\\Routers\\": "routers/",
            "Reut\\Models\\": "models/"
        }
    }
}
```

---

## Creating Models

### Generate a Model

Create a new model class:

```bash
Reut generate:model Products
```

This generates `models/ProductsTable.php` with a basic structure.

### Available Column Types

```php
use Reut\DB\Types\Integer;
use Reut\DB\Types\Varchar;
use Reut\DB\Types\Text;
use Reut\DB\Types\Boolean;
use Reut\DB\Types\Decimal;
use Reut\DB\Types\Date;
use Reut\DB\Types\DateTimeType;
use Reut\DB\Types\Timestamp;
use Reut\DB\Types\Json;
use Reut\DB\Types\Blob;
use Reut\DB\Types\EnumType;
use Reut\DB\Types\BigInteger;
use Reut\DB\Types\SmallInteger;
use Reut\DB\Types\TinyInteger;
use Reut\DB\Types\FloatType;
use Reut\DB\Types\DoubleType;
```

### Define Columns

```php
// Primary key (auto-increment)
$this->addColumn('id', new Integer(false, true, true, null));

// String column (length, nullable)
$this->addColumn('name', new Varchar(255, false));

// Text column (nullable)
$this->addColumn('description', new Text(true));

// Boolean with default
$this->addColumn('is_active', new Boolean(false, true));

// Decimal (precision, scale, nullable, default)
$this->addColumn('price', new Decimal(10, 2, false, 0.00));

// Timestamps
$this->addColumn('created_at', new Timestamp(false, true));
$this->addColumn('updated_at', new Timestamp(true, false, true));

// JSON column
$this->addColumn('metadata', new Json(true));
```

### Define Foreign Keys

```php
// Simple foreign key
$this->addForeignKey('category_id', 'Categories');

// With custom options
$this->addForeignKey(
    'author_id',
    'Users',
    'id',           // referenced column
    'CASCADE',      // ON DELETE
    'CASCADE'       // ON UPDATE
);
```

---

## Database Migrations

### Create/Migrate Tables

```bash
Reut create
# or
Reut migrate
```

Both commands:
- Create the database if it doesn't exist
- Create tables for all models
- Handle relationship ordering (parent tables first)
- Record migrations in the `migrations` table

### Check Migration Status

```bash
Reut status
```

Shows applied migrations and pending changes.

### Sync Schema (Destructive)

```bash
Reut sync
```

**Warning:** This will:
- Add missing columns from models
- Drop columns that exist in DB but not in models
- Prompt to drop orphan tables

---

## Generating Routes

### Auto-Generate CRUD Routes

```bash
Reut generate:routes
```

Creates router files in `routers/` with endpoints:
- `GET /{model}/all` - List all (paginated)
- `GET /{model}/find/{id}` - Get single record
- `POST /{model}/add` - Create new
- `PUT /{model}/update/{id}` - Update
- `DELETE /{model}/delete/{id}` - Delete

---

## Custom Routes

### Using ReuteRoute

```php
<?php
namespace Reut\Routers;

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Reut\Auth\NoAuth;
use Reut\Router\ReuteRoute;

class CustomRouter extends NoAuth
{
    protected $config;

    public function __construct(App $app, array $config)
    {
        $this->config = $config;
        parent::__construct($app);
    }

    protected function genRoutes()
    {
        $routes = ReuteRoute::use($this->app);

        // Standalone route
        $routes->get('/ping', function (Request $request, Response $response) {
            $response->getBody()->write(json_encode(['pong' => true]));
            return $response->withHeader('Content-Type', 'application/json');
        }, 'Health check');

        // Grouped routes
        $routes->group('/api', 'API', function ($group) {
            $group->get('/status', function ($req, $res) {
                // Handler
            }, 'API status');

            $group->post('/action', function ($req, $res) {
                // Handler
            }, 'Perform action', true); // true = requires auth
        });
    }
}
```

### Authentication in Routes

Extend `Auth` instead of `NoAuth` to require JWT for all routes:

```php
use Reut\Auth\Auth;

class SecureRouter extends Auth
{
    // All routes require authentication
}
```

---

## Authentication

### Built-in Auth Endpoints

When `REUT_AUTH_ENABLED=true`, these endpoints are available:

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/auth/register` | POST | Register new user |
| `/auth/login` | POST | Login, returns JWT token |
| `/auth/refresh` | POST | Refresh access token |
| `/auth/logout` | POST | Revoke refresh token |

### Configuration (auth.php)

```php
return [
    'table' => 'users',
    'username_field' => 'email',
    'password_field' => 'password',
    'access_token_expiry' => 3600,      // 1 hour
    'refresh_token_expiry' => 604800,   // 7 days
    'auto_create_table' => true,
];
```

### Using JWT in Requests

```
Authorization: Bearer <your-jwt-token>
```

### Generate Tokens Manually

```php
use Reut\Middleware\JwtAuth;

$auth = new JwtAuth($config);
$token = $auth->generateToken($userId, 3600);
$refreshToken = $auth->generateRefreshToken($userId);
```

---

## API Documentation

### Access Docs

```
http://localhost:9000/docs
http://localhost:9000/docs?format=json
```

Shows all registered endpoints with methods, paths, descriptions, and auth requirements.

### Disable in Production

```env
REUT_DOCS_ENABLED=false
```

---

## Schema Viewer

### Access Schema Viewer

During development, view your database schema at:

```
http://localhost:9000/schema
http://localhost:9000/schema?format=json
```

Features:
- Lists all models and their columns
- Shows column types, primary keys, foreign keys
- Indicates relationships and constraints
- Dark/light mode toggle
- Search functionality

### Disable in Production

```env
REUT_SCHEMA_ENABLED=false
```

### Standalone Viewer

Run the schema viewer as a separate server:

```bash
Reut view --port=8080
```

---

## Schema Inspection

### Inspect Tables

Preview database schema and sync to models:

```bash
# Interactive selection
Reut inspect

# Specific table
Reut inspect --table=users

# All tables
Reut inspect --all

# Auto-apply changes
Reut inspect --table=users --apply
```

---

## Development Workflow

### Quick Start

```bash
# 1. Create project
Reut init

# 2. Install dependencies
cd myproject && composer install

# 3. Create a model
Reut generate:model Products

# 4. Edit model (add columns)
# Edit models/ProductsTable.php

# 5. Run migrations
Reut migrate

# 6. Generate routes
Reut generate:routes

# 7. Start dev server
Reut dev --port=9000

# 8. View docs
open http://localhost:9000/docs

# 9. View schema
open http://localhost:9000/schema
```

---

## CLI Commands

| Command | Description |
|---------|-------------|
| `Reut init` | Initialize a new project |
| `Reut create` | Create tables from models |
| `Reut migrate` | Apply migrations (same as create) |
| `Reut status` | Show migration status |
| `Reut sync` | Sync schema (may drop columns) |
| `Reut generate:model <Name>` | Generate a model class |
| `Reut generate:routes` | Generate CRUD routes |
| `Reut inspect` | Inspect and sync table schema |
| `Reut dev [--port=9000]` | Start development server |
| `Reut view [--port=8080]` | Start standalone schema viewer |
| `Reut update` | Update CLI to latest version |
| `Reut -v` / `Reut version` | Show CLI version |
| `Reut -h` / `Reut help` | Show help |

---

## Environment Variables

### Required

```env
DB_USERNAME=root
DB_PASSWORD=your-password
DB_NAME=my_database
SECRET_KEY=your-jwt-secret-key
```

### Optional Feature Flags

```env
# Enable/disable built-in features (all default to true)
REUT_AUTH_ENABLED=true      # Authentication endpoints
REUT_DOCS_ENABLED=true      # /docs endpoint
REUT_SCHEMA_ENABLED=true    # /schema endpoint
```

### Production Settings

```env
REUT_AUTH_ENABLED=true
REUT_DOCS_ENABLED=false
REUT_SCHEMA_ENABLED=false
```

---

## Examples

### Complete Model

```php
<?php
declare(strict_types=1);

namespace Reut\Models;

use Reut\DB\DataBase;
use Reut\DB\Types\Integer;
use Reut\DB\Types\Varchar;
use Reut\DB\Types\Text;
use Reut\DB\Types\Decimal;
use Reut\DB\Types\Boolean;
use Reut\DB\Types\Timestamp;

class ProductsTable extends DataBase
{
    public function __construct(array $config)
    {
        parent::__construct(
            $config,
            [],
            'products',     // Table name
            false,          // Has relationships
            0,              // Relationship count
            ['image'],      // File upload fields
            []              // Disabled routes
        );

        $this->addColumn('id', new Integer(false, true, true, null));
        $this->addColumn('name', new Varchar(255, false));
        $this->addColumn('description', new Text(true));
        $this->addColumn('price', new Decimal(10, 2, false, 0.00));
        $this->addColumn('stock', new Integer(false, false, false, 0));
        $this->addColumn('is_active', new Boolean(false, true));
        $this->addColumn('image', new Varchar(255, true));
        $this->addColumn('created_at', new Timestamp(false, true));
        $this->addColumn('updated_at', new Timestamp(true, false, true));

        // Foreign key
        $this->addColumn('category_id', new Integer(true));
        $this->addForeignKey('category_id', 'categories', 'id', 'SET NULL', 'CASCADE');
    }
}
```

### Custom Router with Auth

```php
<?php
declare(strict_types=1);

namespace Reut\Routers;

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Reut\Auth\Auth;
use Reut\Router\ReuteRoute;
use Reut\Models\OrdersTable;

class OrdersRouter extends Auth
{
    protected $config;

    public function __construct(App $app, array $config)
    {
        $this->config = $config;
        parent::__construct($app, $config);
    }

    protected function genRoutes()
    {
        $routes = ReuteRoute::use($this->app);
        $orders = new OrdersTable($this->config);

        $routes->group('/orders', 'Orders', function ($group) use ($orders) {
            
            $group->get('/my', function (Request $req, Response $res) use ($orders) {
                $userId = $req->getAttribute('userId');
                $data = $orders->search(['user_id' => $userId])->results;
                $res->getBody()->write(json_encode($data));
                return $res->withHeader('Content-Type', 'application/json');
            }, 'Get my orders', true);

            $group->post('/create', function (Request $req, Response $res) use ($orders) {
                $userId = $req->getAttribute('userId');
                $input = $req->getParsedBody();
                $input['user_id'] = $userId;
                $result = $orders->addOne($input);
                $res->getBody()->write(json_encode(['success' => $result]));
                return $res->withHeader('Content-Type', 'application/json');
            }, 'Create order', true);
        });
    }
}
```

---

## Troubleshooting

### Common Issues

**"Command not found"**
- Ensure Composer's `vendor/bin` is in your PATH
- Reload shell after adding to PATH

**"Database connection failed"**
- Check `.env` credentials
- Verify MySQL is running
- Ensure database exists

**"Class not found"**
- Run `composer install`
- Run `composer dump-autoload`
- Check namespace matches file location

**Routes not working**
- Run `Reut generate:routes`
- Check `routers/routes.php` includes your router
- Verify server is running (`Reut dev`)

---

## Resources

- **GitHub:** [https://github.com/m4rcTr3y/Reut-Cli](https://github.com/m4rcTr3y/Reut-Cli)
- **Core Package:** [https://github.com/m4rcTr3y/reut_core](https://github.com/m4rcTr3y/reut_core)

For issues or contributions, visit the GitHub repository.

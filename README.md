# REUT Backend Framework
REUT is a lightweight PHP framework that streamlines web development with intuitive routing, database management, and authentication.

Built on Slim PHP for routing, REUT uses JWT (JSON Web Tokens) for secure authentication and introduces a model-based approach to database interaction—define your data structure in a PHP class, and REUT automatically generates CRUD APIs and manages tables for you.

## Features

- **Slim PHP Routing**: Fast, flexible routing powered by Slim.
- **Model-Based Database Management**: Define tables as PHP classes in the `models` directory—no manual SQL required.
- **Automatic CRUD API**: Default CRUD endpoints for each model.
- **Built-in Authentication**: Ready-to-use login, register, refresh, and logout endpoints with JWT tokens (can be disabled via `REUT_AUTH_ENABLED=false`).
- **File Upload Handling**: Manages file uploads defined in model fields.
- **Customizable Routes**: Add custom routes in the `routers` directory, with optional authentication middleware.
- **Runtime API Docs**: Built-in `/docs` endpoint (HTML or JSON) lists every registered route and can be disabled via `REUT_DOCS_ENABLED=false`.
- **Configurable Setup**: Set database connection details in `.env` or `config.php`.

## Installation

### Prerequisites

- **PHP**: 7.4 or higher
- **Composer**: [getcomposer.org](https://getcomposer.org)
- **Git**: (optional)

### 1. Install the REUT CLI Tool

Install globally via Composer:

```bash
composer global require m4rc/reut_cli
```

### 2. Add Composer’s `vendor/bin` to Your PATH

#### Linux/macOS

Edit your shell config (e.g., `~/.bashrc`):

```bash
export PATH="$HOME/.composer/vendor/bin:$PATH"
```

Reload your shell:

```bash
source ~/.bashrc
```

Verify installation:

```bash
Reut -v
```

#### Windows

- Edit your user `Path` variable in Environment Variables.
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

> **Note:** Current version (`v1.0.4`). See [Packagist](https://packagist.org/packages/m4rc/reut_cli).

### 3. Initialize a New REUT Project

Create a new project:

```bash
Reut init
```

You’ll be prompted for:

- Project name (default: `myproject`)
- Database type (`mysql` or `postgresql`)
- Database name (default: `test_db`)
- Database username (default: `root`)
- Database password (optional)
- Secret key (default: `12345678`)

This sets up your project directory with all necessary files.

### 4. Set Up Your Project

Navigate to your project:

```bash
cd myproject
```

Install dependencies:

```bash
composer install
```

Generate models or resources:

```bash
Reut manage.php generate:model Users
```
## Usage

- **Initialize a project** (run anywhere):  
    ```bash
    Reut init
    ```

- **Project directory commands** (run inside your project folder):  
    ```bash
    Reut create                # Alias of migrate; ensures tables exist from models
    Reut migrate               # Apply migrations from model definitions to the database
    Reut sync                  # Reconcile existing tables with models (may drop extra columns)
    Reut status                # Check for pending migrations in models
    Reut generate:routes       # Generate routes for each model into the route/ folder
    Reut generate:model Users  # Generate a model class (replace 'Users' with your model name)
    Reut dev --port=9000       # Start the built-in PHP dev server (host defaults to 0.0.0.0)
    Reut view --port=8088      # Start the HTML schema viewer (optional host/port flags)
    Reut inspect --table=users # Inspect DB schema and sync model definitions (use --all/--apply)
    Reut -v                    # Show CLI version
    Reut -h                    # Show help message
    ```

- **Global CLI commands** (if installed globally):  
    ```bash
    Reut <command>
    Reut -v
    Reut help
    ```

### API Docs Endpoint

- Visit `/docs` in your running project to see a generated list of all registered routes (CRUD + custom). Append `?format=json` for JSON output.
- Set `REUT_DOCS_ENABLED=false` in `.env` to skip registering the docs endpoint (recommended for production).

### Custom Routes & Documentation

- Import `Reut\Router\ReuteRoute` inside your router classes; it wraps Slim’s router and auto-records metadata for `/docs`.
    ```php
    use Reut\Router\ReuteRoute;

    $routes = ReuteRoute::use($this->app);

    // Grouped endpoints
    $routes->group('/invoices', 'Invoices', function (ReuteRoute $group) {
        $group->get('/all', $listHandler, 'List invoices');
        $group->post('/pay/{id}', $payHandler, 'Pay invoice', true);
    });

    // Standalone route
    $routes->get('/health', $healthHandler, 'Service healthcheck');
    ```
- Generated routers already use `ReuteRoute`, so CRUD endpoints appear automatically. Custom routes simply adopt the helper to stay documented.

### Built-in Authentication

- REUT provides ready-to-use authentication endpoints when enabled (default during `Reut init`):
  - `POST /auth/login` - Login with email/username and password, returns JWT token and refresh token
  - `POST /auth/register` - Register new user account
  - `POST /auth/refresh` - Refresh JWT token using refresh token
  - `POST /auth/logout` - Revoke tokens

- Configuration via `auth.php` (generated during init):
  ```php
  // Customize table name, field mappings, token expiry
  $authConfig = [
      'table' => 'Users',  // or your custom table
      'fields' => [
          'identifier' => 'email',  // 'email' or 'username'
          'password' => 'password',
      ],
      'token_expiry' => 3600,  // seconds
  ];
  ```

- Auto-creates default `Users` table if no custom auth model exists (when `auto_create_table` is enabled).

- Disable authentication endpoints by setting `REUT_AUTH_ENABLED=false` in `.env`.

- Extend `Reut\Auth\AuthController` to customize validation, password hashing, or response formatting:
  ```php
  class CustomAuthController extends AuthController {
      protected function validateLogin(array $credentials): ?array {
          // Add custom checks (e.g., check if user is active)
          $user = parent::validateLogin($credentials);
          if ($user && $user['is_active'] === false) {
              return null; // Reject inactive users
          }
          return $user;
      }
  }
  ```

### Defining Relationships

- Define foreign keys directly inside your model classes using `addForeignKey`, e.g.:
    ```php
    $this->addForeignKey('user_id', 'Users');
    ```
- Each call automatically marks the table as relational and contributes to the relationship count so migrations know to create parent tables first.

- The viewer command copies the `/viewer` folder into new projects and serves it with the built-in PHP server so you can inspect tables visually.
- Use `Reut dev --port=9000 --host=0.0.0.0` (or `php manage.php dev`) to spin up a PHP dev server with the bundled router that falls back to `index.php`.



## Troubleshooting

- **Command not found**: Ensure Composer’s `vendor/bin` is in your PATH.
- **Stability error**: Use `m4rc/reut_cli:dev-main` or check Packagist for updates.
- **Missing files**: Ensure your project includes required templates and source files. Contact the 
- APP_ENV=development   # Set to "production" on live servers to hide detailed stack traces and enable production optimizations (caching, tighter error logging). Never run with debug mode enabled in public environments.

maintainer if issues persist.
- **Runtime errors**: Run commands with `--verbose` for more details.

## Contributing

Contributions welcome! Submit issues or pull requests at [GitHub](https://github.com/m4rcTr3y/Reut-Cli).

## License

MIT License.

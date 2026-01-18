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
- **Advanced Migration System** (v1.3.0+):
  - **Dry-run mode**: Preview migrations before executing (`--dry-run` flag)
  - **Rollback support**: Rollback migrations by batch or specific migration
  - **Migration validation**: Validate SQL syntax and detect conflicts before applying
  - **Export/Import**: Export and import migration history for backup or sharing
  - **Enhanced status**: JSON output, summary mode, and table-specific status checks
  - **Protected columns**: Automatic protection of common columns (`created_at`, `updated_at`, etc.)

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

Edit your shell config:
- **Bash/Zsh**: `~/.bashrc` or `~/.zshrc`
- **Fish**: `~/.config/fish/config.fish`

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

> **Note:** Current version (`v1.4.2`). See [Packagist](https://packagist.org/packages/m4rc/reut_cli).

### Updating REUT CLI

Update to the latest version with a single command:

```bash
Reut update
```

This automatically clears the composer cache and updates to the latest version.
>>>>>>> 05115002be09fb060fda8bfaf23480d356f0937c

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
    # Migration Commands
    Reut create                # Alias of migrate; ensures tables exist from models
    Reut migrate               # Apply migrations from model definitions to the database
    Reut migrate --dry-run     # Preview migrations without executing (v1.3.0+)
    Reut sync                  # Reconcile existing tables with models (may drop extra columns)
    Reut sync --dry-run        # Preview sync changes without executing (v1.3.0+)
    Reut status                # Check for pending migrations in models
    Reut status --json         # Output migration status as JSON (v1.3.0+)
    Reut status --summary      # Show summary of migration status (v1.3.0+)
    Reut status --table=users  # Check status for specific table (v1.3.0+)
    Reut rollback              # Rollback last batch of migrations (v1.3.0+)
    Reut rollback --batch=2    # Rollback specific batch number (v1.3.0+)
    Reut rollback --migration=name # Rollback specific migration (v1.3.0+)
    Reut rollback --dry-run    # Preview rollback without executing (v1.3.0+)
    Reut validate-migrations   # Validate migration SQL syntax and check conflicts (v1.3.0+)
    Reut export-migrations     # Export migration history to JSON/SQL file (v1.3.0+)
    Reut import-migrations file.json # Import migration history from JSON/SQL file (v1.3.0+)
    
    # Model & Route Generation
    Reut generate:routes       # Generate routes for each model into the route/ folder
    Reut generate:model Users  # Generate a model class (replace 'Users' with your model name)
    
    # Development & Inspection
    Reut dev --port=9000       # Start the built-in PHP dev server (host defaults to 0.0.0.0)
    Reut view --port=8088      # Start the HTML schema viewer (optional host/port flags)
    Reut inspect --table=users # Inspect DB schema and sync model definitions (use --all/--apply)
    
    # Utility
    Reut -v                    # Show CLI version
    Reut -h                    # Show help message
    ```

- **Global CLI commands** (if installed globally):  
    ```bash
    Reut <command>
    Reut update              # Update CLI to latest version
    Reut -v                  # Show version
    Reut help                # Show help
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

### Migration Commands (v1.3.0+)

REUT CLI provides comprehensive migration management:

#### Basic Migration Commands

- **`migrate`** / **`create`**: Apply migrations from models to database. Creates tables, adds columns, and respects protected columns.
- **`sync`**: Aggressively reconcile database with models. Can drop columns and orphan tables (use with caution).
- **`status`**: Check pending migrations without applying them. Supports `--json`, `--summary`, and `--table` options.

#### Advanced Migration Features

- **`rollback`**: Undo migrations
  ```bash
  Reut rollback              # Rollback last batch
  Reut rollback --batch=2     # Rollback specific batch
  Reut rollback --migration=create_users_table_20240101120000  # Rollback specific migration
  Reut rollback --dry-run    # Preview rollback
  ```

- **`validate-migrations`**: Check migration SQL syntax and detect conflicts
  ```bash
  Reut validate-migrations
  ```

- **`export-migrations`**: Export migration history to JSON or SQL
  ```bash
  Reut export-migrations                    # Export to migrations.json
  Reut export-migrations --format=sql      # Export to migrations.sql
  ```

- **`import-migrations`**: Import migration history from JSON or SQL file
  ```bash
  Reut import-migrations migrations.json
  Reut import-migrations migrations.sql
  ```

- **Dry-run mode**: Preview changes before executing
  ```bash
  Reut migrate --dry-run     # Preview migrations
  Reut sync --dry-run        # Preview sync changes
  ```

- **Enhanced status**: Get detailed migration information
  ```bash
  Reut status                # Standard output
  Reut status --json         # JSON output for scripting
  Reut status --summary      # Summary view
  Reut status --table=users  # Check specific table
  ```

See [MIGRATION_COMMANDS.md](MIGRATION_COMMANDS.md) for detailed documentation on all migration commands.

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

### Disabled Routes

- Control which CRUD routes are generated for each model using the `disabledRoutes` array in the model constructor:
    ```php
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
- Route options:
  - `'all'` - Disables all CRUD routes (useful for read-only models)
  - `'find'` - Disables `GET /{model}/find/{id}`
  - `'add'` - Disables `POST /{model}/add`
  - `'update'` - Disables `PUT /{model}/update/{id}`
  - `'delete'` - Disables `DELETE /{model}/delete/{id}`
- Disabled routes are automatically excluded from route generation and shown in the schema viewer at `/schema`.

### Per-Model Authentication

- Enable authentication for specific models by setting `requiresAuth` to `true` in the model constructor:
    ```php
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
    ```
- When `requiresAuth` is `true`:
  - All CRUD endpoints for that model require a valid JWT token
  - The `Authorization: Bearer <token>` header must be included in requests
  - Auth status is displayed in the schema viewer at `/schema`
- When `requiresAuth` is `false` (default):
  - Routes are publicly accessible without authentication
- This allows fine-grained control: some models can be public while others require authentication.

- The viewer command copies the `/viewer` folder into new projects and serves it with the built-in PHP server so you can inspect tables visually.
- Use `Reut dev --port=9000 --host=0.0.0.0` (or `php manage.php dev`) to spin up a PHP dev server with the bundled router that falls back to `index.php`.



## Troubleshooting

- **Command not found**: Ensure Composer's `vendor/bin` is in your PATH.
- **Stability error**: Use `m4rc/reut_cli:dev-main` or check Packagist for updates.
- **Missing files**: Ensure your project includes required templates and source files. Contact the maintainer if issues persist.
- **Runtime errors**: Run commands with `--verbose` for more details.
- **Environment configuration**: Set `APP_ENV=development` for development or `APP_ENV=production` on live servers to hide detailed stack traces and enable production optimizations (caching, tighter error logging). Never run with debug mode enabled in public environments.

## Contributing

Contributions welcome! Submit issues or pull requests at [GitHub](https://github.com/m4rcTr3y/Reut-Cli).

## License

MIT License.

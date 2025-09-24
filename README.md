# REUT Backend Framework
REUT is a lightweight PHP framework that streamlines web development with intuitive routing, database management, and authentication.

Built on Slim PHP for routing, REUT uses JWT (JSON Web Tokens) for secure authentication and introduces a model-based approach to database interaction—define your data structure in a PHP class, and REUT automatically generates CRUD APIs and manages tables for you.

## Features

- **Slim PHP Routing**: Fast, flexible routing powered by Slim.
- **Model-Based Database Management**: Define tables as PHP classes in the `models` directory—no manual SQL required.
- **Automatic CRUD API**: Default CRUD endpoints for each model.
- **File Upload Handling**: Manages file uploads defined in model fields.
- **Customizable Routes**: Add custom routes in the `routers` directory, with optional authentication middleware.
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

> **Note:** Stable version (`v1.0.0`) coming soon. See [Packagist](https://packagist.org/packages/m4rc/reut_cli).

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
php manage.php generate:model Users
# Or, if CLI is installed globally:
Reut manage.php generate:model Users
```
## Usage

- **Initialize a project** (run anywhere):  
    ```bash
    Reut init
    ```

- **Project directory commands** (run inside your project folder):  
    ```bash
    Reut create                # Initial setup or sync models to database tables
    Reut status                # Check for pending migrations in models
    Reut generate:routes       # Generate routes for each model into the route/ folder
    Reut generate:model Users  # Generate a model class (replace 'Users' with your model name)
    Reut migrate               # Apply migrations from model changes to the database
    Reut -v                    # Show CLI version
    Reut -h                    # Show help message
    ```
    or
    ```bash
    php manage.php create
    php manage.php status
    php manage.php generate:routes
    php manage.php generate:model Users
    php manage.php migrate
    php manage.php -v
    php manage.php -h
    ```

- **Global CLI commands** (if installed globally):  
    ```bash
    Reut <command>
    Reut -v
    Reut help
    ```

## Troubleshooting

- **Command not found**: Ensure Composer’s `vendor/bin` is in your PATH.
- **Stability error**: Use `m4rc/reut_cli:dev-main` or check Packagist for updates.
- **Missing files**: Ensure your project includes required templates and source files. Contact the maintainer if issues persist.
- **Runtime errors**: Run commands with `--verbose` for more details.

## Contributing

Contributions welcome! Submit issues or pull requests at [GitHub](https://github.com/m4rcTr3y/Reut-Cli).

## License

MIT License.

# Fixes Implemented

**Version: v1.2.0** (December 2024)

## Security Features Implementation (v1.2.0)

### File Type Validation
- Added per-field file type validation via `fileFieldTypes` property in `DataBase` class
- Implemented `validateFileType()` method with extension whitelist and MIME type validation
- Added dangerous extension blocklist (php, exe, sh, bat, cmd, com, pif, scr, vbs, js, jsp, asp, aspx) that is always rejected
- File size limit enforcement (5MB default)
- Secure file permissions (0640) for uploaded files
- Updated `handleFileUploads()` method to use validation before file operations

### Required Field Validation
- Added configurable `strictRequiredValidation` property to `DataBase` class
- Implemented `validateRequiredFields()` method that respects nullable column definitions
- Validation applied to `addOne()`, `addMany()`, `update()`, `updateMany()`, and `findOne()` methods
- Controlled via `REUT_STRICT_REQUIRED_VALIDATION` environment variable (defaults to `false`)
- Improved environment variable handling using `filter_var()` for proper boolean conversion
- Added `isNullable()` method to `ColumnType` base class for proper nullable checking

### SQL Injection Prevention
- Added `validateIdentifier()` method to validate all table and column names
- Applied identifier validation to all CRUD methods (`findOne`, `update`, `delete`, `search`, `getTableSchema`, `getColumns`, `dropTable`, etc.)
- Prevents SQL injection via identifier manipulation in queries
- Validates SQL identifier format (alphanumeric and underscores only)

### Rate Limiting Middleware
- Created `RateLimitMiddleware` class for IP-based rate limiting
- Configurable via environment variables:
  - `REUT_RATE_LIMIT_ENABLED` (default: `true`)
  - `REUT_RATE_LIMIT_MAX_REQUESTS` (default: `100`)
  - `REUT_RATE_LIMIT_WINDOW_SECONDS` (default: `60`)
- File-based storage with automatic cleanup of old rate limit records
- Proper HTTP 429 responses with `X-RateLimit-Limit`, `X-RateLimit-Window`, and `Retry-After` headers
- Integrated into `index.php` template for automatic application to all projects

### CSRF Protection Middleware
- Created `CsrfMiddleware` class for session-based CSRF token validation
- Configurable via environment variables:
  - `REUT_CSRF_ENABLED` (default: `true`)
  - `REUT_CSRF_TOKEN_NAME` (default: `csrf_token`)
  - `REUT_CSRF_TOKEN_LENGTH` (default: `32`)
  - `REUT_CSRF_TOKEN_LIFETIME` (default: `3600` seconds)
- Validates tokens for POST/PUT/PATCH/DELETE requests
- Token attachment to responses for easy frontend integration
- Timing-safe token comparison using `hash_equals()`
- Integrated into `index.php` template for automatic application to all projects

### Configuration Updates
- Updated `.env` file generation in `initCommand` to include all new security settings
- Updated model template (`createModels.php`) to include new constructor parameters:
  - `fileFieldTypes` array for per-field file type restrictions
  - `strictRequiredValidation` boolean for required field validation control
- Updated `index.php` templates (both skeleton and generated) to include security middlewares
- All security features enabled by default with sensible defaults

### Model Template Updates
- Updated `createModels.php` template to show usage of new security features
- Added example comments demonstrating file type validation and required field validation
- Constructor signature updated with new parameters and clear documentation

### Test Coverage
- Created comprehensive test suite in `tests/new-feature-tests/v1.0/`:
  - `NewSecurityFeaturesTest.php` - 13 tests covering all new security features
  - `ProjectScaffoldingTest.php` - 12 tests covering project initialization and structure
  - `ReutCliCommandTest.php` - 11 tests covering CLI command functionality
- Updated `tests/bootstrap.php` to prioritize `packages/core` over `vendor/reut/core` for testing latest features
- All tests passing with 153 assertions verified

## Database schema handling
- `templates/config/db/DataBase.php` now stores the constructor `$columns` argument in `$this->columns` (and removes the unused `$schema` property) so generated models can register fields without triggering null access errors.

## Migration batching
- `templates/config/migrate.php` correctly derives the next batch number by reading the first row from `sqlQuery()` results, preventing every migration run from reusing batch `1`.

## Router generation
- `templates/config/createRoutes.php` generates `/find/{id}` routes (matching the handler’s expectation) and rewrites router files instead of appending duplicate class definitions when commands are re-run.

## JWT middleware alignment
- `templates/config/middleware/JwtAuth.php` now returns proper `Content-Type: application/json` headers on auth errors.
- `templates/config/middleware/authMiddleware.php` loads the secret key from the environment so it validates the same tokens produced by `JwtAuth`.

## Migration reliability (follow-up)
- Added `DataBase::execute()` and updated `templates/config/migrate.php` to use it for CREATE/ALTER/INSERT statements so migration records persist reliably.
- Escaped table/column names (`preg_quote`) when matching stored migration names, preventing regex mismatches when identifiers contain special characters.

## Protected columns
- `DataBase` exposes a `protectedColumns` array (defaulting to `created_at` and `updated_at`), and `templates/config/migrate.php` now skips dropping those columns during schema reconciliation to avoid deleting manually managed fields.

## Inspect command
- Added the `Reut inspect` workflow (wired through `bin/Reut`, `DatabaseCreator`, and the new `config/inspect.php`) to preview database table schemas, interactively confirm updates, and rewrite model column definitions with `@reut-columns` markers.
- Documentation now lists `Reut inspect` under available CLI commands.

## Command naming cleanup
- `create` and `migrate` now both invoke `migrate.php`, while a new `sync` command (wired through `bin/Reut`, `DatabaseCreator`, and documented in `README.md`) covers the aggressive schema reconciliation that previously lived under `migrate`, explicitly warning that it may drop columns not defined in models.

## API documentation endpoint
- Added `Reut\Router\ReuteRoute` facade that abstracts Slim routing and automatically documents endpoints for the built-in `/docs` API endpoint.
- Generated routers now use `ReuteRoute::use($app)->group()` and `->get/post/put/delete()` methods, which register routes and capture metadata (method, path, description, auth requirement) for documentation.
- Custom routers can import `ReuteRoute` to register standalone or grouped routes while keeping docs in sync.
- Added `DocsRegistry` to collect endpoint metadata and `DocsController` to render JSON or HTML at `/docs`.
- Documentation endpoint can be disabled in production via `REUT_DOCS_ENABLED=false` environment variable (defaults to `true`).
- Router template (`createRoutes.php`) updated to generate code using the new facade, ensuring all auto-generated CRUD endpoints appear in `/docs` automatically.

## Built-in authentication system
- Added `Reut\Auth\AuthRouter` providing built-in authentication endpoints: `POST /auth/login`, `POST /auth/register`, `POST /auth/refresh`, and `POST /auth/logout`.
- Created `Reut\Auth\AuthController` base class for extensibility - users can extend and override validation, password hashing, and response preparation methods.
- Auto-generates `auth.php` configuration file during `Reut init` (alongside `config.php`) with configurable table name, field mappings, and token expiry settings.
- Automatically creates default `Users` table with email/username, password, and timestamps if `auto_create_table` is enabled and no custom auth model exists.
- Auth router auto-registers in `routes.php` when `REUT_AUTH_ENABLED=true` (defaults to enabled during init, can be disabled via env).
- Password hashing uses PHP's `password_hash()` / `password_verify()` for secure storage.
- JWT token generation and refresh token management integrated with existing `JwtAuth` middleware.
- Updated `bin/Reut` init command to prompt for authentication enablement and generate `auth.php` config file.

## PSR-4 Autoloading Fixes (v1.1.2 - v1.1.5)
- **Directory casing fixes for Linux compatibility**: Renamed all lowercase directories to match PSR-4 namespace conventions (case-sensitive on Linux):
  - `db/` → `DB/` (namespace `Reut\DB`)
  - `db/types/` → `DB/Types/` (namespace `Reut\DB\Types`)
  - `db/exceptions/` → `DB/Exceptions/` (namespace `Reut\DB\Exceptions`)
  - `auth/` → `Auth/` (namespace `Reut\Auth`)
  - `middleware/` → `Middleware/` (namespace `Reut\Middleware`)
  - `router/` → `Router/` (namespace `Reut\Router`)
  - `utils/` → `Utils/` (namespace `Reut\Utils`)
- **Fixed DatabaseCreator namespace**: Changed from `Reut\DB\Creator` to `Reut\DB` to match the actual file location.
- **Non-PHP file filtering**: Added filter to exclude `.gitkeep` and other non-PHP files from model directory scans in `checkmigration.php`, `migrate.php`, and `update.php`.
- **Project autoload mappings**: Added `Reut\Routers\` and `Reut\Models\` PSR-4 mappings to skeleton `composer.json` for proper autoloading of user-defined routers and models.

## Schema Viewer Endpoint (v1.1.6)
- **New `/schema` endpoint**: Added `Reut\Router\SchemaController` to render database schema viewer inline during development (like `/docs`).
- Displays all model tables with columns, types, primary keys, foreign keys, relationships, and modification timestamps.
- Supports JSON output via `?format=json` query parameter.
- Dark/light mode toggle with persistent preference.
- **Environment control**: Enable/disable via `REUT_SCHEMA_ENABLED` env variable (defaults to `true` for development, set to `false` in production).
- The `Reut view` command still works for standalone viewer with live refresh, but `/schema` is now available during normal dev server operation.

## CLI Version Update
- Updated CLI version to 1.1.6 in `DatabaseCreator.php`.

## Self-Update Command (v1.1.9)
- **New `Reut update` command**: Added self-update functionality to easily update the CLI tool.
- Automatically clears composer cache for reut-related packages (fixes tag caching issues).
- Runs `composer global update m4rc/reut_cli` with `--no-cache` flag.
- Shows current version before update and verifies new version after update.
- Usage: Simply run `Reut update` from anywhere to update to the latest version.


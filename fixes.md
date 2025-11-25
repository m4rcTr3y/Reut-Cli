# Fixes Implemented

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


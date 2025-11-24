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


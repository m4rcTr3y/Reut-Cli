# New Feature Tests

This directory contains tests for new security features and project scaffolding functionality.

## Version Structure

Tests are organized by version to track when features were introduced:

- **v1.0/** - Initial security features and scaffolding tests (December 2024)
- **v1.1/** - Migration fixes and improvements
- **v1.2/** - CLI commands and migration features
- **v1.3/** - Disabled routes and per-model authentication
- **v1.4/** - Auth setup enhancement (automatic model generation, test user creation, bug fixes)

## Test Files

### v1.0/

- **NewSecurityFeaturesTest.php** - Tests for new security features:
  - File type validation per field
  - Required field validation (strict mode)
  - Rate limiting middleware
  - CSRF protection middleware
  - SQL injection prevention

- **ProjectScaffoldingTest.php** - Tests for project scaffolding:
  - Project initialization and file structure
  - Configuration file generation (.env, config.php, auth.php)
  - Security features integration
  - Middleware setup and ordering
  - Directory structure and permissions

- **ReutCliCommandTest.php** - Tests for CLI command functionality:
  - CLI version and help commands
  - Project initialization
  - Model generation (generate:model)
  - Database operations (create, migrate, status)
  - Route generation (generate:routes)
  - Configuration file loading
  - Error handling
  - Uses test database: `test_db_two` (root/root@1234)

### v1.4/

- **AuthSetupEnhancementTest.php** - Tests for authentication setup enhancement:
  - Automatic UsersTable model generation during project initialization
  - Test user credential storage in `.auth-setup.json`
  - Post-migration automatic user creation
  - AuthRouter bug fix (updated_at column definition)
  - AuthRouter preference for existing model files over auto-creation
  - Proper Timestamp column definitions
  - Uses test database: `test_db_v14` (root/root@1234)

- **CliCommandTest.php** - Tests for CLI command functionality (v1.4):
  - CLI version command (v1.4.9)
  - CORS middleware integration in generated projects
  - CORS configuration in `.env` file
  - Auth configuration file generation
  - Automatic UsersTable model generation
  - Test user credential storage
  - Post-migration user creation via migrate command
  - CORS middleware ordering
  - Uses test database: `test_db_v14_cli` (root/root@1234)

## Running Tests

Run all new feature tests:
```bash
php vendor/bin/phpunit tests/new-feature-tests/
```

Run tests for a specific version:
```bash
php vendor/bin/phpunit tests/new-feature-tests/v1.0/
```

Run a specific test file:
```bash
php vendor/bin/phpunit tests/new-feature-tests/v1.0/NewSecurityFeaturesTest.php
php vendor/bin/phpunit tests/new-feature-tests/v1.0/ProjectScaffoldingTest.php
```

## Important Notes

- **Bootstrap Priority**: The test bootstrap (`tests/bootstrap.php`) prioritizes `packages/core` over `vendor/reut/core` to ensure tests use the latest features
- **Monorepo Sync**: If tests fail with "property not found" errors, ensure `packages/core` is synced to `vendor/reut/core`:
  ```bash
  composer update reut/core
  ```
- **Test Environment**: Tests create temporary directories and clean up automatically

## Adding New Version Tests

When adding new features in the future:

1. Create a new version directory (e.g., `v1.1/`, `v2.0/`)
2. Add test files for the new features
3. Update this README with the new version and test descriptions


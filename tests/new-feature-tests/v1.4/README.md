# Auth Setup Enhancement Tests (v1.4)

This test suite covers the new authentication setup enhancement features implemented in v1.4.

## Features Tested

1. **Auth Model Generation**
   - Automatic UsersTable model generation during project initialization
   - Correct column definitions (id, email/username, password, created_at, updated_at)
   - Proper Timestamp column definitions

2. **Bug Fix: updated_at Column Definition**
   - Fixed "Invalid default value for 'updated_at'" error
   - Updated Timestamp definition from `(true, false, true)` to `(false, true, true)`
   - Ensures `NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`

3. **Test User Credential Storage**
   - `.auth-setup.json` file creation and reading
   - Storage of test user credentials during initialization

4. **Post-Migration User Creation**
   - Automatic test user creation after migrations complete
   - Integration with migrate.php

5. **AuthRouter Enhancements**
   - Preference for existing model files over auto-creation
   - Proper model file loading and instantiation
   - Auto-creation uses correct updated_at definition

## Test Databases

- **AuthSetupEnhancementTest**: `test_db_v14`
- **CliCommandTest**: `test_db_v14_cli`
- **Username**: `root`
- **Password**: `root@1234`
- **Host**: `localhost`

## Running Tests

Run all v1.4 tests:
```bash
php vendor/bin/phpunit tests/new-feature-tests/v1.4/
```

Run with testdox output:
```bash
php vendor/bin/phpunit tests/new-feature-tests/v1.4/ --testdox
```

Run a specific test file:
```bash
php vendor/bin/phpunit tests/new-feature-tests/v1.4/AuthSetupEnhancementTest.php
php vendor/bin/phpunit tests/new-feature-tests/v1.4/CliCommandTest.php
```

Run a specific test:
```bash
php vendor/bin/phpunit tests/new-feature-tests/v1.4/ --filter testName
```

## Test Cases

### 1. `testAuthModelGenerationCreatesCorrectStructure`
- Verifies auth model generation creates UsersTable with correct structure
- Checks for all required columns
- Validates Timestamp definitions

### 2. `testAuthModelInstantiationAndColumns`
- Tests that generated model can be instantiated
- Verifies all columns are defined correctly

### 3. `testAuthModelTableCreationWithCorrectUpdatedAt`
- **Critical**: Tests the bug fix for updated_at column
- Ensures table creation doesn't fail with "Invalid default value" error
- Verifies database column definition matches expected structure

### 4. `testCreateAuthUserFunction`
- Tests the createAuthUser function
- Verifies user creation with password hashing
- Checks password verification

### 5. `testAuthSetupJsonFileCreation`
- Tests `.auth-setup.json` file creation and reading
- Validates data persistence

### 6. `testAuthRouterPrefersExistingModelFiles`
- Verifies AuthRouter loads existing model files instead of auto-creating
- Ensures model file structure is preserved

### 7. `testAuthRouterAutoCreationUsesCorrectUpdatedAt`
- **Critical**: Tests that auto-created models use correct updated_at definition
- Ensures bug fix applies to auto-creation path

### 8. `testPostMigrationUserCreation`
- Tests automatic user creation after migrations
- Simulates the post-migration flow

### 9. `testAuthModelGenerationWithUsernameIdentifier`
- Tests model generation with username instead of email
- Verifies identifier field flexibility

### 10. `testAuthModelGenerationRespectsExistingFiles`
- Tests that existing model files are not overwritten
- Validates file protection

## CLI Command Tests

The `CliCommandTest.php` file tests the CLI command functionality for v1.4:

### 1. `testCliVersionCommand`
- Verifies CLI version command shows v1.4.0

### 2. `testGeneratedIndexIncludesCorsMiddleware`
- Tests that generated `index.php` includes `CorsMiddleware` import and instantiation
- Verifies CORS middleware is added before other security middlewares

### 3. `testEnvFileIncludesCorsConfig`
- Tests that `.env` file includes all CORS configuration variables
- Verifies default CORS settings are present

### 4. `testAuthConfigFileGenerated`
- Tests that `auth.php` is generated when auth is enabled
- Verifies auth configuration structure

### 5. `testUsersTableModelGenerated`
- Tests that `UsersTable` model is automatically generated
- Verifies model structure and correct `updated_at` definition

### 6. `testAuthSetupJsonCreated`
- Tests that `.auth-setup.json` is created with test user credentials
- Validates JSON structure

### 7. `testMigrateCreatesTestUser`
- Tests that `migrate` command creates test user after migrations
- Verifies user exists in database with correct password hash
- Confirms `.auth-setup.json` is deleted after user creation

### 8. `testCorsMiddlewareOrder`
- Tests that CORS middleware is added in correct order (before rate limit and CSRF)

### 9. `testCorsMiddlewareFileExists`
- Verifies that CorsMiddleware is referenced in generated project

## Important Notes

- Tests create temporary directories and clean up automatically
- Database is created and dropped for each test run
- Tests verify both file-based and auto-created model paths
- Critical tests focus on the updated_at bug fix

## Related Files

- `src/createAuthModel.php` - Auth model generator
- `src/createAuthUser.php` - User creation function
- `src/Auth/AuthRouter.php` - Authentication router (bug fix)
- `src/migrate.php` - Migration script (post-migration user creation)
- `bin/Reut` - CLI init command (enhanced with auth setup)


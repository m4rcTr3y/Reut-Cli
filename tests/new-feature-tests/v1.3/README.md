# Disabled Routes and Per-Model Authentication Tests (v1.3)

## Overview

This test suite covers the new features implemented in v1.3:
1. **Disabled Routes**: Control which CRUD routes are generated for each model
2. **Per-Model Authentication**: Enable JWT authentication on a per-model basis

## Test Coverage

### 1. Disabled Routes Functionality
- **Test**: `testDisabledRoutesAll()`
  - Verifies that `['all']` disables all CRUD routes
- **Test**: `testDisabledRoutesSpecific()`
  - Verifies that specific routes can be disabled individually ('find', 'add', 'update', 'delete')
- **Test**: `testDisabledRoutesList()`
  - Verifies that the 'all' route (list endpoint) can be disabled separately
- **Test**: `testDisabledRoutesMultiple()`
  - Verifies that multiple routes can be disabled at once
- **Test**: `testDisabledRoutesEmpty()`
  - Verifies that empty array enables all routes

### 2. Per-Model Authentication
- **Test**: `testRequiresAuthTrue()`
  - Verifies that when `requiresAuth = true`, router extends `Auth` class
- **Test**: `testRequiresAuthFalse()`
  - Verifies that when `requiresAuth = false`, router extends `NoAuth` class (default)
- **Test**: `testRequiresAuthInRouterGeneration()`
  - Verifies that route generation correctly detects and uses auth requirement

### 3. Schema Viewer Integration
- **Test**: `testSchemaViewerShowsDisabledRoutes()`
  - Verifies that disabled routes are displayed in schema viewer metadata
- **Test**: `testSchemaViewerShowsAuthStatus()`
  - Verifies that auth status is displayed in schema viewer metadata
- **Test**: `testSchemaViewerBadges()`
  - Verifies that badges are correctly displayed for disabled routes and auth status

### 4. Route Generation
- **Test**: `testRouteGenerationRespectsDisabledRoutes()`
  - Verifies that generated router files exclude disabled routes
- **Test**: `testRouteGenerationWithAuth()`
  - Verifies that router files use correct auth class based on requiresAuth

### 5. DataBase Class Properties
- **Test**: `testDataBaseRequiresAuthProperty()`
  - Verifies that `requiresAuth` property exists and is accessible
- **Test**: `testDataBaseDisabledRoutesProperty()`
  - Verifies that `disabledRoutes` property exists and is accessible

## Running the Tests

### Prerequisites

1. **Database Setup**: Tests require MySQL/MariaDB with:
   - Host: `localhost`
   - Username: `root`
   - Password: `root@1234`
   - Database: `test_db_v13` (created automatically)

2. **PHPUnit**: Ensure PHPUnit is installed via Composer:
   ```bash
   composer install
   ```

### Run All Tests

```bash
php vendor/bin/phpunit tests/new-feature-tests/v1.3/ --testdox
```

### Run Specific Test

```bash
php vendor/bin/phpunit tests/new-feature-tests/v1.3/DisabledRoutesAndAuthTest.php --filter testName
```

### Run with Coverage

```bash
php vendor/bin/phpunit tests/new-feature-tests/v1.3/ --coverage-text
```

## Test Structure

Each test follows this pattern:

1. **Setup**: Creates temporary project directory, database, and test models
2. **Execution**: Tests the disabled routes or auth functionality
3. **Assertion**: Verifies expected behavior
4. **Cleanup**: Removes temporary files and database objects

## Test Models

The test suite uses several test models:

- `TestModelWithDisabledRoutes` - Model with various disabled routes configurations
- `TestModelWithAuth` - Model with `requiresAuth = true`
- `TestModelWithoutAuth` - Model with `requiresAuth = false` (default)
- `TestModelAllDisabled` - Model with all routes disabled

## Features Tested

### Disabled Routes
- ✅ `['all']` - Disables all CRUD routes
- ✅ `['find']` - Disables GET /find/{id}
- ✅ `['add']` - Disables POST /add
- ✅ `['update']` - Disables PUT /update/{id}
- ✅ `['delete']` - Disables DELETE /delete/{id}
- ✅ Multiple routes: `['add', 'delete']`
- ✅ Empty array enables all routes

### Per-Model Authentication
- ✅ `requiresAuth = true` - Router extends `Auth` class
- ✅ `requiresAuth = false` - Router extends `NoAuth` class
- ✅ Auth status visible in schema viewer
- ✅ Correct middleware applied when auth is enabled

### Schema Viewer
- ✅ Disabled routes shown as badges
- ✅ Auth status shown as "Auth Required" badge
- ✅ Metadata includes disabledRoutes and requiresAuth

## Expected Test Results

All tests should pass with the following output:

```
Disabled Routes and Per-Model Authentication Test (v1.3)
 ✔ Test database requires auth property
 ✔ Test database disabled routes property
 ✔ Test disabled routes all
 ✔ Test disabled routes specific
 ✔ Test disabled routes list
 ✔ Test disabled routes multiple
 ✔ Test disabled routes empty
 ✔ Test requires auth true
 ✔ Test requires auth false
 ✔ Test requires auth in router generation
 ✔ Test route generation respects disabled routes
 ✔ Test route generation with auth
 ✔ Test schema viewer shows disabled routes
 ✔ Test schema viewer shows auth status
 ✔ Test schema viewer badges

Time: XX.XX seconds, Memory: XX.XX MB

OK (15 tests, XX assertions)
```

## Troubleshooting

### Database Connection Issues

If tests fail with connection errors:
1. Verify MySQL is running: `sudo systemctl status mysql`
2. Check credentials match test constants
3. Ensure user has CREATE/DROP DATABASE permissions

### Permission Issues

If tests fail with permission errors:
1. Check write permissions on `/tmp` directory
2. Verify database user has necessary privileges
3. Ensure PHP can create temporary directories

### Route Generation Issues

If route generation tests fail:
1. Verify that model files are correctly created
2. Check that router files are generated in the correct location
3. Ensure config.php exists and is valid

## Notes

- Tests are designed to be independent and can run in any order
- Each test cleans up after itself
- Temporary files are created in system temp directory
- Database operations are isolated to test database only
- Router files are generated in temporary directories

## Related Documentation

- [README.md](../../../../README.md) - Main documentation
- [EXAMPLE_MODEL.php](../../../../EXAMPLE_MODEL.php) - Example model with features


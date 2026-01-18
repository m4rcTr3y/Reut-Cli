# CLI Cleanup Plan - Remove Duplicate Core Code

## Problem
The CLI package (`Reut_CLI`) contains duplicate copies of core framework code that should be coming from the `reut/core` package dependency.

## Current Duplication

### Duplicated Directories (Should be removed):
1. **`src/DB/`** - Entire database layer (DataBase, Types, ConnectionPool, Exceptions)
   - Should use: `reut/core` via composer autoload
   - **Exception**: `DatabaseCreator.php` might be CLI-specific (routes commands)

2. **`src/Auth/`** - Authentication classes (Auth, AuthController, AuthRouter, NoAuth)
   - Should use: `reut/core` via composer autoload

3. **`src/Middleware/`** - All middleware classes
   - Should use: `reut/core` via composer autoload

4. **`src/Router/`** - Router classes (DocsController, DocsRegistry, ReuteRoute, SchemaController)
   - Should use: `reut/core` via composer autoload

5. **`src/Support/`** - Support classes (ProjectPath)
   - Should use: `reut/core` via composer autoload

### Scripts That Should Use Core (but may need to stay for backward compatibility):
- `migrate.php` - Should use core's migrate.php or be refactored to use MigrateCommand
- `checkmigration.php` - Should use core's checkmigration.php or be refactored to use StatusCommand
- `rollback.php` - Should use core's rollback.php
- `validate-migrations.php` - Should use core's validate-migrations.php
- `export-migrations.php` - Should use core's export-migrations.php
- `import-migrations.php` - Should use core's import-migrations.php
- `inspect.php` - Should use core's inspect.php
- `update.php` - Should use core's update.php
- `createModels.php` - CLI-specific, can stay
- `createRoutes.php` - CLI-specific, can stay
- `createAuthModel.php` - CLI-specific, can stay
- `createAuthUser.php` - CLI-specific, can stay

### CLI-Specific Code (Should Stay):
- `Commands/` - All command classes (new modernization)
- `Output/` - Output formatting (new modernization)
- `Interactive/` - Interactive features (new modernization)
- `Help/` - Help system (new modernization)
- `authContent.php` - Template file
- `configContent.php` - Template file
- `indexContent.php` - Template file
- `dev.php` - CLI-specific dev server script
- `view.php` - CLI-specific viewer script
- `viewer/` - Viewer assets
- `devserver/` - Dev server router
- `Utils/ascii_table.php` - CLI utility (might be duplicated?)

## Recommended Actions

### Phase 1: Verify Core Classes Are Available
1. Ensure `reut/core` is properly autoloaded in CLI
2. Test that all `use Reut\DB\...` statements work with core classes
3. Check if there are any CLI-specific modifications needed

### Phase 2: Remove Duplicate Directories
1. Remove `src/DB/` (except possibly DatabaseCreator.php if CLI-specific)
2. Remove `src/Auth/`
3. Remove `src/Middleware/`
4. Remove `src/Router/`
5. Remove `src/Support/` (if same as core)

### Phase 3: Update Scripts
1. Update all scripts to use core classes via `use` statements
2. Refactor old scripts (migrate.php, checkmigration.php) to use new Command classes
3. Or ensure they properly use core classes

### Phase 4: Update Templates
1. Ensure templates (authContent.php, configContent.php, indexContent.php) reference core classes
2. Update any hardcoded paths

## Benefits
- **Single source of truth**: Core code lives in `reut/core` only
- **Easier maintenance**: Fix bugs once, not in multiple places
- **Smaller CLI package**: Reduced size and complexity
- **Version consistency**: CLI always uses the correct core version

## Risks
- **Breaking changes**: If CLI scripts depend on CLI-specific modifications
- **Version compatibility**: Need to ensure CLI works with required core version
- **Migration effort**: Need to test all commands after removal

## Next Steps
1. Audit which files are actually different between CLI and core
2. Identify any CLI-specific modifications that need to be moved to core
3. Create migration plan to remove duplicates
4. Test all CLI commands after cleanup



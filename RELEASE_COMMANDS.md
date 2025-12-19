# Release Commands for v1.3.0

## Step-by-Step Release Process

### 1. Check Current Status
```bash
cd /media/m4rc/Ps/Reut_CLI
git status
git remote -v
```

### 2. Stage and Commit All Changes
```bash
git add -A
git commit -m "chore: release v1.3.0 - Migration features and improvements

- Added rollback command for migrations
- Added validate-migrations command
- Added export-migrations and import-migrations commands
- Enhanced status command with --json, --summary, --table options
- Added dry-run mode for migrate and sync commands
- Improved error handling and standardization
- Added MigrationHelper utility class
- Fixed case sensitivity issues
- Added comprehensive test suites (v1.1 and v1.2)
- Updated version to 1.3.0"
```

### 3. Push Code to GitHub
```bash
git push origin main
# or if your default branch is master:
# git push origin master
```

### 4. Release reut/core (Following LOCAL_RELEASE.md)

#### 4a. Split subtree for packages/core
```bash
git subtree split --prefix=packages/core -b core-release
```

#### 4b. Push to reut_core repository
```bash
# Make sure reut_core remote exists, if not add it:
# git remote add reut_core <repository-url>

git push reut_core core-release:main
```

#### 4c. Tag and push reut/core release
```bash
git tag -a v1.3.0-core core-release -m "reut/core v1.3.0: Migration features and improvements

- Added rollback command for migrations
- Added validate-migrations command
- Added export-migrations and import-migrations commands
- Enhanced status command with --json, --summary, --table options
- Added dry-run mode for migrate and sync commands
- Improved error handling and standardization
- Added MigrationHelper utility class
- Fixed case sensitivity issues"

git push reut_core v1.3.0-core
```

### 5. Tag CLI Release
```bash
git tag -a v1.3.0 -m "Reut CLI v1.3.0: Migration features and improvements

Features:
- Rollback command for migrations (last batch, specific batch, or migration)
- Validate-migrations command (SQL syntax validation and conflict detection)
- Export-migrations command (JSON/SQL format)
- Import-migrations command (JSON/SQL format)
- Enhanced status command (--json, --summary, --table options)
- Dry-run mode for migrate and sync commands
- Improved error handling and standardization
- MigrationHelper utility class for common operations
- Fixed case sensitivity issues
- Comprehensive test suites (v1.1 and v1.2)

Bug Fixes:
- Fixed SQL injection vulnerabilities
- Fixed duplicate migration handling
- Fixed protected columns warning
- Fixed database connection error handling
- Fixed vendor package sync issues"
```

### 6. Push Tags
```bash
git push origin v1.3.0
```

### 7. Update Dependencies (if needed)
```bash
composer require reut/core:^1.3
composer update reut/core
git add composer.json composer.lock
git commit -m "chore: bump reut/core to v1.3.0"
git push origin main
```

### 8. Cleanup (Optional)
```bash
# Delete local core-release branch after pushing
git branch -D core-release
```

## Quick Release Script

You can also use the provided release script:
```bash
./release-v1.3.0.sh
```

## Verification

After release, verify:
```bash
# Check CLI version
php bin/Reut version
# Should output: Reut CLI v1.3.0

# Check tags
git tag -l "v1.3*"

# Verify remote tags
git ls-remote --tags origin | grep v1.3
git ls-remote --tags reut_core | grep v1.3
```


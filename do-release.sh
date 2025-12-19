#!/bin/sh
set -e

cd /media/m4rc/Ps/Reut_CLI

echo "=== Releasing v1.3.0 ==="
echo ""

# Step 1: Check and commit changes
echo "Step 1: Checking git status..."
git status --short

echo ""
echo "Step 2: Staging changes..."
git add -A

if git diff --cached --quiet; then
    echo "  No changes to commit"
else
    echo "  Committing changes..."
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
fi

# Step 3: Push to main repository
echo ""
echo "Step 3: Pushing to main repository..."
git push origin main || git push origin master

# Step 4: Split subtree for packages/core
echo ""
echo "Step 4: Splitting subtree for packages/core..."
git subtree split --prefix=packages/core -b core-release

# Step 5: Push to reut_core repository (main branch)
echo ""
echo "Step 5: Pushing to reut_core repository (main branch)..."
git push reut_core core-release:main

# Step 6: Tag the core release
echo ""
echo "Step 6: Tagging reut/core v1.3.0-core..."
git tag -a v1.3.0-core core-release -m "reut/core v1.3.0: Migration features and improvements

- Added rollback command for migrations
- Added validate-migrations command
- Added export-migrations and import-migrations commands
- Enhanced status command with --json, --summary, --table options
- Added dry-run mode for migrate and sync commands
- Improved error handling and standardization
- Added MigrationHelper utility class
- Fixed case sensitivity issues"

# Step 7: Push the core tag
echo ""
echo "Step 7: Pushing core tag to reut_core..."
git push reut_core v1.3.0-core

# Step 8: Tag the CLI release
echo ""
echo "Step 8: Tagging CLI release v1.3.0..."
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

# Step 9: Push the CLI tag
echo ""
echo "Step 9: Pushing CLI tag to origin..."
git push origin v1.3.0

echo ""
echo "=== Release Complete ==="
echo ""
echo "✓ Code pushed to origin (main)"
echo "✓ Code pushed to reut_core (main)"
echo "✓ Tagged reut/core as v1.3.0-core"
echo "✓ Tagged CLI as v1.3.0"
echo "✓ All tags pushed"


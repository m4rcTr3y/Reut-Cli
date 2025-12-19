#!/bin/bash
# Release script for Reut CLI v1.3.0
# Follows LOCAL_RELEASE.md process

set -e  # Exit on error

echo "=== Reut CLI v1.3.0 Release Process ==="
echo ""

# Step 1: Check git status
echo "Step 1: Checking git status..."
git status --short

# Step 2: Add all changes
echo ""
echo "Step 2: Staging all changes..."
git add -A

# Step 3: Commit changes
echo ""
echo "Step 3: Committing changes..."
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

# Step 4: Push to GitHub
echo ""
echo "Step 4: Pushing to GitHub..."
git push origin main || git push origin master

# Step 5: Release reut/core (following LOCAL_RELEASE.md)
echo ""
echo "Step 5: Releasing reut/core..."

# Check if reut_core remote exists
if git remote | grep -q "^reut_core$"; then
    echo "  Found reut_core remote"
else
    echo "  Warning: reut_core remote not found. You may need to add it:"
    echo "  git remote add reut_core <repository-url>"
    echo "  Continuing with core release..."
fi

# Split subtree for packages/core
echo "  Splitting subtree for packages/core..."
git subtree split --prefix=packages/core -b core-release

# Push to reut_core repo
echo "  Pushing to reut_core repository..."
if git remote | grep -q "^reut_core$"; then
    git push reut_core core-release:main || git push reut_core core-release:master
    
    # Tag the core release
    echo "  Tagging reut/core v1.3.0..."
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
    echo "  ✓ reut/core v1.3.0 released"
else
    echo "  ⚠ Skipping reut_core push (remote not configured)"
fi

# Step 6: Tag CLI release
echo ""
echo "Step 6: Tagging CLI release v1.3.0..."
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

# Step 7: Push tags
echo ""
echo "Step 7: Pushing tags..."
git push origin v1.3.0

if git remote | grep -q "^reut_core$"; then
    git push reut_core v1.3.0-core 2>/dev/null || true
fi

echo ""
echo "=== Release Complete ==="
echo ""
echo "✓ Code pushed to GitHub"
echo "✓ CLI tagged as v1.3.0"
if git remote | grep -q "^reut_core$"; then
    echo "✓ reut/core tagged as v1.3.0-core"
fi
echo ""
echo "Next steps:"
echo "1. Update Packagist if necessary"
echo "2. Create release notes on GitHub"
echo "3. Update composer.json dependency if needed:"
echo "   composer require reut/core:^1.3"


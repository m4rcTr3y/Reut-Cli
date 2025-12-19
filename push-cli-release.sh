#!/usr/bin/env fish
# Release script for Reut CLI v1.3.0

echo "=== Releasing Reut CLI v1.3.0 ==="
echo ""

# Step 1: Tag the CLI release
echo "Step 1: Tagging CLI release v1.3.0..."
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

# Step 2: Push the tag
echo ""
echo "Step 2: Pushing tag to origin..."
git push origin v1.3.0

echo ""
echo "=== Reut CLI v1.3.0 Release Complete ==="
echo ""
echo "✓ Tagged as v1.3.0"
echo "✓ Tag pushed to origin"


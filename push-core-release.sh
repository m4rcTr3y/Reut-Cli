#!/usr/bin/env fish
# Release script for reut/core v1.3.0
# Following LOCAL_RELEASE.md process

echo "=== Releasing reut/core v1.3.0 ==="
echo ""

# Step 1: Check if changes need to be committed
echo "Step 1: Checking git status..."
git status --short

# Step 2: Stage and commit changes (if any)
echo ""
echo "Step 2: Staging changes..."
git add -A

if git diff --cached --quiet
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
end

# Step 3: Push to main repository
echo ""
echo "Step 3: Pushing to main repository..."
git push origin main 2>&1; or git push origin master 2>&1

# Step 4: Split subtree for packages/core
echo ""
echo "Step 4: Splitting subtree for packages/core..."
git subtree split --prefix=packages/core -b core-release

# Step 5: Push to reut_core repository
echo ""
echo "Step 5: Pushing to reut_core repository..."
if git remote | grep -q "^reut_core$"
    git push reut_core core-release:main 2>&1; or git push reut_core core-release:master 2>&1
    echo "  ✓ Pushed to reut_core"
else
    echo "  ⚠ reut_core remote not found!"
    echo "  Add it with: git remote add reut_core <repository-url>"
    exit 1
end

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

# Step 7: Push the tag
echo ""
echo "Step 7: Pushing tag to reut_core..."
git push reut_core v1.3.0-core

echo ""
echo "=== reut/core v1.3.0 Release Complete ==="
echo ""
echo "✓ Code pushed to reut_core repository"
echo "✓ Tagged as v1.3.0-core"
echo ""
echo "Next: Tag and push CLI release (v1.3.0)"


# Pre-Commit Checklist for v1.2.0

## ✅ Tests Status
- **All versioned tests passing**: ✅ 36 tests, 153 assertions
- **Test location**: `tests/new-feature-tests/v1.0/`
- **Old unversioned tests**: Excluded via `.gitignore`

## 📦 Monorepo Structure
- **Main repo**: `origin` → https://github.com/m4rcTr3y/Reut-Cli.git
- **Sub-package**: `reut_core` → https://github.com/m4rcTr3y/reut_core.git

## 🔄 Files to Commit

### Core Changes (packages/core/)
- `packages/core/src/db/DataBase.php` - Security features (file validation, required fields, SQL injection prevention)
- `packages/core/src/db/types/ColumnType.php` - Added `isNullable()` method
- `packages/core/src/middleware/RateLimitMiddleware.php` - NEW
- `packages/core/src/middleware/CsrfMiddleware.php` - NEW
- `packages/core/src/createModels.php` - Updated template with new features
- `packages/core/src/db/exceptions/*.php` - NEW exception classes

### Configuration & Templates
- `src/indexContent.php` - Added security middlewares
- `packages/skeleton/index.php` - Added security middlewares
- `bin/Reut` - Updated version to v1.2.0, added security env vars
- `src/configContent.php` - (if modified)

### Documentation
- `fixes.md` - Updated with v1.2.0 security features
- `README.md` - Updated version number
- `EXAMPLE_MODEL.php` - Example showing new features

### Tests (Versioned Only)
- `tests/new-feature-tests/v1.0/NewSecurityFeaturesTest.php` - NEW
- `tests/new-feature-tests/v1.0/ProjectScaffoldingTest.php` - NEW
- `tests/new-feature-tests/v1.0/ReutCliCommandTest.php` - NEW
- `tests/new-feature-tests/README.md` - NEW

### Git Configuration
- `.gitignore` - Updated to exclude old unversioned tests

## ⚠️ Important Notes

1. **Monorepo Push**: Remember to push `packages/core/` changes to `reut_core` remote separately
2. **Old Tests**: Excluded from git - only versioned tests in `new-feature-tests/v1.0/` are included
3. **Version**: Updated to v1.2.0 in all locations
4. **Database**: Tests use `test_db_two` (root/root@1234)

## 🚀 Push Commands

```bash
# 1. Add all changes
git add .

# 2. Commit changes
git commit -m "feat: Add security features v1.2.0 - file validation, required fields, rate limiting, CSRF protection, SQL injection prevention"

# 3. Push to main repo
git push origin main

# 4. Push core package to reut_core (if needed)
cd packages/core
git push reut_core <branch-name>
cd ../..
```

## ✅ Pre-Push Verification

- [x] All tests passing (36 tests, 153 assertions)
- [x] Version updated to v1.2.0
- [x] fixes.md updated with new features
- [x] .gitignore excludes old tests
- [x] Only versioned tests included


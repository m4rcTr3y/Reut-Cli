# Release Steps for v1.3.0

## Quick Release (Using Scripts)

### 1. Release reut/core
```fish
chmod +x push-core-release.sh
./push-core-release.sh
```

### 2. Release CLI
```fish
chmod +x push-cli-release.sh
./push-cli-release.sh
```

## Manual Release (Step by Step)

### Part 1: Release reut/core (Following LOCAL_RELEASE.md)

```fish
# 1. Commit all changes
cd /media/m4rc/Ps/Reut_CLI
git add -A
git commit -m "chore: release v1.3.0 - Migration features and improvements"

# 2. Push to main repository
git push origin main

# 3. Split subtree for packages/core
git subtree split --prefix=packages/core -b core-release

# 4. Push to reut_core repository
git push reut_core core-release:main

# 5. Tag the core release
git tag -a v1.3.0-core core-release -m "reut/core v1.3.0: Migration features and improvements"

# 6. Push the tag
git push reut_core v1.3.0-core
```

### Part 2: Release CLI

```fish
# 1. Tag the CLI release
git tag -a v1.3.0 -m "Reut CLI v1.3.0: Migration features and improvements"

# 2. Push the tag
git push origin v1.3.0
```

## Verification

After release, verify tags:
```fish
git tag -l "v1.3*"
git ls-remote --tags origin | grep v1.3
git ls-remote --tags reut_core | grep v1.3
```

## Notes

- Make sure `reut_core` remote is configured:
  ```fish
  git remote add reut_core <repository-url>
  ```
- The `core-release` branch is temporary and can be deleted after pushing:
  ```fish
  git branch -D core-release
  ```


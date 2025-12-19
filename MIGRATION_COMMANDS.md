# Migration Commands Reference

This document explains the differences between migration-related CLI commands in Reut CLI.

## Overview

| Command | File | Purpose | Direction |
|---------|------|---------|-----------|
| `migrate` / `create` | `migrate.php` | Apply migrations from models to database | Models → Database |
| `sync` | `update.php` | Reconcile database with models (aggressive) | Models → Database |
| `status` | `checkmigration.php` | Check pending migrations (read-only) | Read-only |
| `inspect` | `inspect.php` | Sync models from database schema | Database → Models |

---

## 1. `migrate` / `create` (Aliases)

**Command:** `php manage.php migrate` or `php manage.php create`

**Purpose:** Apply migrations from model definitions to the database. This is the **primary migration command** for normal development workflow.

### What it does:
- ✅ Creates tables if they don't exist
- ✅ Adds missing columns from models to existing tables
- ✅ Drops extra columns that are not in models (respects `protectedColumns`)
- ✅ Records all changes in the `migrations` table
- ✅ Checks for existing migrations to avoid duplicates
- ✅ Handles relationships intelligently (creates tables without relations first)

### Key Features:
- **Safe**: Respects `protectedColumns` array - won't drop protected columns
- **Idempotent**: Checks migration history to avoid re-applying changes
- **Relationship-aware**: Processes tables in correct order based on foreign keys
- **Migration tracking**: All changes are recorded with timestamps and batch numbers

### When to use:
- Initial project setup
- After adding new models
- After modifying model column definitions
- Regular development workflow

### Example:
```bash
php manage.php migrate
# Output: Creates missing tables, adds missing columns, records migrations
```

---

## 2. `sync`

**Command:** `php manage.php sync`

**Purpose:** Aggressively reconcile existing database tables with model definitions. This is a **more destructive** operation than `migrate`.

### What it does:
- ✅ Adds missing columns from models
- ✅ Drops removed columns (does NOT respect `protectedColumns`)
- ⚠️ Can drop orphan tables (tables in DB but no model) - **asks for confirmation**
- ✅ Records migrations in the `migrations` table
- ❌ Does NOT create missing tables (tells you to run `create` instead)

### Key Features:
- **Aggressive**: Will drop columns even if they're not explicitly protected
- **Interactive**: Prompts before dropping orphan tables
- **No table creation**: If a table doesn't exist, it tells you to run `create` first
- **Less strict**: Doesn't check migration history as thoroughly as `migrate`

### When to use:
- When you want to force database to match models exactly
- When cleaning up orphan tables
- When you've removed columns from models and want them dropped from DB
- **Use with caution** - can cause data loss

### Example:
```bash
php manage.php sync
# Output: Adds missing columns, drops extra columns, may prompt to drop orphan tables
```

### Differences from `migrate`:
| Feature | `migrate` | `sync` |
|---------|-----------|--------|
| Creates tables | ✅ Yes | ❌ No (tells you to run `create`) |
| Respects protectedColumns | ✅ Yes | ❌ No |
| Drops orphan tables | ❌ No | ✅ Yes (with confirmation) |
| Migration history check | ✅ Strict | ⚠️ Less strict |

---

## 3. `status`

**Command:** `php manage.php status`

**Purpose:** Check for pending migrations without applying them. This is a **read-only** command.

### What it does:
- ✅ Lists all applied migrations from the `migrations` table
- ✅ Checks models against database schema
- ✅ Reports pending migrations (create table, add column, drop column)
- ❌ Does NOT apply any changes
- ❌ Does NOT modify database or models

### Key Features:
- **Read-only**: Safe to run anytime
- **Detailed reporting**: Shows what would be applied if you ran `migrate`
- **Migration history**: Displays all previously applied migrations

### When to use:
- Before running migrations to see what will change
- To check migration status
- To verify database is in sync with models
- Debugging migration issues

### Example:
```bash
php manage.php status
# Output: Shows applied migrations and pending migrations
```

---

## 4. `inspect`

**Command:** `php manage.php inspect [--table=table_name] [--all] [--apply]`

**Purpose:** Inspect database schema and sync model definitions **FROM database TO models**. This is the **reverse** of migration.

### What it does:
- ✅ Reads actual database schema
- ✅ Generates model column definitions from database
- ✅ Updates model files with database schema
- ✅ Works on specific tables or all tables
- ✅ Can preview changes before applying

### Key Features:
- **Reverse direction**: Database → Models (opposite of migrate)
- **Interactive**: Can preview changes before applying
- **Selective**: Can target specific tables with `--table=name`
- **Auto-apply**: Use `--apply` flag to skip confirmation

### When to use:
- When database was modified outside of models
- When you need to sync models with existing database
- When reverse-engineering database schema
- When working with legacy databases

### Example:
```bash
# Inspect specific table
php manage.php inspect --table=users

# Inspect all tables
php manage.php inspect --all

# Auto-apply without confirmation
php manage.php inspect --all --apply
```

### Differences from other commands:
| Feature | `migrate`/`sync` | `inspect` |
|---------|------------------|-----------|
| Direction | Models → Database | Database → Models |
| Modifies | Database | Model files |
| Purpose | Apply model changes | Sync models from DB |

---

## Command Comparison Summary

### By Use Case:

**Initial Setup:**
```bash
php manage.php create  # or migrate
```

**Regular Development (after model changes):**
```bash
php manage.php status   # Check what will change
php manage.php migrate  # Apply changes
```

**Force Sync (aggressive):**
```bash
php manage.php sync     # Warning: may drop columns/tables
```

**Reverse Engineering:**
```bash
php manage.php inspect --all  # Sync models from database
```

### Safety Level:

1. **Safest**: `status` (read-only)
2. **Safe**: `migrate`/`create` (respects protected columns)
3. **Moderate**: `inspect` (modifies model files)
4. **Risky**: `sync` (can drop columns/tables)

---

## Migration Table

All commands (except `status` and `inspect`) record changes in the `migrations` table:

```sql
CREATE TABLE migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    sql_text TEXT NOT NULL,
    batch INT NOT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
```

- **name**: Unique migration identifier (e.g., `create_users_table_20240101120000`)
- **sql_text**: The SQL that was executed
- **batch**: Groups related migrations together
- **applied_at**: Timestamp when migration was applied

---

## Best Practices

1. **Always check status first**: Run `php manage.php status` before migrating
2. **Use migrate for normal workflow**: Prefer `migrate` over `sync` for regular development
3. **Protect important columns**: Use `protectedColumns` array in models to prevent accidental drops
4. **Backup before sync**: Always backup database before running `sync` command
5. **Use inspect for legacy DBs**: When working with existing databases, use `inspect` to generate models

---

## Notes

- `create` and `migrate` are **aliases** - they do exactly the same thing
- `sync` does NOT create tables - it only modifies existing ones
- `inspect` modifies **model files**, not the database
- All commands respect relationship ordering (tables without foreign keys are created first)


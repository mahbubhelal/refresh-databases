# Refresh Databases

A Laravel package that extends the `RefreshDatabase` trait to support multiple database connections with separate migration paths. Also provides a fast database refreshing mechanism that skips migrations when migrations are unchanged.

## Requirements

- PHP 8.3+
- Laravel 11.x or 12.x

## Installation

```bash
composer require mahbubhelal/refresh-databases --dev
```

## Usage

### Basic Usage

Replace Laravel's `RefreshDatabase` trait with `RefreshDatabases` in your test case:

```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Mahbub\RefreshDatabases\RefreshDatabases;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabases;
}
```

By default, this behaves exactly like Laravel's `RefreshDatabase` trait, using your default database connection and the standard `database/migrations` path.

### Multiple Database Connections

To refresh multiple database connections, define the `$connectionsToTransact` property:

```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Mahbub\RefreshDatabases\RefreshDatabases;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabases;

    protected array $connectionsToTransact = ['mysql', 'reporting'];
}
```

The package will run `migrate:fresh` on each connection. Migration paths are resolved as follows:

| Connection | Migration Path |
|------------|----------------|
| Default connection | `database/migrations` |
| Other connections | `database/migrations/{connection}` |

For the example above with `mysql` as the default connection:
- `mysql` → `database/migrations`
- `reporting` → `database/migrations/reporting`

### Custom Migration Paths

Override the default migration paths using the `$migrationPaths` property:

```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Mahbub\RefreshDatabases\RefreshDatabases;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabases;

    protected array $connectionsToTransact = ['mysql', 'reporting', 'analytics'];

    protected array $migrationPaths = [
        'reporting' => '/custom/path/to/reporting/migrations',
        // 'mysql' will use database/migrations (default connection)
        // 'analytics' will use database/migrations/analytics (convention)
    ];
}
```

### Lifecycle Hooks

Use lifecycle hooks to run code before or after database refresh:

```php
protected function beforeRefreshingDatabases(): void
{
    // Runs before migrations execute
}

protected function afterRefreshingDatabases(): void
{
    // Runs after migrations and transaction setup complete
}
```

## Fast Refresh

The `FastRefreshDatabases` trait extends `RefreshDatabases` with checksum-based migration caching. Migrations only run when files change, significantly speeding up test suites.

```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Mahbub\RefreshDatabases\FastRefreshDatabases;

abstract class TestCase extends BaseTestCase
{
    use FastRefreshDatabases;

    protected array $connectionsToTransact = ['mysql', 'reporting'];
}
```

### How It Works

1. Calculates a SHA-256 checksum from all migration files (modification time + path) and the current git branch
2. Compares against the cached checksum in `storage/app/migration-checksum_{database}.txt`
3. Only runs migrations if the checksum differs
4. Stores the new checksum after successful migration

### Caveats

The checksum only covers migration files and the git branch. Changes to SQL files (`schema`, `views`, `seed`) are **not** detected. If you modify an SQL file without changing any migration, clear the checksum to force a re-run.

### Clearing the Checksum

To force a fresh migration, remove the checksum file:

```bash
php artisan refresh-databases:remove-checksum
```

This removes all `migration-checksum_*.txt` files from the storage directory.

## SQL Files

After running `migrate:fresh` on each connection, the package loads optional SQL files from `database/schema/` in the following order:

### Schema Files

For connections whose driver doesn't support Laravel's built-in schema dump (e.g., `sqlsrv`), the package loads `database/schema/{connection}-schema.sql` after running migrations. Drivers that support built-in schema loading (`mysql`, `mariadb`, `pgsql`, `sqlite`) skip this step.

### Views Files

View definitions can be provided via `database/schema/{connection}-views.sql`. These are loaded for all connections, regardless of driver.

### Seed Files

Seed data can be provided via `database/schema/{connection}-seed.sql`. These are loaded for all connections, regardless of driver.

### Example

```
database/
└── schema/
    ├── reporting-schema.sql   # DDL for 'reporting' connection (loaded if driver lacks built-in schema support)
    ├── reporting-views.sql    # View definitions for 'reporting' connection
    ├── reporting-seed.sql     # Seed data for 'reporting' connection
    ├── mysql-views.sql        # View definitions for 'mysql' connection
    └── mysql-seed.sql         # Seed data for 'mysql' connection
```

## Parallel Testing

The package fully supports Laravel's parallel testing. When running tests with `--parallel`, the package automatically:

1. **Creates separate test databases** for each parallel process (suffixed with `_test_{token}`)
2. **Replaces database names** in schema, views, and seed SQL files to target the correct parallel database

### Automatic Database Creation

When a parallel test process starts, the package creates a new database if it doesn't exist. The `{database}` is the configured database name from your connection config (not the connection name):

- **MySQL**: `CREATE DATABASE IF NOT EXISTS {database}_test_{token}`
- **SQL Server**: `CREATE DATABASE [{database}_test_{token}]`

For example, if your `reporting` connection has `database: 'reports_db'`, the parallel database would be `reports_db_test_1`.

### SQL Server Three-Part Naming in Parallel Tests

SQL Server schema, views, and seed files often use three-part naming: `Database.Schema.Table`. During parallel testing, database names in SQL are automatically replaced with the parallel database name.

```sql
-- database/schema/tcb-schema.sql (for connection named 'tcb')
CREATE TABLE TCB.dbo.Conferences (
    Conference_ID bigint NOT NULL,
    Title nvarchar(175)
);
```

During parallel testing, this becomes:
- `TCB.dbo.Conferences` → `TCB_test_1.dbo.Conferences`

MySQL seed and views files typically use simple table names since the connection already targets the correct database.

### Running Parallel Tests

```bash
# Run tests in parallel (uses available CPU cores)
php artisan test --parallel

# Run with specific number of processes
php artisan test --parallel --processes=4
```

## Directory Structure

Recommended directory structure for multiple connections (assuming `mysql` is default, with additional `reporting` and `analytics` connections):

```
database/
├── schema/
│   ├── reporting-schema.sql     # Schema DDL (loaded for drivers without built-in schema support)
│   ├── reporting-views.sql      # View definitions (loaded for any driver)
│   ├── reporting-seed.sql       # Seed data (loaded for any driver)
│   ├── analytics-seed.sql       # Seed data for analytics connection
│   └── mysql-views.sql          # View definitions for default connection
└── migrations/
    ├── 2024_01_01_000000_create_users_table.php      # default (mysql) connection
    ├── 2024_01_01_000001_create_posts_table.php      # default (mysql) connection
    ├── reporting/
    │   └── 2024_01_01_000000_create_reports_table.php
    └── analytics/
        └── 2024_01_01_000000_create_metrics_table.php
```

## Credits

The fast refresh functionality is inspired by [PlannrCrm/laravel-fast-refresh-database](https://github.com/PlannrCrm/laravel-fast-refresh-database).

## License

MIT

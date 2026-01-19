# Refresh Databases

A Laravel package that extends the `RefreshDatabase` trait to support multiple database connections with separate migration paths. Also provides a fast database refreshing mechanism that skips migrations when migration is unchanged.

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

### Clearing the Checksum

If you need to force a fresh migration, remove the checksum file:

```bash
php artisan refresh-databases:remove-checksum
```

This removes all `migration-checksum_*.txt` files from the storage directory.

## SQL Server Support

For SQL Server connections where Laravel's built-in schema dump loading doesn't work, the package automatically loads schema files from `database/schema/{connection}-schema.sql` after running migrations.

You can also provide seed data via `database/schema/{connection}-seed.sql`.

```
database/
└── schema/
    ├── sqlsrv-reporting-schema.sql    # SQL Server schema dump
    └── sqlsrv-reporting-seed.sql      # Optional seed data
```

## Directory Structure

Recommended directory structure for multiple connections:

```
database/
├── schema/
│   ├── mysql-cid-schema.sql           # MySQL schema (auto-loaded by Laravel)
│   ├── sqlsrv-tcb-schema.sql          # SQL Server schema (loaded by package)
│   └── sqlsrv-tcb-seed.sql            # Optional SQL seed file
└── migrations/
    ├── 2024_01_01_000000_create_users_table.php      # default connection
    ├── 2024_01_01_000001_create_posts_table.php      # default connection
    └── mysql-cid/
        └── 2024_01_01_000000_create_other_table.php
```

## Credits

The fast refresh functionality is inspired by [PlannrCrm/laravel-fast-refresh-database](https://github.com/PlannrCrm/laravel-fast-refresh-database).

## License

MIT

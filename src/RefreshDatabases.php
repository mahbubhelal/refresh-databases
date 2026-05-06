<?php

declare(strict_types=1);

namespace Mahbub\RefreshDatabases;

use Illuminate\Foundation\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\ParallelTesting;
use RuntimeException;

trait RefreshDatabases
{
    use RefreshDatabase;

    /** @var array<string, string> */
    protected static array $databaseNameMap = [];

    /** @var array<string, string> */
    protected static array $originalDatabaseNames = [];

    public function replaceParallelDatabaseNames(string $sql): string
    {
        foreach (static::$databaseNameMap as $original => $parallel) {
            $sql = preg_replace(
                '/\b' . preg_quote($original, '/') . '\.dbo\./i',
                $parallel . '.dbo.',
                $sql
            ) ?? $sql;

            $sql = preg_replace(
                '/\b' . preg_quote($original, '/') . '\.(\w)/i',
                $parallel . '.$1',
                $sql
            ) ?? $sql;
        }

        return $sql;
    }

    protected function beforeRefreshingDatabase(): void
    {
        $this->beforeRefreshingDatabases();

        $this->setConnectionsToTransact();

        $this->configureParallelDatabases();

        $this->setMigrationPaths();
    }

    protected function configureParallelDatabases(): void
    {
        $token = ParallelTesting::token();

        if ($token === false) {
            return;
        }

        /** @var array<string> */
        $connections = $this->connectionsToTransact; // @phpstan-ignore property.notFound

        foreach ($connections as $connection) {
            $this->configureParallelDatabase($connection, $token);
        }
    }

    protected function configureParallelDatabase(string $connection, string $token): void
    {
        $configKey = "database.connections.{$connection}.database";

        if (array_key_exists($connection, static::$originalDatabaseNames)) {
            config([$configKey => static::$originalDatabaseNames[$connection] . "_test_{$token}"]);

            return;
        }

        $baseDatabase = config($configKey);

        if (!is_string($baseDatabase) || $baseDatabase === ':memory:') {
            return;
        }

        static::$originalDatabaseNames[$connection] = $baseDatabase;

        $parallelDatabase = "{$baseDatabase}_test_{$token}";

        config([$configKey => $parallelDatabase]);

        $this->ensureParallelDatabaseExists($connection, $parallelDatabase);

        static::$databaseNameMap[$baseDatabase] = $parallelDatabase;
    }

    protected function ensureParallelDatabaseExists(string $connection, string $database): void
    {
        $driver = config("database.connections.{$connection}.driver");

        if ($driver === 'mysql') {
            $this->createMysqlDatabase($connection, $database);
        } elseif ($driver === 'sqlsrv') {
            $this->createSqlServerDatabase($connection, $database);
        }
    }

    protected function createMysqlDatabase(string $connection, string $database): void
    {
        /** @var string $charset */
        $charset = config("database.connections.{$connection}.charset");
        /** @var string $collation */
        $collation = config("database.connections.{$connection}.collation");

        config(["database.connections.{$connection}.database" => null]);
        DB::purge($connection);

        DB::connection($connection)->statement(
            "CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET {$charset} COLLATE {$collation}"
        );

        config(["database.connections.{$connection}.database" => $database]);
        DB::purge($connection);
    }

    protected function createSqlServerDatabase(string $connection, string $database): void
    {
        config(["database.connections.{$connection}.database" => null]);
        DB::purge($connection);

        $exists = DB::connection($connection)->selectOne(
            'SELECT name FROM sys.databases WHERE name = ?',
            [$database]
        );

        if ($exists === null) {
            DB::connection($connection)->statement("CREATE DATABASE [{$database}]");
        }

        config(["database.connections.{$connection}.database" => $database]);
        DB::purge($connection);
    }

    protected function afterRefreshingDatabase(): void
    {
        $this->afterRefreshingDatabases();
    }

    protected function beforeRefreshingDatabases(): void {}

    protected function afterRefreshingDatabases(): void {}

    protected function setConnectionsToTransact(): void
    {
        if (property_exists($this, 'connectionsToTransact')) { // @phpstan-ignore function.impossibleType
            return;
        }

        $defaultConnection = config('database.default');

        $this->connectionsToTransact = [$defaultConnection]; // @phpstan-ignore property.notFound
    }

    protected function setMigrationPaths(): void
    {
        /** @var array<string, string> */
        $migrationPaths = $this->migrationPaths ?? []; // @phpstan-ignore property.notFound

        $paths = [];
        $migrationPath = database_path('migrations');
        $defaultConnection = config('database.default');

        /** @var array<string> */
        $connections = $this->connectionsToTransact; // @phpstan-ignore property.notFound

        foreach ($connections as $connection) {
            if (!is_array(config("database.connections.{$connection}"))) {
                throw new RuntimeException("Database connection [{$connection}] is not defined.");
            }

            if (array_key_exists($connection, $migrationPaths)) {
                $paths[$connection] = $migrationPaths[$connection];

                continue;
            }

            $paths[$connection] = $connection === $defaultConnection
                ? $migrationPath
                : $migrationPath . '/' . $connection;
        }

        $this->migrationPaths = $paths; // @phpstan-ignore property.notFound
    }

    /**
     * @return array<string, string>
     */
    protected function getMigrationPaths(): array
    {
        return $this->migrationPaths; // @phpstan-ignore property.notFound, return.type
    }

    protected function refreshTestDatabase(): void
    {
        if (!RefreshDatabaseState::$migrated) {
            $this->migrateConnections();

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }

    protected function migrateInMemoryConnections(): void
    {
        foreach ($this->getMigrationPaths() as $connection => $path) {
            if (config("database.connections.{$connection}.database") !== ':memory:') {
                continue;
            }

            $this->artisan('migrate:fresh', array_merge(
                [
                    '--database' => $connection,
                    '--path' => $path,
                    '--realpath' => true,
                ],
                $this->migrateFreshUsing()
            ));

            if (!$this->supportsSchemaLoading($connection)) {
                $this->loadSqlFile($connection, 'schema');
            }

            $this->loadSqlFile($connection, 'views');
            $this->loadSqlFile($connection, 'seed');
        }

        resolve(Kernel::class)->setArtisan(null);

        $this->updateLocalCacheOfInMemoryDatabases();
    }

    protected function migrateConnections(): void
    {
        foreach ($this->getMigrationPaths() as $connection => $path) {
            if ($this->supportsSchemaLoading($connection)) {
                DB::connection($connection)->getSchemaBuilder()->dropAllTables();
            }

            $this->artisan('migrate:fresh', array_merge(
                [
                    '--database' => $connection,
                    '--path' => $path,
                    '--realpath' => true,
                ],
                $this->migrateFreshUsing()
            ));

            if (!$this->supportsSchemaLoading($connection)) {
                $this->loadSqlFile($connection, 'schema');
            }

            $this->loadSqlFile($connection, 'views');
            $this->loadSqlFile($connection, 'seed');
        }

        resolve(Kernel::class)->setArtisan(null);

        $this->updateLocalCacheOfInMemoryDatabases();
    }

    protected function supportsSchemaLoading(string $connection): bool
    {
        $driver = config("database.connections.{$connection}.driver");

        return in_array($driver, ['mysql', 'mariadb', 'pgsql', 'sqlite'], true);
    }

    protected function loadSqlFile(string $connection, string $type): void
    {
        $path = database_path("schema/{$connection}-{$type}.sql");

        if (!file_exists($path)) {
            return;
        }

        $sql = $this->replaceParallelDatabaseNames(File::get($path));

        $statements = array_filter(
            explode(';', $sql),
            static fn (string $s): bool => trim($s) !== ''
        );

        foreach ($statements as $statement) {
            DB::connection($connection)->statement(trim($statement));
        }
    }
}

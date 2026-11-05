<?php

declare(strict_types=1);

namespace Mahbub\RefreshDatabases;

use Illuminate\Foundation\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\ParallelTesting;
use RuntimeException;

trait RefreshDatabases
{
    use RefreshDatabase;

    private const string IN_MEMORY_DATABASE = ':memory:';

    private const string SEMICOLON_OUTSIDE_QUOTES = "/;(?=(?:[^'\"]*['\"][^'\"]*['\"])*[^'\"]*$)/";

    /** @var array<string, string> */
    protected static array $databaseNameMap = [];

    /** @var array<string, string> */
    protected static array $originalDatabaseNames = [];

    /** @var array<string, string> */
    private array $resolvedMigrationPaths = [];

    public function replaceParallelDatabaseNames(string $sql): string
    {
        foreach (static::$databaseNameMap as $original => $parallel) {
            $sql = preg_replace('/\b' . preg_quote($original, '/') . '\.(\w)/i', $parallel . '.$1', $sql)
                ?? throw new RuntimeException("Could not replace the database name [{$original}] in the SQL file: " . preg_last_error_msg());
        }

        return $sql;
    }

    /**
     * @return list<string>
     */
    public function splitSqlStatements(string $sql): array
    {
        $statements = preg_split(self::SEMICOLON_OUTSIDE_QUOTES, $sql);

        if ($statements === false) {
            throw new RuntimeException('Could not split the SQL file into statements: ' . preg_last_error_msg());
        }

        return array_values(array_filter(
            array_map(trim(...), $statements),
            static fn (string $statement): bool => $statement !== ''
        ));
    }

    protected function beforeRefreshingDatabase(): void
    {
        $this->beforeRefreshingDatabases();

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
        $connections = $this->connectionsToTransact();

        foreach ($connections as $connection) {
            $this->configureParallelDatabase($connection, $token);
        }
    }

    protected function configureParallelDatabase(string $connection, string $token): void
    {
        $configKey = "database.connections.{$connection}.database";

        if (array_key_exists($connection, static::$originalDatabaseNames)) {
            Config::set($configKey, $this->parallelDatabaseName(static::$originalDatabaseNames[$connection], $token));

            return;
        }

        $baseDatabase = Config::string($configKey);

        if ($baseDatabase === self::IN_MEMORY_DATABASE) {
            return;
        }

        static::$originalDatabaseNames[$connection] = $baseDatabase;

        $parallelDatabase = $this->parallelDatabaseName($baseDatabase, $token);

        Config::set($configKey, $parallelDatabase);

        $this->ensureParallelDatabaseExists($connection, $parallelDatabase);

        static::$databaseNameMap[$baseDatabase] = $parallelDatabase;
    }

    protected function ensureParallelDatabaseExists(string $connection, string $database): void
    {
        $driver = Config::string("database.connections.{$connection}.driver");

        if ($driver === 'mysql') {
            $this->createMysqlDatabase($connection, $database);
        } elseif ($driver === 'sqlsrv') {
            $this->createSqlServerDatabase($connection, $database);
        }
    }

    protected function createMysqlDatabase(string $connection, string $database): void
    {
        $charset = Config::string("database.connections.{$connection}.charset");
        $collation = Config::string("database.connections.{$connection}.collation");

        Config::set("database.connections.{$connection}.database");
        DB::purge($connection);

        DB::connection($connection)->statement(
            "CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET {$charset} COLLATE {$collation}"
        );

        Config::set("database.connections.{$connection}.database", $database);
        DB::purge($connection);
    }

    protected function createSqlServerDatabase(string $connection, string $database): void
    {
        Config::set("database.connections.{$connection}.database");
        DB::purge($connection);

        $exists = DB::connection($connection)->selectOne(
            'SELECT name FROM sys.databases WHERE name = ?',
            [$database]
        );

        if ($exists === null) {
            DB::connection($connection)->statement("CREATE DATABASE [{$database}]");
        }

        Config::set("database.connections.{$connection}.database", $database);
        DB::purge($connection);
    }

    protected function afterRefreshingDatabase(): void
    {
        $this->afterRefreshingDatabases();
    }

    protected function beforeRefreshingDatabases(): void {}

    /**
     * @return array<string, string>
     */
    protected function migrationPaths(): array
    {
        return [];
    }

    protected function afterRefreshingDatabases(): void {}

    protected function setMigrationPaths(): void
    {
        $migrationPaths = $this->migrationPaths();

        $paths = [];
        $migrationPath = database_path('migrations');
        $defaultConnection = Config::string('database.default');

        /** @var array<string> */
        $connections = $this->connectionsToTransact();

        foreach ($connections as $connection) {
            if (!Config::has("database.connections.{$connection}")) {
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

        $this->resolvedMigrationPaths = $paths;
    }

    /**
     * @return array<string, string>
     */
    protected function getMigrationPaths(): array
    {
        return $this->resolvedMigrationPaths;
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
            if (!$this->isInMemoryConnection($connection)) {
                continue;
            }

            $this->migrateConnection($connection, $path);
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

            $this->migrateConnection($connection, $path);
        }

        resolve(Kernel::class)->setArtisan(null);

        $this->updateLocalCacheOfInMemoryDatabases();
    }

    protected function supportsSchemaLoading(string $connection): bool
    {
        $driver = Config::string("database.connections.{$connection}.driver");

        return in_array($driver, ['mysql', 'mariadb', 'pgsql', 'sqlite'], true);
    }

    protected function loadSqlFile(string $connection, string $type): void
    {
        $path = database_path("schema/{$connection}-{$type}.sql");

        if (!file_exists($path)) {
            return;
        }

        $sql = $this->replaceParallelDatabaseNames(File::get($path));

        foreach ($this->splitSqlStatements($sql) as $statement) {
            DB::connection($connection)->statement($statement);
        }
    }

    private function migrateConnection(string $connection, string $path): void
    {
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

    private function isInMemoryConnection(string $connection): bool
    {
        return Config::get("database.connections.{$connection}.database") === self::IN_MEMORY_DATABASE;
    }

    private function parallelDatabaseName(string $baseDatabase, string $token): string
    {
        return "{$baseDatabase}_test_{$token}";
    }
}

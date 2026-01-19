<?php

declare(strict_types=1);

namespace Mahbub\RefreshDatabases;

use Illuminate\Foundation\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use RuntimeException;

trait RefreshDatabases
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->beforeRefreshingDatabases();

        $this->setConnectionsToTransact();

        $this->setMigrationPaths();
    }

    protected function afterRefreshingDatabase(): void
    {
        $this->afterRefreshingDatabases();
    }

    protected function afterRefreshingDatabases(): void {}

    protected function beforeRefreshingDatabases(): void {}

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
     * Get the migration paths mapping.
     *
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

    protected function migrateConnections(): void
    {
        foreach ($this->getMigrationPaths() as $connection => $path) {
            $this->artisan('migrate:fresh', array_merge(
                [
                    '--database' => $connection,
                    '--path' => $path,
                    '--realpath' => true,
                ],
                $this->migrateFreshUsing()
            ));

            if (config("database.connections.{$connection}.driver") === 'sqlsrv') {
                $this->loadSqlSrvConnectionSchema($connection);
            }

            $this->loadConnectionSeeds($connection);
        }

        resolve(Kernel::class)->setArtisan(null);
    }

    /**
     * Load schema from SQL file for SQL Server connections.
     *
     * Laravel's built-in schema dump loading doesn't work well with SQL Server,
     * so this method manually loads schema from database/schema/{connection}-schema.sql.
     */
    protected function loadSqlSrvConnectionSchema(string $connection): void
    {
        $schemaPath = database_path("schema/{$connection}-schema.sql");

        if (!file_exists($schemaPath)) {
            return;
        }

        $statements = array_filter(
            explode(';', (string) file_get_contents($schemaPath)),
            static fn (string $s): bool => trim($s) !== ''
        );

        foreach ($statements as $statement) {
            DB::connection($connection)->statement(trim($statement));
        }
    }

    /**
     * Load seed data for a connection from SQL file.
     */
    protected function loadConnectionSeeds(string $connection): void
    {
        $seedPath = database_path("schema/{$connection}-seed.sql");

        if (!file_exists($seedPath)) {
            return;
        }

        $statements = array_filter(
            explode(';', (string) file_get_contents($seedPath)),
            static fn (string $s): bool => trim($s) !== ''
        );

        foreach ($statements as $statement) {
            DB::connection($connection)->statement(trim($statement));
        }
    }
}

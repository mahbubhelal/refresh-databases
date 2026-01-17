<?php

declare(strict_types=1);

namespace Mahbub\RefreshDatabases;

use Illuminate\Foundation\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
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

            if ($connection === $defaultConnection) {
                $paths[$connection] = $migrationPath;

                continue;
            }

            $paths[$connection] = array_key_exists($connection, $migrationPaths)
                ? $migrationPaths[$connection]
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
        }

        resolve(Kernel::class)->setArtisan(null);
    }
}

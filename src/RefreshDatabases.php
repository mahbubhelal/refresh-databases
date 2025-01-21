<?php

declare(strict_types=1);

namespace Tcb\FastRefreshDatabases;

use Illuminate\Foundation\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\File;

trait RefreshDatabases
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase()
    {
        $this->runIt();

        $this->setConnectionsToTransact();
    }

    protected function runIt() {}

    protected function setConnectionsToTransact()
    {
        if (property_exists($this, 'connectionsToTransact')) {
            return;
        }

        $migrationPath = database_path('migrations');
        $connections = config('database.default') ? [$migrationPath => config('database.default')] : [];

        if (File::exists($migrationPath)) {
            $connections = array_merge(
                $connections,
                collect(File::directories($migrationPath))
                    ->mapWithKeys(fn ($path) => [$path => basename((string) $path)])
                    ->filter(fn ($connection, $path): bool => is_array(config("database.connections.{$connection}")))
                    ->toArray()
            );
        }

        $this->connectionsToTransact = $connections;
    }

    protected function refreshTestDatabase()
    {
        if (!RefreshDatabaseState::$migrated) {
            $this->migrateConnections();

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }

    protected function migrateConnections()
    {
        foreach ($this->connectionsToTransact() as $path => $connection) {
            $this->artisan('migrate:fresh', array_merge(
                [
                    '--database' => $connection,
                    '--path' => $path,
                    '--realpath' => true,
                ],
                $this->migrateFreshUsing()
            ));
        }

        $this->app[Kernel::class]->setArtisan(null);
    }
}

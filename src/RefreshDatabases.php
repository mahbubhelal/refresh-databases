<?php

declare(strict_types=1);

namespace Mahbub\FastRefreshDatabases;

use Illuminate\Foundation\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\File;

trait RefreshDatabases
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->beforeRefreshingDatabases();

        $this->setConnectionsToTransact();
    }

    protected function beforeRefreshingDatabases(): void {}

    protected function setConnectionsToTransact(): void
    {
        if (property_exists($this, 'connectionsToTransact')) {
            return;
        }

        $hasDefaultConnection = !is_null(config('database.default'));

        $migrationPath = database_path('migrations');
        $connections = $hasDefaultConnection ? [$migrationPath => config('database.default')] : [];

        if (File::exists($migrationPath)) {
            /** @var list<string> */
            $directories = File::directories($migrationPath);

            $connections = array_merge(
                $connections,
                collect($directories)
                    ->mapWithKeys(fn (string $path) => [$path => basename($path)])
                    ->filter(fn ($connection, $path): bool => is_array(config("database.connections.{$connection}")))
                    ->toArray()
            );
        }

        $this->connectionsToTransact = $connections; // @phpstan-ignore property.notFound
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

        app(Kernel::class)->setArtisan(null);
    }
}

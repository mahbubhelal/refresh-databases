<?php

declare(strict_types=1);

namespace Tcb\FastRefreshDatabases;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Tcb\FastRefreshDatabases\FastRefreshDatabaseState;

trait FastRefreshDatabases
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase()
    {
        $this->connectionsToTransact = explode(',', env('DB_CONNECTIONS', env('DB_CONNECTION')));
    }

    protected function refreshTestDatabase()
    {
        $this->fastRefreshTestDatabases();

        $this->beginDatabaseTransaction();
    }

    protected function fastRefreshTestDatabases()
    {
        if (!RefreshDatabaseState::$migrated) {
            $cachedChecksum = FastRefreshDatabaseState::$cachedChecksum ??= $this->getCachedMigrationChecksum();
            $currentChecksum = FastRefreshDatabaseState::$currentChecksum ??= $this->calculateMigrationChecksum();

            if ($cachedChecksum !== $currentChecksum) {
                foreach ($this->connectionsToTransact() as $connection) {
                    $this->runMigrations($connection);

                    if (config('database.connections.' . $connection . '.driver') === 'sqlsrv') {
                        $this->migrateSqlServerSchemeDumps($connection);
                        $this->migrateSqlServerViews($connection);
                    }

                    $this->runExtraSeeds($connection);
                }

                $this->app[Kernel::class]->setArtisan(null);

                $this->storeMigrationChecksum($currentChecksum);
            }

            RefreshDatabaseState::$migrated = true;
        }
    }

    /**
     * Calculate a checksum based on the migrations name and last modified date
     *
     * @throws JsonException
     */
    protected function calculateMigrationChecksum(): string
    {
        $finder = Finder::create()
            ->in(database_path('migrations'))
            ->name('*.php')
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
            ->files();

        $migrations = array_map(static fn (SplFileInfo $fileInfo) => [$fileInfo->getMTime(), $fileInfo->getPath()], iterator_to_array($finder));

        // Reset the array keys so there is less data

        $migrations = array_values($migrations);

        // Add the current git branch

        $checkBranch = new Process(['git', 'branch', '--show-current']);
        $checkBranch->run();

        $migrations['gitBranch'] = trim($checkBranch->getOutput());

        // Create a hash

        return hash('sha256', json_encode($migrations, JSON_THROW_ON_ERROR));
    }

    /**
     * Get the cached migration checksum
     */
    protected function getCachedMigrationChecksum(): ?string
    {
        return rescue(fn () => file_get_contents($this->getMigrationChecksumFile()), null, false);
    }

    /**
     * Store the migration checksum
     */
    protected function storeMigrationChecksum(string $checksum): void
    {
        file_put_contents($this->getMigrationChecksumFile(), $checksum);
    }

    /**
     * Provides a configurable migration checksum file path
     */
    protected function getMigrationChecksumFile(): string
    {
        $connection = $this->app[ConnectionInterface::class];

        $databaseNameSlug = Str::slug($connection->getDatabaseName());

        return storage_path("app/migration-checksum_{$databaseNameSlug}.txt");
    }

    protected function connectionsToTransact()
    {
        return explode(',', env('DB_CONNECTIONS'));
    }

    private function runMigrations(string $connection): void
    {
        $path = 'database/migrations/';

        $defaultConnection = config('database.default');

        $path .= $connection === $defaultConnection ? '' : $connection;

        $this->artisan(
            'migrate:fresh',
            array_merge(
                [
                    '--database' => $connection,
                    '--path' => $path,
                ],
                $this->migrateFreshUsing()
            )
        );
    }

    private function migrateSqlServerSchemeDumps(string $connection): void
    {
        if (file_exists(database_path('extras/' . $connection . '-schema.sql'))) {
            DB::connection($connection)
                ->statement(
                    file_get_contents(
                        database_path('extras/' . $connection . '-schema.sql')
                    )
                );
        }
    }

    private function migrateSqlServerViews(string $connection): void
    {
        if (file_exists(database_path('extras/' . $connection . '-view.sql'))) {
            $contents = file_get_contents(database_path('extras/' . $connection . '-view.sql'));

            $contents = explode(';', trim(trim($contents), ';'));

            foreach ($contents as $content) {
                DB::connection($connection)
                    ->statement(trim($content));
            }
        }
    }

    private function runExtraSeeds(string $connection): void
    {
        if (file_exists(database_path('extras/' . $connection . '-seed.sql'))) {
            $contents = file_get_contents(database_path('extras/' . $connection . '-seed.sql'));

            $contents = explode(';', trim(trim($contents), ';'));

            foreach ($contents as $content) {
                DB::connection($connection)
                    ->statement(trim($content));
            }
        }
    }
}

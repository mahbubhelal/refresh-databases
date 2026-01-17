<?php

declare(strict_types=1);

namespace Mahbub\RefreshDatabases;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use JsonException;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

trait FastRefreshDatabases
{
    use RefreshDatabases;

    protected function refreshTestDatabase(): void
    {
        if (!RefreshDatabaseState::$migrated) {
            $cachedChecksum = FastRefreshDatabaseState::$cachedChecksum ??= $this->getCachedMigrationChecksum();
            $currentChecksum = FastRefreshDatabaseState::$currentChecksum ??= $this->calculateMigrationChecksum();

            if ($cachedChecksum !== $currentChecksum) {
                $this->migrateConnections();

                $this->storeMigrationChecksum($currentChecksum);
            }

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }

    /**
     * @throws JsonException
     */
    protected function calculateMigrationChecksum(): string
    {
        $files = Finder::create()
            ->in(database_path('migrations'))
            ->name('*.php')
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
            ->files();

        $migrations = array_map(static fn (SplFileInfo $fileInfo): array => [$fileInfo->getMTime(), $fileInfo->getPath()], iterator_to_array($files));

        // Reset the array keys so there is less data
        $migrations = array_values($migrations);

        // Add the current git branch
        $checkBranch = new Process(['git', 'branch', '--show-current']);
        $checkBranch->run();

        $migrations['gitBranch'] = trim($checkBranch->getOutput());

        // Create a hash
        return hash('sha256', json_encode($migrations, JSON_THROW_ON_ERROR));
    }

    protected function getCachedMigrationChecksum(): ?string
    {
        return rescue(fn (): string => File::get($this->getMigrationChecksumFile()), null, false);
    }

    protected function storeMigrationChecksum(string $checksum): void
    {
        File::put($this->getMigrationChecksumFile(), $checksum);
    }

    protected function getMigrationChecksumFile(): string
    {
        $connection = resolve(ConnectionInterface::class);

        $databaseNameSlug = Str::slug($connection->getDatabaseName());

        return storage_path("app/migration-checksum_{$databaseNameSlug}.txt");
    }
}

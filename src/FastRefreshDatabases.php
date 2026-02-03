<?php

declare(strict_types=1);

namespace Mahbub\RefreshDatabases;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use JsonException;
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
        $checksumData = [];

        foreach ($this->getMigrationPaths() as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $files = Finder::create()
                ->in($path)
                ->name('*.php')
                ->ignoreDotFiles(true)
                ->ignoreVCS(true)
                ->files();

            foreach ($files as $file) {
                $checksumData[] = [$file->getMTime(), $file->getRealPath()];
            }
        }

        $schemaPath = database_path('schema');

        if (is_dir($schemaPath)) {
            $schemaFiles = Finder::create()
                ->in($schemaPath)
                ->name('*.sql')
                ->ignoreDotFiles(true)
                ->ignoreVCS(true)
                ->files();

            foreach ($schemaFiles as $file) {
                $checksumData[] = [$file->getMTime(), $file->getRealPath()];
            }
        }

        $checkBranch = new Process(['git', 'branch', '--show-current']);
        $checkBranch->run();

        $checksumData['gitBranch'] = trim($checkBranch->getOutput());

        return hash('sha256', json_encode($checksumData, JSON_THROW_ON_ERROR));
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

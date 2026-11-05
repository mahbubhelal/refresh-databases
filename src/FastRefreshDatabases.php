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
            } elseif ($this->usingInMemoryDatabase()) {
                $this->migrateInMemoryConnections();
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
            array_push($checksumData, ...$this->fileFingerprints($path, '*.php'));
        }

        array_push($checksumData, ...$this->fileFingerprints(database_path('schema'), '*.sql'));

        $checksumData['gitBranch'] = $this->currentGitBranch();

        return hash('sha256', json_encode($checksumData, JSON_THROW_ON_ERROR));
    }

    protected function getCachedMigrationChecksum(): ?string
    {
        $checksumFile = $this->getMigrationChecksumFile();

        if (!File::exists($checksumFile)) {
            return null;
        }

        return File::get($checksumFile);
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

    /**
     * @return list<array{int|false, string|false}>
     */
    private function fileFingerprints(string $directory, string $pattern): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = Finder::create()
            ->in($directory)
            ->name($pattern)
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
            ->files();

        $fingerprints = [];

        foreach ($files as $file) {
            $fingerprints[] = [$file->getMTime(), $file->getRealPath()];
        }

        return $fingerprints;
    }

    private function currentGitBranch(): string
    {
        $gitBranch = new Process(['git', 'branch', '--show-current']);
        $gitBranch->run();

        return trim($gitBranch->getOutput());
    }
}

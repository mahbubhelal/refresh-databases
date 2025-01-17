<?php

declare(strict_types=1);

namespace Tcb\FastRefreshDatabases\Command;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

class RemoveChecksum extends Command
{
    protected $signature = 'fast-refresh:remove-checksum';

    protected $description = 'Remove the checksum file from the storage folder';

    public function handle()
    {
        $connection = app(ConnectionInterface::class);

        $databaseNameSlug = Str::slug($connection->getDatabaseName());

        try {
            unlink(storage_path("app/migration-checksum_{$databaseNameSlug}.txt"));

            $this->info('Checksum file has been removed from the storage folder');
        } catch (Exception) {
            $this->warn('Checksum file not present in the storage folder');
        }
    }
}

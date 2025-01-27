<?php

declare(strict_types=1);

namespace Mahbub\RefreshDatabases\Command;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

class RemoveChecksum extends Command
{
    protected $signature = 'fast-refresh:remove-checksum';

    protected $description = 'Remove the checksum file from the storage folder';

    public function handle(): void
    {
        try {
            $files = array_keys(iterator_to_array(
                Finder::create()
                    ->in(storage_path('app'))
                    ->name('migration-checksum_*.txt')
                    ->ignoreDotFiles(true)
                    ->ignoreVCS(true)
                    ->files()
            ));

            if ($files === []) {
                throw new Exception;
            }

            File::delete($files);

            $this->info('Checksum file has been removed from the storage folder');
        } catch (Exception) {
            $this->warn('Checksum file not present in the storage folder');
        }
    }
}

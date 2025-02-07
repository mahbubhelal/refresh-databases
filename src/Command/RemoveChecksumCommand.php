<?php

declare(strict_types=1);

namespace Mahbub\RefreshDatabases\Command;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

class RemoveChecksumCommand extends Command
{
    protected $signature = 'refresh:remove-checksum';

    protected $description = 'Remove the checksum file from storage';

    public function handle(): int
    {
        $files = array_keys(iterator_to_array(
            Finder::create()
                ->in(storage_path('app'))
                ->name('migration-checksum_*.txt')
                ->ignoreDotFiles(true)
                ->ignoreVCS(true)
                ->files()
        ));

        if ($files === []) {
            $this->warn('Checksum file not present.');

            return 1;
        }

        File::delete($files);

        $this->info('Checksum file has been removed.');

        return 0;
    }
}

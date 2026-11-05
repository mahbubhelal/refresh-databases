<?php

declare(strict_types=1);

namespace Mahbub\RefreshDatabases\Command;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

#[Description('Remove the checksum file from storage')]
#[Signature('refresh-databases:remove-checksum')]
final class RemoveChecksumCommand extends Command
{
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

            return self::FAILURE;
        }

        File::delete($files);

        $this->info('Checksum file has been removed.');

        return self::SUCCESS;
    }
}

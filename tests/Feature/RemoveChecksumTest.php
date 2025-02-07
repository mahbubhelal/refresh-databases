<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

test('can remove checksum files from storage folder', function () {
    $file = File::partialMock();

    File::put(storage_path('app/migration-checksum_laravel.txt'), 'something');

    $file->shouldReceive('delete')
        ->once();

    $this->artisan('refresh:remove-checksum')
        ->expectsOutput('Checksum file has been removed.')
        ->assertOk();

    unlink(storage_path('app/migration-checksum_laravel.txt'));
});

test('does not remove checksum files from storage folder if not present', function () {
    $file = File::partialMock();

    $file->shouldNotReceive('delete');

    $this->artisan('refresh:remove-checksum')
        ->expectsOutput('Checksum file not present.')
        ->assertExitCode(1);
});

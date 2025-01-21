<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

test('can remove checksum files from storage folder', function () {
    $file = File::partialMock();

    File::put(storage_path('app/migration-checksum_laravel.txt'), 'something');

    $file->shouldReceive('delete')
        ->once();

    $this->artisan('fast-refresh:remove-checksum')
        ->assertOk();

    unlink(storage_path('app/migration-checksum_laravel.txt'));
});

test('does not remove checksum files from storage folder if not present', function () {
    $file = File::partialMock();

    $file->shouldNotReceive('delete');

    $this->artisan('fast-refresh:remove-checksum')
        ->assertExitCode(0);
});

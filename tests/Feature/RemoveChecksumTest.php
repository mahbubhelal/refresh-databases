<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

use function Pest\Laravel\artisan;

test('removes the checksum files from storage and nothing else', function (): void {
    $checksumFile = storage_path('app/migration-checksum_laravel.txt');
    $unrelatedFile = storage_path('app/unrelated.txt');
    File::put($checksumFile, 'something');
    File::put($unrelatedFile, 'something');

    artisan('refresh-databases:remove-checksum')
        ->expectsOutput('Checksum file has been removed.')
        ->assertOk();

    $unrelatedFileSurvived = File::exists($unrelatedFile);
    File::delete($unrelatedFile);

    expect(File::exists($checksumFile))->toBeFalse()
        ->and($unrelatedFileSurvived)->toBeTrue();
})->group('need_review');

test('fails when there is no checksum file to remove', function (): void {
    artisan('refresh-databases:remove-checksum')
        ->expectsOutput('Checksum file not present.')
        ->assertFailed();
})->group('need_review');

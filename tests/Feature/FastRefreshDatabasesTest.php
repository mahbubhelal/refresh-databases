<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\File;
use Mahbub\RefreshDatabases\FastRefreshDatabases;
use Mahbub\RefreshDatabases\FastRefreshDatabaseState;
use Mahbub\RefreshDatabases\Tests\Fixtures\Models\DefaultOne;
use Mahbub\RefreshDatabases\Tests\Fixtures\Models\Other\OtherOne;

uses(FastRefreshDatabases::class);

beforeAll(function () {
    RefreshDatabaseState::$migrated = false;
});

test('can refresh default connection', function () {
    DefaultOne::factory()->create();

    expect(DefaultOne::count())->toBe(1);
});

test('can refresh non default database connections', function () {
    OtherOne::factory()->create();

    expect(OtherOne::count())->toBe(1);
});

test('can refresh multiple database connections', function () {
    DefaultOne::factory()->create();
    OtherOne::factory()->create();

    expect(DefaultOne::count())->toBe(1);
    expect(OtherOne::count())->toBe(1);
});

test('does not migrate again if migration checksums have not changed', function () {
    FastRefreshDatabaseState::$cachedChecksum = '123';
    FastRefreshDatabaseState::$currentChecksum = '123';
    RefreshDatabaseState::$migrated = false;

    $class = new class
    {
        use FastRefreshDatabases;

        public static $called = 0;

        /** @var array<string> */
        protected $connectionsToTransact = ['default'];

        public function runIt()
        {
            $this->setMigrationPaths();
            $this->refreshTestDatabase();
        }

        protected function migrateConnections()
        {
            self::$called++;
        }

        protected function storeMigrationChecksum(string $checksum) {}

        protected function beginDatabaseTransaction() {}
    };

    $class->runIt();

    expect($class::$called)->toBe(0);

    RefreshDatabaseState::$migrated = true;
});

test('migrates again if checksum mismatched', function () {
    FastRefreshDatabaseState::$cachedChecksum = '123';
    FastRefreshDatabaseState::$currentChecksum = '124';
    RefreshDatabaseState::$migrated = false;

    $class = new class
    {
        use FastRefreshDatabases;

        public static $called = 0;

        /** @var array<string> */
        protected $connectionsToTransact = ['default'];

        public function runIt()
        {
            $this->setMigrationPaths();
            $this->refreshTestDatabase();
        }

        protected function migrateConnections()
        {
            self::$called++;
        }

        protected function storeMigrationChecksum(string $checksum) {}

        protected function beginDatabaseTransaction() {}
    };

    $class->runIt();

    expect($class::$called)->toBe(1);

    RefreshDatabaseState::$migrated = true;
});

test('can calculate checksum from migrations', function () {
    $class = new class
    {
        use FastRefreshDatabases;

        public static $checksum = '';

        public function runIt()
        {
            self::$checksum = $this->calculateMigrationChecksum();
        }
    };

    $class->runIt();

    expect($checksum = $class::$checksum)->not()->toBe('');

    $class->runIt();

    expect($class::$checksum)->toBe($checksum);

    $fakeFilePath = database_path('migrations/test.php');

    File::put($fakeFilePath, 'something');

    $class->runIt();

    File::delete($fakeFilePath);

    $checksumNew = $class::$checksum;

    expect($checksumNew)->not()->toBe($checksum);

    usleep(1000000);

    File::put($fakeFilePath, 'something else');

    $class->runIt();

    expect($class::$checksum)->not()->toBe($checksumNew);

    File::delete($fakeFilePath);
});

test('can store migration checksum', function () {
    $file = File::partialMock();

    $path = '';
    $content = '';

    $file->shouldReceive('put')
        ->with(Mockery::capture($path), Mockery::capture($content));

    $class = new class
    {
        use FastRefreshDatabases;

        public static $checksum = '';

        public function runIt()
        {
            $this->storeMigrationChecksum('123');
        }
    };

    $class->runIt();

    expect($path)->toBe(storage_path('app/migration-checksum_laravel.txt'))
        ->and($content)->toBe('123');
});

test('can get cached migration checksum', function () {
    $file = File::partialMock();

    $file->shouldReceive('get')->andReturn('123');

    $class = new class
    {
        use FastRefreshDatabases;

        public static $checksum = '';

        public function runIt()
        {
            self::$checksum = $this->getCachedMigrationChecksum();
        }
    };

    $class->runIt();

    expect($class::$checksum)->toBe('123');
});

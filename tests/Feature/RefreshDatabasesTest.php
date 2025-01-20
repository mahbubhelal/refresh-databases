<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Tcb\FastRefreshDatabases\Tests\Fixtures\Models\DefaultOne;
use Tcb\FastRefreshDatabases\Tests\Fixtures\Models\Other\OtherOne;

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

test('it can infer connectionsToTransact from migration directories', function () {
    $file = File::partialMock();

    $file->shouldReceive('exists')->once()->with(database_path('migrations'))->andReturn(true);
    $file->shouldReceive('directories')->once()->with(database_path('migrations'))->andReturn([database_path('migrations/other')]);

    $class = new class
    {
        use Tcb\FastRefreshDatabases\RefreshDatabases;

        public function beforeRefreshingDatabases()
        {
            $this->setConnectionsToTransact();
        }

        public function getConnectionsToTransact()
        {
            return $this->connectionsToTransact;
        }
    };

    $class->beforeRefreshingDatabases();

    expect($class->getConnectionsToTransact())
        ->toBe([
            database_path('migrations') => 'default',
            database_path('migrations/other') => 'other',
        ]);
});

test('it discards inferred connections if they are not configured', function () {
    $file = File::partialMock();

    $file->shouldReceive('exists')->once()->with(database_path('migrations'))->andReturn(true);
    $file->shouldReceive('directories')->once()->with(database_path('migrations'))->andReturn([database_path('migrations/another')]);

    $class = new class
    {
        use Tcb\FastRefreshDatabases\RefreshDatabases;

        public function beforeRefreshingDatabases()
        {
            $this->setConnectionsToTransact();
        }

        public function getConnectionsToTransact()
        {
            return $this->connectionsToTransact;
        }
    };

    $class->beforeRefreshingDatabases();

    expect($class->getConnectionsToTransact())
        ->toBe([
            database_path('migrations') => 'default',
        ]);
});

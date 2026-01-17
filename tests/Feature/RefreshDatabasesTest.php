<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Mahbub\RefreshDatabases\Tests\Fixtures\Models\DefaultOne;
use Mahbub\RefreshDatabases\Tests\Fixtures\Models\Other\OtherOne;

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

test('it can infer migrationPaths from migration directories', function () {
    $class = new class
    {
        use Mahbub\RefreshDatabases\RefreshDatabases;

        public function runIt()
        {
            $this->setMigrationPaths();
        }

        public function getMigrationPaths()
        {
            return $this->migrationPaths;
        }

        public function getConnectionsToTransact()
        {
            return $this->connectionsToTransact;
        }
    };

    $class->runIt();

    expect($class->getMigrationPaths())
        ->toBe([
            database_path('migrations') => 'default',
            database_path('migrations/other') => 'other',
        ]);

    expect($class->getConnectionsToTransact())
        ->toBe(['default', 'other']);
});

test('it discards inferred connections if they are not configured', function () {
    $class = new class
    {
        use Mahbub\RefreshDatabases\RefreshDatabases;

        public function runIt()
        {
            $this->setMigrationPaths();
        }

        public function getMigrationPaths()
        {
            return $this->migrationPaths;
        }

        public function getConnectionsToTransact()
        {
            return $this->connectionsToTransact;
        }
    };

    config(['database.connections.other' => null]);

    $class->runIt();

    expect($class->getMigrationPaths())
        ->toBe([
            database_path('migrations') => 'default',
        ]);

    expect($class->getConnectionsToTransact())
        ->toBe(['default']);
});

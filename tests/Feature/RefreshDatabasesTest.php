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

test('it can infer migrationPaths from connectionsToTransact', function () {
    $class = new class
    {
        use Mahbub\RefreshDatabases\RefreshDatabases;

        protected $connectionsToTransact = ['default', 'other'];

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
            'default' => database_path('migrations'),
            'other' => database_path('migrations/other'),
        ]);

    expect($class->getConnectionsToTransact())
        ->toBe(['default', 'other']);
});

test('it throws exception if connection is not configured', function () {
    $class = new class
    {
        use Mahbub\RefreshDatabases\RefreshDatabases;

        protected $connectionsToTransact = ['default', 'nonexistent'];

        public function runIt()
        {
            $this->setMigrationPaths();
        }
    };

    expect(fn () => $class->runIt())
        ->toThrow(RuntimeException::class, 'Database connection [nonexistent] is not defined.');
});

test('infers default migration path for default connection if connectionsToTransact is missing', function () {
    $class = new class
    {
        use Mahbub\RefreshDatabases\RefreshDatabases;

        public function runIt()
        {
            $this->setConnectionsToTransact();
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

    expect($class->getConnectionsToTransact())
        ->toBe([config('database.default')]);

    expect($class->getMigrationPaths())
        ->toBe([
            config('database.default') => database_path('migrations'),
        ]);
});

test('it uses migrationPaths property if defined', function () {
    $class = new class
    {
        use Mahbub\RefreshDatabases\RefreshDatabases;

        protected $connectionsToTransact = ['default', 'other'];

        protected $migrationPaths = [
            'other' => '/custom/path/for/other',
        ];

        public function runIt()
        {
            $this->setMigrationPaths();
        }

        public function getMigrationPaths()
        {
            return $this->migrationPaths;
        }
    };
    $class->runIt();

    expect($class->getMigrationPaths())
        ->toBe([
            'default' => database_path('migrations'),
            'other' => '/custom/path/for/other',
        ]);
});

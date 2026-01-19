<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Mahbub\RefreshDatabases\RefreshDatabases;
use Mahbub\RefreshDatabases\Tests\Fixtures\Models\DefaultOne;
use Mahbub\RefreshDatabases\Tests\Fixtures\Models\Other\OtherOne;

uses(RefreshDatabases::class);

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
        use RefreshDatabases;

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
        use RefreshDatabases;

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
        use RefreshDatabases;

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
        use RefreshDatabases;

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

test('can load seed file and execute statements', function () {
    $seedPath = database_path('schema/default-seed.sql');
    $seedContent = "INSERT INTO default_table_one (id, name) VALUES (99, 'seed_test');";

    File::put($seedPath, $seedContent);

    RefreshDatabaseState::$migrated = false;

    $this->refreshTestDatabase();

    $record = DB::connection('default')
        ->table('default_table_one')
        ->where('id', 99)
        ->first();

    File::delete($seedPath);

    expect($record)->not->toBeNull()
        ->and($record->name)->toBe('seed_test');
});

test('does nothing when seed file does not exist', function () {
    $seedPath = database_path('schema/default-seed.sql');

    expect(File::exists($seedPath))->toBeFalse();

    RefreshDatabaseState::$migrated = false;

    $this->refreshTestDatabase();

    expect(Schema::connection('default')->hasTable('default_table_one'))->toBeTrue();
});

<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Mahbub\RefreshDatabases\RefreshDatabases;
use Mahbub\RefreshDatabases\Tests\Fixtures\Models\DefaultOne;
use Mahbub\RefreshDatabases\Tests\Fixtures\Models\Other\OtherOne;

pest()->use(RefreshDatabases::class);

beforeAll(function (): void {
    RefreshDatabaseState::$migrated = false;
});

test('can refresh default connection', function (): void {
    DefaultOne::factory()->create();

    expect(DefaultOne::count())->toBe(1);
});

test('can refresh non default database connections', function (): void {
    OtherOne::factory()->create();

    expect(OtherOne::count())->toBe(1);
});

test('can refresh multiple database connections', function (): void {
    DefaultOne::factory()->create();
    OtherOne::factory()->create();

    expect(DefaultOne::count())->toBe(1);
    expect(OtherOne::count())->toBe(1);
});

test('it can infer migrationPaths from connectionsToTransact', function (): void {
    $class = new class
    {
        use RefreshDatabases;

        protected $connectionsToTransact = ['default', 'other'];

        public function runIt(): void
        {
            $this->setMigrationPaths();
        }

        public function resolvedMigrationPaths(): array
        {
            return $this->getMigrationPaths();
        }

        public function getConnectionsToTransact()
        {
            return $this->connectionsToTransact;
        }
    };

    $class->runIt();

    expect($class->resolvedMigrationPaths())
        ->toBe([
            'default' => database_path('migrations'),
            'other' => database_path('migrations/other'),
        ]);

    expect($class->getConnectionsToTransact())
        ->toBe(['default', 'other']);
})->group('need_review');

test('it throws exception if connection is not configured', function (): void {
    $class = new class
    {
        use RefreshDatabases;

        protected $connectionsToTransact = ['default', 'nonexistent'];

        public function runIt(): void
        {
            $this->setMigrationPaths();
        }
    };

    expect(fn () => $class->runIt())
        ->toThrow(RuntimeException::class, 'Database connection [nonexistent] is not defined.');
});

test('infers default migration path for default connection if connectionsToTransact is missing', function (): void {
    $class = new class
    {
        use RefreshDatabases;

        public function runIt(): void
        {
            $this->setMigrationPaths();
        }

        public function resolvedMigrationPaths(): array
        {
            return $this->getMigrationPaths();
        }
    };

    $class->runIt();

    expect($class->resolvedMigrationPaths())
        ->toBe([
            config('database.default') => database_path('migrations'),
        ]);
})->group('need_review');

test('adds no migrationPaths or connectionsToTransact to a test case that declares neither', function (): void {
    // PHP 8.2+ deprecates creating undeclared properties, so the resolved state must stay inside the trait.
    $class = new class
    {
        use RefreshDatabases;

        public function runIt(): void
        {
            $this->beforeRefreshingDatabase();
        }
    };

    $class->runIt();

    expect($class)
        ->not->toHaveProperty('migrationPaths')
        ->not->toHaveProperty('connectionsToTransact');
})->group('need_review');

test('it uses the paths migrationPaths() is overridden with', function (): void {
    $class = new class
    {
        use RefreshDatabases;

        protected $connectionsToTransact = ['default', 'other'];

        public function runIt(): void
        {
            $this->setMigrationPaths();
        }

        public function resolvedMigrationPaths(): array
        {
            return $this->getMigrationPaths();
        }

        /**
         * @return array<string, string>
         */
        protected function migrationPaths(): array
        {
            return ['other' => '/custom/path/for/other'];
        }
    };
    $class->runIt();

    expect($class->resolvedMigrationPaths())
        ->toBe([
            'default' => database_path('migrations'),
            'other' => '/custom/path/for/other',
        ]);
})->group('need_review');

test('can load seed file and execute statements', function (): void {
    $seedPath = database_path('schema/default-seed.sql');
    $seedContent = "INSERT INTO default_table_one (id, name) VALUES (99, 'seed_test');";

    File::put($seedPath, $seedContent);

    rollBackTestTransactions();

    RefreshDatabaseState::$migrated = false;

    $this->refreshTestDatabase();

    $record = DB::connection('default')
        ->table('default_table_one')
        ->where('id', 99)
        ->first();

    File::delete($seedPath);

    expect($record)->name->toBe('seed_test');
})->group('need_review');

test('does nothing when seed file does not exist', function (): void {
    $seedPath = database_path('schema/default-seed.sql');

    expect(File::exists($seedPath))->toBeFalse();

    rollBackTestTransactions();

    RefreshDatabaseState::$migrated = false;

    $this->refreshTestDatabase();

    expect(Schema::connection('default')->hasTable('default_table_one'))->toBeTrue();
});

test('migrateInMemoryConnections only migrates in memory connections', function (): void {
    $class = new class
    {
        use RefreshDatabases;

        public array $migratedConnections = [];

        public function runIt(): void
        {
            $this->migrateInMemoryConnections();
        }

        protected function getMigrationPaths(): array
        {
            return [
                'memory_conn' => '/fake/path',
                'disk_conn' => '/fake/path2',
            ];
        }

        protected function artisan($command, array $parameters = []): void
        {
            $this->migratedConnections[] = $parameters['--database'];
        }

        protected function loadSqlFile(string $connection, string $type): void {}

        protected function supportsSchemaLoading(string $connection): bool
        {
            return true;
        }

        protected function updateLocalCacheOfInMemoryDatabases(): void {}
    };

    config([
        'database.connections.memory_conn' => ['driver' => 'sqlite', 'database' => ':memory:'],
        'database.connections.disk_conn' => ['driver' => 'mysql', 'database' => 'real_db'],
    ]);

    $class->runIt();

    expect($class->migratedConnections)->toBe(['memory_conn']);
})->group('need_review');

test('migrateInMemoryConnections loads schema file for unsupported drivers', function (): void {
    $class = new class
    {
        use RefreshDatabases;

        public array $migratedConnections = [];

        public array $loadedSqlFiles = [];

        public function runIt(): void
        {
            $this->migrateInMemoryConnections();
        }

        protected function getMigrationPaths(): array
        {
            return [
                'memory_sqlsrv' => '/fake/path',
            ];
        }

        protected function artisan($command, array $parameters = []): void
        {
            $this->migratedConnections[] = $parameters['--database'];
        }

        protected function loadSqlFile(string $connection, string $type): void
        {
            $this->loadedSqlFiles[] = [$connection, $type];
        }

        protected function supportsSchemaLoading(string $connection): bool
        {
            return false;
        }

        protected function updateLocalCacheOfInMemoryDatabases(): void {}
    };

    config([
        'database.connections.memory_sqlsrv' => ['driver' => 'sqlsrv', 'database' => ':memory:'],
    ]);

    $class->runIt();

    expect($class->migratedConnections)->toBe(['memory_sqlsrv'])
        ->and($class->loadedSqlFiles)->toBe([
            ['memory_sqlsrv', 'schema'],
            ['memory_sqlsrv', 'views'],
            ['memory_sqlsrv', 'seed'],
        ]);
})->group('need_review');

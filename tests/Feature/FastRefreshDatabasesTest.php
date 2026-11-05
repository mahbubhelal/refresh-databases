<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\File;
use Mahbub\RefreshDatabases\FastRefreshDatabases;
use Mahbub\RefreshDatabases\FastRefreshDatabaseState;
use Mahbub\RefreshDatabases\Tests\Fixtures\Models\DefaultOne;
use Mahbub\RefreshDatabases\Tests\Fixtures\Models\Other\OtherOne;

pest()->use(FastRefreshDatabases::class);

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

test('does not migrate again if migration checksums have not changed', function (): void {
    FastRefreshDatabaseState::$cachedChecksum = '123';
    FastRefreshDatabaseState::$currentChecksum = '123';
    RefreshDatabaseState::$migrated = false;

    $class = new class
    {
        use FastRefreshDatabases;

        public static $called = 0;

        /** @var array<string> */
        protected $connectionsToTransact = ['default'];

        public function runIt(): void
        {
            $this->setMigrationPaths();
            $this->refreshTestDatabase();
        }

        protected function migrateConnections()
        {
            self::$called++;
        }

        protected function storeMigrationChecksum(string $checksum) {}

        protected function beginDatabaseTransaction(): void {}
    };

    $class->runIt();

    expect($class::$called)->toBe(0);

    RefreshDatabaseState::$migrated = true;
});

test('migrates again if checksum mismatched', function (): void {
    FastRefreshDatabaseState::$cachedChecksum = '123';
    FastRefreshDatabaseState::$currentChecksum = '124';
    RefreshDatabaseState::$migrated = false;

    $class = new class
    {
        use FastRefreshDatabases;

        public static $called = 0;

        /** @var array<string> */
        protected $connectionsToTransact = ['default'];

        public function runIt(): void
        {
            $this->setMigrationPaths();
            $this->refreshTestDatabase();
        }

        protected function migrateConnections()
        {
            self::$called++;
        }

        protected function storeMigrationChecksum(string $checksum) {}

        protected function beginDatabaseTransaction(): void {}
    };

    $class->runIt();

    expect($class::$called)->toBe(1);

    RefreshDatabaseState::$migrated = true;
});

test('can calculate checksum from migrations', function (): void {
    $class = new class
    {
        use FastRefreshDatabases;

        public static $checksum = '';

        /** @var array<string> */
        protected $connectionsToTransact = ['default'];

        public function runIt(): void
        {
            $this->setMigrationPaths();
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

    File::put($fakeFilePath, 'something else');
    touch($fakeFilePath, time() + 60);

    $class->runIt();

    expect($class::$checksum)->not()->toBe($checksumNew);

    File::delete($fakeFilePath);
})->group('need_review');

test('can store migration checksum', function (): void {
    $class = new class
    {
        use FastRefreshDatabases;

        public function runIt(): void
        {
            $this->storeMigrationChecksum('123');
        }
    };
    $checksumFile = storage_path('app/migration-checksum_laravel.txt');

    $class->runIt();

    $storedChecksum = File::get($checksumFile);
    File::delete($checksumFile);

    expect($storedChecksum)->toBe('123');
})->group('need_review');

test('can get cached migration checksum', function (): void {
    $class = new class
    {
        use FastRefreshDatabases;

        public function runIt(): ?string
        {
            $this->storeMigrationChecksum('123');

            try {
                return $this->getCachedMigrationChecksum();
            } finally {
                File::delete($this->getMigrationChecksumFile());
            }
        }
    };

    expect($class->runIt())->toBe('123');
})->group('need_review');

test('has no cached migration checksum before one is stored', function (): void {
    $class = new class
    {
        use FastRefreshDatabases;

        public function runIt(): ?string
        {
            return $this->getCachedMigrationChecksum();
        }
    };

    expect($class->runIt())->toBeNull();
})->group('need_review');

test('fails when the cached migration checksum cannot be read', function (): void {
    $class = new class
    {
        use FastRefreshDatabases;

        public function runIt(): ?string
        {
            $this->storeMigrationChecksum('123');
            chmod($this->getMigrationChecksumFile(), 0o000);

            try {
                return $this->getCachedMigrationChecksum();
            } finally {
                File::delete($this->getMigrationChecksumFile());
            }
        }
    };

    expect($class->runIt(...))->toThrow(ErrorException::class, 'Permission denied');
})->group('need_review');

test('checksum includes all configured migration paths', function (): void {
    $otherPath = database_path('migrations/other');

    File::ensureDirectoryExists($otherPath);

    $class = new class
    {
        use FastRefreshDatabases;

        public static $checksum = '';

        /** @var array<string> */
        protected $connectionsToTransact = ['default', 'other'];

        public function runIt(): void
        {
            $this->setMigrationPaths();
            self::$checksum = $this->calculateMigrationChecksum();
        }
    };

    $class->runIt();
    $checksumBefore = $class::$checksum;

    $fakeFilePath = $otherPath . '/test_migration.php';
    File::put($fakeFilePath, '<?php // test migration');

    $class->runIt();
    $checksumAfter = $class::$checksum;

    File::delete($fakeFilePath);

    expect($checksumAfter)->not()->toBe($checksumBefore);
});

test('checksum includes schema sql files', function (): void {
    $schemaPath = database_path('schema');

    File::ensureDirectoryExists($schemaPath);

    $class = new class
    {
        use FastRefreshDatabases;

        public static $checksum = '';

        /** @var array<string> */
        protected $connectionsToTransact = ['default'];

        public function runIt(): void
        {
            $this->setMigrationPaths();
            self::$checksum = $this->calculateMigrationChecksum();
        }
    };

    $class->runIt();
    $checksumBefore = $class::$checksum;

    $fakeFilePath = $schemaPath . '/default-views.sql';
    File::put($fakeFilePath, 'CREATE VIEW test AS SELECT 1;');

    $class->runIt();
    $checksumAfter = $class::$checksum;

    File::delete($fakeFilePath);

    expect($checksumAfter)->not()->toBe($checksumBefore);
});

test('migrates in memory connections when checksums match and database is in memory', function (): void {
    FastRefreshDatabaseState::$cachedChecksum = '123';
    FastRefreshDatabaseState::$currentChecksum = '123';
    RefreshDatabaseState::$migrated = false;

    $class = new class
    {
        use FastRefreshDatabases;

        public static $migrateConnectionsCalled = 0;

        public static $migrateInMemoryCalled = 0;

        protected $connectionsToTransact = ['default'];

        public function runIt(): void
        {
            $this->setMigrationPaths();
            $this->refreshTestDatabase();
        }

        protected function migrateConnections()
        {
            self::$migrateConnectionsCalled++;
        }

        protected function migrateInMemoryConnections()
        {
            self::$migrateInMemoryCalled++;
        }

        protected function usingInMemoryDatabase(): bool
        {
            return true;
        }

        protected function storeMigrationChecksum(string $checksum) {}

        protected function beginDatabaseTransaction(): void {}
    };

    $class->runIt();

    expect($class::$migrateConnectionsCalled)->toBe(0)
        ->and($class::$migrateInMemoryCalled)->toBe(1);

    RefreshDatabaseState::$migrated = true;
});

test('checksum handles missing migration paths gracefully', function (): void {
    $class = new class
    {
        use FastRefreshDatabases;

        public static $checksum = '';

        protected $connectionsToTransact = ['default', 'other'];

        public function runIt(): void
        {
            $this->setMigrationPaths();
            self::$checksum = $this->calculateMigrationChecksum();
        }

        /**
         * 'default' resolves to an existing directory, 'other' to one that does not exist.
         *
         * @return array<string, string>
         */
        protected function migrationPaths(): array
        {
            return ['other' => '/nonexistent/migrations/other'];
        }
    };

    $class->runIt();

    expect($class::$checksum)->not()->toBe('');
})->group('need_review');

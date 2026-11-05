<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Testing\ParallelTesting as ParallelTestingManager;
use Mahbub\RefreshDatabases\RefreshDatabases;

test('configureParallelDatabase skips when connection already configured', function (): void {
    $class = new class
    {
        use RefreshDatabases;

        protected $connectionsToTransact = ['skipconn'];

        public function runIt(): void
        {
            self::$originalDatabaseNames['skipconn'] = 'skipdb';
            $this->configureParallelDatabase('skipconn', '42');
        }

        public function getMap()
        {
            return self::$databaseNameMap;
        }
    };

    config(['database.connections.skipconn' => [
        'driver' => 'pgsql',
        'database' => 'skipdb',
    ]]);

    $class->runIt();

    expect($class->getMap())->not->toHaveKey('skipdb');
    expect(config('database.connections.skipconn.database'))->toBe('skipdb_test_42');
});

test('configureParallelDatabase skips memory databases', function (): void {
    $class = new class
    {
        use RefreshDatabases;

        protected $connectionsToTransact = ['sqlite'];

        public function runIt(): void
        {
            $this->configureParallelDatabase('sqlite', '1');
        }

        public function getMap()
        {
            return self::$databaseNameMap;
        }
    };

    config(['database.connections.sqlite' => [
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]]);

    $class->runIt();

    expect($class->getMap())->toBe([]);
});

test('configureParallelDatabase fails for a connection without a database name', function (): void {
    $class = new class
    {
        use RefreshDatabases;

        protected $connectionsToTransact = ['nulldb'];

        public function runIt(): void
        {
            $this->configureParallelDatabase('nulldb', '1');
        }
    };

    config(['database.connections.nulldb' => [
        'driver' => 'mysql',
        'database' => null,
    ]]);

    expect($class->runIt(...))
        ->toThrow(InvalidArgumentException::class, 'Configuration value for key [database.connections.nulldb.database] must be a string, NULL given.');
})->group('need_review');

test('replaceParallelDatabaseNames replaces SQL Server style names', function (): void {
    $class = new class
    {
        use RefreshDatabases;

        public function runIt(string $sql): string
        {
            self::$databaseNameMap = [
                'TCB' => 'TCB_test_1',
                'OtherDB' => 'OtherDB_test_1',
            ];

            return $this->replaceParallelDatabaseNames($sql);
        }
    };

    $sql = 'SELECT * FROM TCB.dbo.Users; INSERT INTO OtherDB.dbo.Items VALUES (1);';
    $result = $class->runIt($sql);

    expect($result)->toBe('SELECT * FROM TCB_test_1.dbo.Users; INSERT INTO OtherDB_test_1.dbo.Items VALUES (1);');
});

test('replaceParallelDatabaseNames replaces MySQL style names', function (): void {
    $class = new class
    {
        use RefreshDatabases;

        public function runIt(string $sql): string
        {
            self::$databaseNameMap = [
                'mydb' => 'mydb_test_1',
            ];

            return $this->replaceParallelDatabaseNames($sql);
        }

        public function reset(): void
        {
            self::$databaseNameMap = [];
        }
    };

    $sql = 'SELECT * FROM mydb.users;';
    $result = $class->runIt($sql);

    expect($result)->toBe('SELECT * FROM mydb_test_1.users;');

    $class->reset();
});

test('replaceParallelDatabaseNames fails when the database names cannot be replaced', function (): void {
    $class = new class
    {
        use RefreshDatabases;

        public function runIt(string $sql): string
        {
            self::$databaseNameMap = ['Uncompiled' => 'Uncompiled_test_1'];

            return $this->replaceParallelDatabaseNames($sql);
        }

        public function reset(): void
        {
            self::$databaseNameMap = [];
        }
    };
    // PHP caches compiled patterns, so the name must be one no other test has compiled with the JIT on.
    $jit = ini_set('pcre.jit', '0');
    $backtrackLimit = ini_set('pcre.backtrack_limit', '1');

    try {
        expect(fn (): string => $class->runIt('SELECT * FROM Uncompiled.dbo.Users;'))
            ->toThrow(RuntimeException::class, 'Could not replace the database name [Uncompiled] in the SQL file: Backtrack limit exhausted');
    } finally {
        ini_set('pcre.jit', (string) $jit);
        ini_set('pcre.backtrack_limit', (string) $backtrackLimit);
        $class->reset();
    }
})->group('need_review');

test('ensureParallelDatabaseExists handles unsupported drivers', function (): void {
    $class = new class
    {
        use RefreshDatabases;

        protected $connectionsToTransact = ['pgsqlconn'];

        public function runIt(string $connection, string $database): void
        {
            $this->ensureParallelDatabaseExists($connection, $database);
        }
    };

    config(['database.connections.pgsqlconn' => [
        'driver' => 'pgsql',
        'database' => 'testdb',
    ]]);

    $class->runIt('pgsqlconn', 'testdb_test_1');

    expect(config('database.connections.pgsqlconn.database'))->toBe('testdb');
})->group('need_review');

test('ensureParallelDatabaseExists creates mysql database', function (): void {
    $class = new class
    {
        use RefreshDatabases;

        public function runIt(string $connection, string $database): void
        {
            $this->ensureParallelDatabaseExists($connection, $database);
        }
    };

    $testDb = 'test_ensure_mysql_' . time();

    $class->runIt('default', $testDb);

    config(['database.connections.default.database' => null]);
    DB::purge('default');

    $exists = DB::connection('default')->select(
        'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?',
        [$testDb]
    );

    expect($exists)->toEqual([(object) ['SCHEMA_NAME' => $testDb]]);

    DB::connection('default')->statement("DROP DATABASE IF EXISTS `{$testDb}`");

    config(['database.connections.default.database' => 'laravel']);
    DB::purge('default');
})->group('need_review');

test('createMysqlDatabase creates database with correct charset', function (): void {
    $class = new class
    {
        use RefreshDatabases;

        protected $connectionsToTransact = ['default'];

        public function runIt(string $connection, string $database): void
        {
            $this->createMysqlDatabase($connection, $database);
        }
    };

    $testDb = 'test_parallel_' . time();

    $class->runIt('default', $testDb);

    config(['database.connections.default.database' => null]);
    DB::purge('default');

    $exists = DB::connection('default')->select(
        'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?',
        [$testDb]
    );

    expect($exists)->toEqual([(object) ['SCHEMA_NAME' => $testDb]]);

    DB::connection('default')->statement("DROP DATABASE IF EXISTS `{$testDb}`");

    config(['database.connections.default.database' => 'laravel']);
    DB::purge('default');
})->group('need_review');

test('ensureParallelDatabaseExists creates sqlsrv database', function (): void {
    $class = new class
    {
        use RefreshDatabases;

        public function runIt(string $connection, string $database): void
        {
            $this->ensureParallelDatabaseExists($connection, $database);
        }
    };

    $testDb = 'test_ensure_sqlsrv_' . time();

    $class->runIt('sqlsrv', $testDb);

    config(['database.connections.sqlsrv.database' => 'master']);
    DB::purge('sqlsrv');

    $exists = DB::connection('sqlsrv')->selectOne(
        'SELECT name FROM sys.databases WHERE name = ?',
        [$testDb]
    );

    expect($exists)->name->toBe($testDb);

    DB::connection('sqlsrv')->statement("DROP DATABASE [{$testDb}]");

    config(['database.connections.sqlsrv.database' => 'master']);
    DB::purge('sqlsrv');
})->group('need_review');

test('createSqlServerDatabase skips creation when database exists', function (): void {
    $class = new class
    {
        use RefreshDatabases;

        public function runIt(string $connection, string $database): void
        {
            $this->createSqlServerDatabase($connection, $database);
        }
    };

    expect(fn () => $class->runIt('sqlsrv', 'master'))->not->toThrow(Throwable::class);
})->group('need_review');

test('configureParallelDatabases loops through connections when token exists', function (): void {
    $mock = Mockery::mock(ParallelTestingManager::class)->makePartial();
    $mock->shouldReceive('token')->andReturn('99');
    ParallelTesting::swap($mock);

    $class = new class
    {
        use RefreshDatabases;

        protected $connectionsToTransact = ['parallelconn'];

        public function runIt(): void
        {
            $this->configureParallelDatabases();
        }

        public function getMap()
        {
            return self::$databaseNameMap;
        }
    };

    config(['database.connections.parallelconn' => [
        'driver' => 'pgsql', // unsupported driver to skip actual DB creation
        'database' => 'paralleldb',
    ]]);

    $class->runIt();

    expect($class->getMap())->toBe(['paralleldb' => 'paralleldb_test_99']);
});

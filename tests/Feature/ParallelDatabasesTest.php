<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Testing\ParallelTesting as ParallelTestingManager;
use Mahbub\RefreshDatabases\RefreshDatabases;

test('configureParallelDatabase skips when connection already configured', function () {
    $class = new class
    {
        use RefreshDatabases;

        protected $connectionsToTransact = ['skipconn'];

        public function runIt()
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

test('configureParallelDatabase skips memory databases', function () {
    $class = new class
    {
        use RefreshDatabases;

        protected $connectionsToTransact = ['sqlite'];

        public function runIt()
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

test('configureParallelDatabase skips null databases', function () {
    $class = new class
    {
        use RefreshDatabases;

        protected $connectionsToTransact = ['nulldb'];

        public function runIt()
        {
            $this->configureParallelDatabase('nulldb', '1');
        }

        public function getMap()
        {
            return self::$databaseNameMap;
        }
    };

    config(['database.connections.nulldb' => [
        'driver' => 'mysql',
        'database' => null,
    ]]);

    $class->runIt();

    expect($class->getMap())->toBe([]);
});

test('replaceParallelDatabaseNames replaces SQL Server style names', function () {
    $class = new class
    {
        use RefreshDatabases;

        public function runIt(string $sql)
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

test('replaceParallelDatabaseNames replaces MySQL style names', function () {
    $class = new class
    {
        use RefreshDatabases;

        public function runIt(string $sql)
        {
            self::$databaseNameMap = [
                'mydb' => 'mydb_test_1',
            ];

            return $this->replaceParallelDatabaseNames($sql);
        }

        public function reset()
        {
            self::$databaseNameMap = [];
        }
    };

    $sql = 'SELECT * FROM mydb.users;';
    $result = $class->runIt($sql);

    expect($result)->toBe('SELECT * FROM mydb_test_1.users;');

    $class->reset();
});

test('ensureParallelDatabaseExists handles unsupported drivers', function () {
    $class = new class
    {
        use RefreshDatabases;

        protected $connectionsToTransact = ['pgsqlconn'];

        public function runIt(string $connection, string $database)
        {
            $this->ensureParallelDatabaseExists($connection, $database);
        }
    };

    config(['database.connections.pgsqlconn' => [
        'driver' => 'pgsql',
        'database' => 'testdb',
    ]]);

    $class->runIt('pgsqlconn', 'testdb_test_1');

    expect(true)->toBeTrue();
});

test('ensureParallelDatabaseExists creates mysql database', function () {
    $class = new class
    {
        use RefreshDatabases;

        public function runIt(string $connection, string $database)
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

    expect($exists)->not->toBeEmpty();

    DB::connection('default')->statement("DROP DATABASE IF EXISTS `{$testDb}`");

    config(['database.connections.default.database' => 'laravel']);
    DB::purge('default');
});

test('createMysqlDatabase creates database with correct charset', function () {
    $class = new class
    {
        use RefreshDatabases;

        protected $connectionsToTransact = ['default'];

        public function runIt(string $connection, string $database)
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

    expect($exists)->not->toBeEmpty();

    DB::connection('default')->statement("DROP DATABASE IF EXISTS `{$testDb}`");

    config(['database.connections.default.database' => 'laravel']);
    DB::purge('default');
});

test('ensureParallelDatabaseExists creates sqlsrv database', function () {
    $class = new class
    {
        use RefreshDatabases;

        public function runIt(string $connection, string $database)
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

    expect($exists)->not->toBeNull();

    DB::connection('sqlsrv')->statement("DROP DATABASE [{$testDb}]");

    config(['database.connections.sqlsrv.database' => 'master']);
    DB::purge('sqlsrv');
});

test('createSqlServerDatabase skips creation when database exists', function () {
    $class = new class
    {
        use RefreshDatabases;

        public function runIt(string $connection, string $database)
        {
            $this->createSqlServerDatabase($connection, $database);
        }
    };

    $class->runIt('sqlsrv', 'master');

    expect(true)->toBeTrue();
});

test('configureParallelDatabases loops through connections when token exists', function () {
    $mock = Mockery::mock(ParallelTestingManager::class)->makePartial();
    $mock->shouldReceive('token')->andReturn('99');
    ParallelTesting::swap($mock);

    $class = new class
    {
        use RefreshDatabases;

        protected $connectionsToTransact = ['parallelconn'];

        public function runIt()
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

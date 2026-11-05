<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Mahbub\RefreshDatabases\RefreshDatabases;
use Mahbub\RefreshDatabases\Tests\Fixtures\Models\Sqlsrv\SqlsrvOne;

pest()->use(RefreshDatabases::class);

beforeAll(function (): void {
    RefreshDatabaseState::$migrated = false;
});

test('can refresh sqlsrv connection', function (): void {
    SqlsrvOne::factory()->create();

    expect(SqlsrvOne::count())->toBe(1);
});

test('rollback works between tests', function (): void {
    expect(SqlsrvOne::count())->toBe(0);

    SqlsrvOne::factory()->count(3)->create();

    expect(SqlsrvOne::count())->toBe(3);
});

test('load sqlsrv schema file when it exists', function (): void {
    $schemaPath = database_path('schema/sqlsrv-schema.sql');
    $schemaContent = 'CREATE TABLE sqlsrv_schema_test (id INT PRIMARY KEY, name NVARCHAR(255));';

    File::put($schemaPath, $schemaContent);

    rollBackTestTransactions();

    RefreshDatabaseState::$migrated = false;

    $this->refreshTestDatabase();

    $tableExists = Schema::connection('sqlsrv')
        ->hasTable('sqlsrv_schema_test');

    File::delete($schemaPath);

    expect($tableExists)->toBeTrue();
});

test('load seed file when it exists', function (): void {
    $seedPath = database_path('schema/sqlsrv-seed.sql');
    $seedContent = "INSERT INTO sqlsrv_table_one (name, created_at, updated_at) VALUES ('seeded_record', '2024-01-01', '2024-01-01')";

    File::put($seedPath, $seedContent);

    rollBackTestTransactions();

    RefreshDatabaseState::$migrated = false;

    $this->refreshTestDatabase();

    $record = DB::connection('sqlsrv')
        ->table('sqlsrv_table_one')
        ->where('name', 'seeded_record')
        ->first();

    File::delete($seedPath);

    expect($record)->name->toBe('seeded_record');
})->group('need_review');

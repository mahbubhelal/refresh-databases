<?php

declare(strict_types=1);

use Mahbub\RefreshDatabases\Tests\Fixtures\FakeTestCase;

/**
 * @return list<string>
 */
function splitSql(string $sql): array
{
    return new FakeTestCase('splitSql')->splitSqlStatements($sql);
}

test('splits a script into individual statements', function (): void {
    expect(splitSql("SELECT 1;\nSELECT 2;"))
        ->toBe(['SELECT 1', 'SELECT 2']);
});

test('trims statements and drops empty ones', function (): void {
    expect(splitSql("SELECT 1;   ;\n\n"))
        ->toBe(['SELECT 1']);
});

test('keeps the final statement when it has no trailing semicolon', function (): void {
    expect(splitSql('SELECT 1; SELECT 2'))
        ->toBe(['SELECT 1', 'SELECT 2']);
});

test('does not split on a semicolon inside a single-quoted string literal', function (): void {
    $sql = "CREATE VIEW v AS SELECT CASE WHEN x = 1 THEN '&euro;' ELSE '&dollar;' END AS c;\nSELECT 2;";

    expect(splitSql($sql))
        ->toBe([
            "CREATE VIEW v AS SELECT CASE WHEN x = 1 THEN '&euro;' ELSE '&dollar;' END AS c",
            'SELECT 2',
        ]);
});

test('treats a doubled quote as an escaped literal', function (): void {
    expect(splitSql("INSERT INTO t VALUES ('a;b''c;d'); SELECT 2;"))
        ->toBe([
            "INSERT INTO t VALUES ('a;b''c;d')",
            'SELECT 2',
        ]);
});

test('fails when the script cannot be split', function (): void {
    $backtrackLimit = ini_set('pcre.backtrack_limit', '1');

    try {
        expect(fn (): array => splitSql("INSERT INTO a VALUES ('x;y'); SELECT 1;"))
            ->toThrow(RuntimeException::class, 'Could not split the SQL file into statements: Backtrack limit exhausted');
    } finally {
        ini_set('pcre.backtrack_limit', (string) $backtrackLimit);
    }
})->group('need_review');

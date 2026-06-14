<?php

declare(strict_types=1);

use Mahbub\RefreshDatabases\RefreshDatabases;

function splitSql(string $sql): array
{
    return (new class
    {
        use RefreshDatabases;
    })->splitSqlStatements($sql);
}

test('splits a script into individual statements', function () {
    expect(splitSql("SELECT 1;\nSELECT 2;"))
        ->toBe(['SELECT 1', 'SELECT 2']);
});

test('trims statements and drops empty ones', function () {
    expect(splitSql("SELECT 1;   ;\n\n"))
        ->toBe(['SELECT 1']);
});

test('keeps the final statement when it has no trailing semicolon', function () {
    expect(splitSql('SELECT 1; SELECT 2'))
        ->toBe(['SELECT 1', 'SELECT 2']);
});

test('does not split on a semicolon inside a single-quoted string literal', function () {
    $sql = "CREATE VIEW v AS SELECT CASE WHEN x = 1 THEN '&euro;' ELSE '&dollar;' END AS c;\nSELECT 2;";

    expect(splitSql($sql))
        ->toBe([
            "CREATE VIEW v AS SELECT CASE WHEN x = 1 THEN '&euro;' ELSE '&dollar;' END AS c",
            'SELECT 2',
        ]);
});

test('treats a doubled quote as an escaped literal', function () {
    expect(splitSql("INSERT INTO t VALUES ('a;b''c;d'); SELECT 2;"))
        ->toBe([
            "INSERT INTO t VALUES ('a;b''c;d')",
            'SELECT 2',
        ]);
});

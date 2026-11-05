<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Mahbub\RefreshDatabases\Tests\TestCase;

pest()
    ->extends(TestCase::class)
    ->in('Feature');

function rollBackTestTransactions(): void
{
    foreach (DB::getConnections() as $connection) {
        $connection->rollBack(0);
    }
}

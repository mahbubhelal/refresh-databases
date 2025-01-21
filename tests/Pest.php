<?php

declare(strict_types=1);

use Tcb\FastRefreshDatabases\FastRefreshDatabases;
use Tcb\FastRefreshDatabases\RefreshDatabases;
use Tcb\FastRefreshDatabases\Tests\TestCase;

pest()
    ->extends(TestCase::class)
    ->in('Feature');

pest()
    ->use(RefreshDatabases::class)
    ->in('Feature/RefreshDatabasesTest.php');

pest()
    ->use(FastRefreshDatabases::class)
    ->in('Feature/FastRefreshDatabasesTest.php');

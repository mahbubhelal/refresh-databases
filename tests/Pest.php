<?php

declare(strict_types=1);

use Tcb\FastRefreshDatabases\RefreshDatabases;
use Tcb\FastRefreshDatabases\Tests\TestCase;

pest()
    ->extends(TestCase::class)
    ->use(RefreshDatabases::class)
    ->in('Feature/RefreshDatabasesTest.php');

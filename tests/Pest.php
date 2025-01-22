<?php

declare(strict_types=1);

use Mahbub\FastRefreshDatabases\FastRefreshDatabases;
use Mahbub\FastRefreshDatabases\RefreshDatabases;
use Mahbub\FastRefreshDatabases\Tests\TestCase;

pest()
    ->extends(TestCase::class)
    ->in('Feature');

pest()
    ->use(RefreshDatabases::class)
    ->in('Feature/RefreshDatabasesTest.php');

pest()
    ->use(FastRefreshDatabases::class)
    ->in('Feature/FastRefreshDatabasesTest.php');

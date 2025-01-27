<?php

declare(strict_types=1);

use Mahbub\RefreshDatabases\FastRefreshDatabases;
use Mahbub\RefreshDatabases\RefreshDatabases;
use Mahbub\RefreshDatabases\Tests\TestCase;

pest()
    ->extends(TestCase::class)
    ->in('Feature');

pest()
    ->use(RefreshDatabases::class)
    ->in('Feature/RefreshDatabasesTest.php');

pest()
    ->use(FastRefreshDatabases::class)
    ->in('Feature/FastRefreshDatabasesTest.php');

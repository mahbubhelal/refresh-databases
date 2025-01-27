<?php

declare(strict_types=1);

namespace Mahbub\RefreshDatabases\Tests\Fixtures;

use Mahbub\RefreshDatabases\FastRefreshDatabases;
use Orchestra\Testbench\TestCase as TestbenchTestCase;

/**
 * FakeTestCase fo phpstan to recognize the FastRefreshDatabases trait
 */
class FakeTestCase extends TestbenchTestCase
{
    use FastRefreshDatabases;
}

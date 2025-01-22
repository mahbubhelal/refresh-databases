<?php

declare(strict_types=1);

namespace Mahbub\FastRefreshDatabases\Tests\Fixtures;

use Mahbub\FastRefreshDatabases\FastRefreshDatabases;
use Orchestra\Testbench\TestCase as TestbenchTestCase;

/**
 * FakeTestCase fo phpstan to recognize the FastRefreshDatabases trait
 */
class FakeTestCase extends TestbenchTestCase
{
    use FastRefreshDatabases;
}

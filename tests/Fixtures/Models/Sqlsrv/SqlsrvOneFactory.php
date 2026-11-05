<?php

declare(strict_types=1);

namespace Mahbub\RefreshDatabases\Tests\Fixtures\Models\Sqlsrv;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Mahbub\RefreshDatabases\Tests\Fixtures\Models\Sqlsrv\SqlsrvOne>
 */
final class SqlsrvOneFactory extends Factory
{
    #[\Override]
    protected $model = SqlsrvOne::class;

    public function definition()
    {
        return [
            'name' => fake()->name,
        ];
    }
}

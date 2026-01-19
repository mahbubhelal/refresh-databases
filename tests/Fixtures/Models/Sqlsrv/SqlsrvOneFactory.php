<?php

declare(strict_types=1);

namespace Mahbub\RefreshDatabases\Tests\Fixtures\Models\Sqlsrv;

use Illuminate\Database\Eloquent\Factories\Factory;

final class SqlsrvOneFactory extends Factory
{
    protected $model = SqlsrvOne::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name,
        ];
    }
}

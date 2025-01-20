<?php

declare(strict_types=1);

namespace Tcb\FastRefreshDatabases\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Factories\Factory;

class DefaultOneFactory extends Factory
{
    protected $model = DefaultOne::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name,
        ];
    }
}

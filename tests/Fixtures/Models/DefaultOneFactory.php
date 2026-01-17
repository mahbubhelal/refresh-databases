<?php

declare(strict_types=1);

namespace Mahbub\RefreshDatabases\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Factories\Factory;

final class DefaultOneFactory extends Factory
{
    protected $model = DefaultOne::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name,
        ];
    }
}

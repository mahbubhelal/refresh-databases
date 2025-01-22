<?php

declare(strict_types=1);

namespace Mahbub\FastRefreshDatabases\Tests\Fixtures\Models\Other;

use Illuminate\Database\Eloquent\Factories\Factory;

class OtherOneFactory extends Factory
{
    protected $model = OtherOne::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name,
        ];
    }
}

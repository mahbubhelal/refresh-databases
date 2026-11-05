<?php

declare(strict_types=1);

namespace Mahbub\RefreshDatabases\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Mahbub\RefreshDatabases\Tests\Fixtures\Models\DefaultOne>
 */
final class DefaultOneFactory extends Factory
{
    #[\Override]
    protected $model = DefaultOne::class;

    public function definition()
    {
        return [
            'name' => fake()->name,
        ];
    }
}

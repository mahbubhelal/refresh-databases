<?php

declare(strict_types=1);

namespace Mahbub\RefreshDatabases\Tests\Fixtures\Models\Other;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Mahbub\RefreshDatabases\Tests\Fixtures\Models\Other\OtherOne>
 */
final class OtherOneFactory extends Factory
{
    #[\Override]
    protected $model = OtherOne::class;

    public function definition()
    {
        return [
            'name' => fake()->name,
        ];
    }
}

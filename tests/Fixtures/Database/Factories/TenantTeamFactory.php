<?php

namespace Baspa\FilamentCanary\Tests\Fixtures\Database\Factories;

use Baspa\FilamentCanary\Tests\Fixtures\Models\TenantTeam;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantTeamFactory extends Factory
{
    protected $model = TenantTeam::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
        ];
    }
}

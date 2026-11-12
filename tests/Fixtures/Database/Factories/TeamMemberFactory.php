<?php

namespace Baspa\FilamentCanary\Tests\Fixtures\Database\Factories;

use Baspa\FilamentCanary\Tests\Fixtures\Models\TeamMember;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamMemberFactory extends Factory
{
    protected $model = TeamMember::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => 'password',
        ];
    }
}

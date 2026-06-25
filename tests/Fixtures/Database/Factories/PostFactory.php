<?php

namespace Baspa\FilamentCanary\Tests\Fixtures\Database\Factories;

use Baspa\FilamentCanary\Tests\Fixtures\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
        ];
    }
}

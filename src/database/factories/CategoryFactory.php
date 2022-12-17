<?php

namespace Backpack\Store\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Backpack\Store\app\Models\Category;

class CategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
      return [
        'name' => $this->faker->sentence(),
        'slug' => $this->faker->uuid(),
        'images' => [
          [
            'src' => $this->faker->imageUrl(640, 480, 'Post', true),
            'alt' => 'alt',
            'title' => 'title'
          ]
        ],
      ];
    }
}

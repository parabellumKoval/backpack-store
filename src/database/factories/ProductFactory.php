<?php

namespace Backpack\Store\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Backpack\Store\app\Models\Product;

class ProductFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {

      return [
        'name' => $this->faker->sentence(),
        'short_name' => $this->faker->word(),
        'slug' => $this->faker->uuid(),
        'code' => $this->faker->regexify('[A-Z]{5}[0-4]{3}'),
        'price' => $this->faker->randomFloat(2, 0, 100000),
        'old_price' => $this->faker->randomElement(
          [
            $this->faker->randomFloat(2, 0, 100000), 
            null
          ]
        ),
        'images' => [
          [
            'src' => $this->faker->randomElement([
              'https://images.unsplash.com/photo-1557800636-894a64c1696f?q=80&w=1024&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
              'https://images.unsplash.com/photo-1528825871115-3581a5387919?q=80&w=1024&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
              'https://images.unsplash.com/photo-1559181567-c3190ca9959b?q=80&w=1024&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
              'https://images.unsplash.com/photo-1589820296156-2454bb8a6ad1?q=80&w=1024&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
              'https://images.unsplash.com/photo-1471943038886-87c772c31367?q=80&w=1024&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
            ]),
            'alt' => 'alt',
            'title' => 'title'
          ],[
            'src' => $this->faker->randomElement([
              'https://images.unsplash.com/photo-1557800636-894a64c1696f?q=80&w=1024&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
              'https://images.unsplash.com/photo-1528825871115-3581a5387919?q=80&w=1024&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
              'https://images.unsplash.com/photo-1559181567-c3190ca9959b?q=80&w=1024&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
              'https://images.unsplash.com/photo-1589820296156-2454bb8a6ad1?q=80&w=1024&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
              'https://images.unsplash.com/photo-1471943038886-87c772c31367?q=80&w=1024&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
            ]),
            'alt' => 'alt 2',
            'title' => 'title 2'
          ]
        ],
        'content' => $this->faker->text(),
        'is_active' => $this->faker->randomElement([0,1]),
        'in_stock' => $this->faker->randomElement([0,1]),
      ];
    }

    /**
     * Indicate that the user is suspended.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
    */
    public function suspended()
    {
        return $this->state(function (array $attributes) {
            return [
              'old_price' => $attributes['price'] * 1.2,
              'in_stock' => 
                $this->faker->randomElement([
                  0,
                  $this->faker->randomNumber(2, false),
                  $this->faker->randomNumber(2, false),
                  $this->faker->randomNumber(2, false)
                ])
            ];
        });
    }

}

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
        'slug' => $this->faker->uuid(),
        'lang' => config('app.locale', 'en'),
        'code' => $this->faker->regexify('[A-Z]{5}[0-4]{3}'),
        'price' => $this->faker->randomFloat(2, 0, 100000),
        'old_price' => $this->faker->randomElement(
          [
            $this->faker->randomFloat(2, 0, 100000), 
            null
          ]
        ),
        'images' => json_encode(
          [
            $this->faker->imageUrl(640, 480, 'Post', true), 
            $this->faker->imageUrl(640, 480, 'Post', true)
          ]
        ),
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
            ];
        });
    }

}

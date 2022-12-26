<?php

namespace Backpack\Store\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Backpack\Store\app\Models\Order;

class OrderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {

      return [
        'user_id' => $this->faker->randomElement([1, null]),
        'code' => $this->faker->regexify('[A-Z]{3}[0-4]{3}'),
        'price' => $this->faker->randomFloat(2, 0, 100000),
        'status' => $this->faker->randomElement(
          [
            'new',
            'pending',
            'complited',
            'fail'
          ]
        ),
        'is_paid' => $this->faker->randomElement([
          1, 0
        ]),
        'info' => [
          'name' => $this->faker->firstName(),
          'address' => $this->faker->address(),
          'city' => $this->faker->city(),
          'tel' => $this->faker->phoneNumber(),
          'email' => 'ex@ex.com',
          'payment' => $this->faker->randomElement(['visa', 'paypal']),
          'delivery' => $this->faker->randomElement(['shipping', 'courier']),
          'comment' => $this->faker->sentence(),
          'products' => [
            [
              'name' => $this->faker->sentence(),
              'slug' => $this->faker->uuid(),
              'amount' => $this->faker->randomDigit(),
              'price' => $this->faker->randomFloat(2, 0, 1000),
              'old_price' => $this->faker->randomFloat(2, 0, 1000),
              'image' => [
                'src' => $this->faker->imageUrl(640, 480, 'Post', true),
                'alt' => 'alt',
                'title' => 'title'
              ],
            ],[
              'name' => $this->faker->sentence(),
              'slug' => $this->faker->uuid(),
              'amount' => $this->faker->randomDigit(),
              'price' => $this->faker->randomFloat(2, 0, 1000),
              'old_price' => $this->faker->randomFloat(2, 0, 1000),
              'image' => [
                'src' => $this->faker->imageUrl(640, 480, 'Post', true),
                'alt' => 'alt',
                'title' => 'title'
              ],
            ]
          ]
        ],
      ];
    }

}

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
        'code' => $this->faker->regexify('[A-Z]{3}[0-4]{3}'),
        'price' => $this->faker->randomFloat(2, 0, 100000),
        'status' => $this->faker->randomElement([
          'new',
          'pending',
          'complited',
          'fail'
        ]),
        'pay_status' => $this->faker->randomElement([
          'waiting',
          'failed',
          'paied'
        ]),
        'delivery_status' => $this->faker->randomElement([
          'waiting',
          'sent',
          'failed',
          'delivered',
          'pickedup'
        ]),
        'info' => [
          'user' => [
            'firstname' => $this->faker->firstName(),
            'lastname' => $this->faker->lastName(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->email(),
          ],
          'delivery' => [
            'zip' => $this->faker->randomNumber(6, true),
            'room' => $this->faker->randomNumber(2, false),
            'house' => $this->faker->randomNumber(2, false),
            'method' => $this->faker->randomElement(['address', 'courier']),
            'street' => $this->faker->address(),
            'city' => $this->faker->city(),
            'warehouse' => null,
            'comment' => $this->faker->sentence(),
          ],
          'payment' => [
            'method' => $this->faker->randomElement(['cash', 'paypal']),
          ],
          'products' => [
            [
              'name' => $this->faker->sentence(),
              'slug' => $this->faker->uuid(),
              'amount' => $this->faker->randomDigit(),
              'price' => $this->faker->randomFloat(2, 0, 1000),
              'old_price' => $this->faker->randomFloat(2, 0, 1000),
              'image' => [
                'src' => $this->faker->randomElement([
                  'https://images.unsplash.com/photo-1557800636-894a64c1696f?q=80&w=1024&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                  'https://images.unsplash.com/photo-1528825871115-3581a5387919?q=80&w=1024&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                  'https://images.unsplash.com/photo-1559181567-c3190ca9959b?q=80&w=1024&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                  'https://images.unsplash.com/photo-1589820296156-2454bb8a6ad1?q=80&w=1024&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                  'https://images.unsplash.com/photo-1471943038886-87c772c31367?q=80&w=1024&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                ]),
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
                'src' => $this->faker->randomElement([
                  'https://images.unsplash.com/photo-1557800636-894a64c1696f?q=80&w=1024&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                  'https://images.unsplash.com/photo-1528825871115-3581a5387919?q=80&w=1024&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                  'https://images.unsplash.com/photo-1559181567-c3190ca9959b?q=80&w=1024&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                  'https://images.unsplash.com/photo-1589820296156-2454bb8a6ad1?q=80&w=1024&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                  'https://images.unsplash.com/photo-1471943038886-87c772c31367?q=80&w=1024&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                ]),
                'alt' => 'alt',
                'title' => 'title'
              ],
            ]
          ]
        ],
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
          $products = $attributes['info']['products'];

          $price = array_reduce($products, function($carry, $item) {
            return $carry + $item['price'] * $item['amount'];
          }, 0);

          return [
              'price' => round($price, 2),
          ];
        });
    }

}

<?php

namespace Backpack\Store\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Backpack\Store\app\Models\Promocode;

class PromocodeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Promocode::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {

      return [
        'code' => $this->faker->regexify('[A-Z]{5}[0-4]{3}'),
        'name' => $this->faker->sentence(),
        'type' => $this->faker->randomElement([
          'percent',
          'value'
        ]),
        'value' => $this->faker->numberBetween(5, 50),
        'limit' => $this->faker->randomDigit(),
        'valid_until' => $this->faker->dateTimeBetween('-1 week', '+1 week'),
        'is_active' => $this->faker->randomElement([0,1]),
        'used_times' => $this->faker->randomDigit()
      ];
    }

    /**
     * 
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
    */
    // public function suspended()
    // {
    // }

}

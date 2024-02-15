<?php

namespace Backpack\Store\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Backpack\Store\app\Models\AttributeProduct;

class AttributeProductFactory extends Factory
{
  /**
   * The name of the factory's corresponding model.
   *
   * @var string
   */
  protected $model = AttributeProduct::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
          'value' => $this->faker->randomNumber(8, false),
          'attribute_value_id' => null,
          'attribute_id' => 0,
          'product_id' => 0
        ];
    }

    /**
     * Indicate that the user is suspended.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
    */
    public function suspended()
    {
        // return $this->state(function (array $attributes) {
        //   $values = null;
        //   $default_value = null;

        //   // CHECKBOX and RADIO
        //   if($attributes['type'] === 'checkbox' || $attributes['type'] === 'radio') {
        //     $values = $this->faker->words(10);
        //     $default_value = $values[0];
        //   }
        //   // NUMBER TYPE
        //   elseif($attributes['type'] === 'number') {
        //     $values = null;
        //     $default_value = $this->faker->randomElement([
        //       null,
        //       $this->faker->words(3, true)
        //     ]);
        //   }
        //   // STRING TYPE
        //   elseif($attributes['type'] === 'string') {
        //     $values = null;
        //     $default_value = $this->faker->randomElement([
        //       null,
        //       $this->faker->numberBetween(0, 50)
        //     ]);
        //   }

        //   return [
        //     'values' => $values,
        //     'default_value' => $default_value,
        //   ];
        // });
    }
}

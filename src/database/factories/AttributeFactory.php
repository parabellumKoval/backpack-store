<?php

namespace Backpack\Store\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Backpack\Store\app\Models\Attribute;

class AttributeFactory extends Factory
{
  /**
   * The name of the factory's corresponding model.
   *
   * @var string
   */
  protected $model = Attribute::class;

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
          'content' => $this->faker->text(),
          'si' => null,
          'default_value' => null,
          'values' => null,
          'type' => $this->faker->randomElement([
            'checkbox',
            'radio',
            'number',
            'string'
          ]),

          'extras' => null,

          'is_important' => $this->faker->randomElement([1,0]),
          'is_active' => $this->faker->randomElement([1,0]),
          'in_filters' => $this->faker->randomElement([1,0]),
          'in_properties' => $this->faker->randomElement([1,0]),

          'parent_id' => 0,
          'lft' => 0,
          'rgt' => 0,
          'depth' => 0
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
          $values = null;
          $default_value = null;

          // CHECKBOX and RADIO
          if($attributes['type'] === 'checkbox' || $attributes['type'] === 'radio') {
            $values = $this->faker->words(10);
            $default_value = $values[0];
          }
          // NUMBER TYPE
          elseif($attributes['type'] === 'number') {
            $values = null;
            $default_value = $this->faker->randomElement([
              null,
              $this->faker->words(3, true)
            ]);
          }
          // STRING TYPE
          elseif($attributes['type'] === 'string') {
            $values = null;
            $default_value = $this->faker->randomElement([
              null,
              $this->faker->numberBetween(0, 50)
            ]);
          }

          return [
            'values' => $values,
            'default_value' => $default_value,
          ];
        });
    }
}

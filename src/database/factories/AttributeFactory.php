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
          'name' => $this->faker->words(2, true),
          'slug' => $this->faker->uuid(),
          'content' => $this->faker->text(),

          'type' => $this->faker->randomElement([
            'checkbox',
            'radio',
            'number'
          ]),

          'extras' => null,
          'extras_trans' => json_encode([
            'si' => $this->faker->regexify('[a-z]{2}\.')
          ]),

          'is_active' => true,
          'in_filters' => $this->faker->randomElement([1,0]),
          'in_properties' => $this->faker->randomElement([1,0]),

          'parent_id' => null,
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
          $extras = null;

          if($attributes['type'] === 'number') {
            $extras = [
              'min' => 0,
              'max' => $this->faker->randomElement([100, 500, 1000]),
              'step' => $this->faker->randomElement([0.1, 1, 10])
            ];

            $extras['default_value'] = rand($extras['min'], $extras['max']);
            
            $attributes['extras'] = json_encode($extras, true);
          }

          return [
            'extras' => $extras,
          ];
        });
    }
}

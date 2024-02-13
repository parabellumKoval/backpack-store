<?php

namespace Backpack\Store\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Backpack\Store\app\Models\AttributeValue;

class AttributeValueFactory extends Factory
{
  /**
   * The name of the factory's corresponding model.
   *
   * @var string
   */
  protected $model = AttributeValue::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
          'value' => $this->faker->word(),
          'attribute_id' => null,
        ];
    }

    /**
     * Indicate that the user is suspended.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
    */
    public function suspended()
    {
    }
}

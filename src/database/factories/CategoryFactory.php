<?php

namespace Backpack\Store\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Backpack\Store\app\Models\Category;
use \Illuminate\Support\Facades\Http;

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
      $response = Http::get('https://picsum.photos/1024/1024?nocache='.microtime());
      $image_src = $response->effectiveUri()->__toString();

      // dd($image_src);
      return [
        'name' => $this->faker->sentence(),
        'slug' => $this->faker->uuid(),
        'images' => [
          [
            'src' => null,
            'alt' => 'alt',
            'title' => 'title'
          ]
        ],
      ];
    }
}

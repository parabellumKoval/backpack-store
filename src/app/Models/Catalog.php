<?php

namespace Backpack\Store\app\Models;

// use Illuminate\Database\Eloquent\Model;

class Catalog
{

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    // protected $table = 'ak_carts';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    // protected $guarded = ['id'];
    // protected $fillable = [];
    // protected $hidden = [];
    // protected $dates = [];

    const DEFAULT_BY = 'created_at';
    const DEFAULT_DIR = 'desc';
    
    /**
     * Method getSortingData
     *
     * @return void
     */
    public static function getSortingData() {
      return [
        [
          'by' => 'created_at',
          'dir' => 'desc',
          'caption' => __('backpack-store::filter.sorting.news_desc')
        ],[
          'by' => 'created_at',
          'dir' => 'asc',
          'caption' => __('backpack-store::filter.sorting.news_asc')
        ],[
          'by' => 'price',
          'dir' => 'asc',
          'caption' => __('backpack-store::filter.sorting.price_asc')
        ], [
          'by' => 'price',
          'dir' => 'desc',
          'caption' => __('backpack-store::filter.sorting.price_desc')
        ],[
          'by' => 'in_stock',
          'dir' => 'desc',
          'caption' => __('backpack-store::filter.sorting.in_stock_desc')
        ],[
          'by' => 'in_stock',
          'dir' => 'asc',
          'caption' => __('backpack-store::filter.sorting.in_stock_asc')
        ]
      ];
    }
    
    /**
     * Method getSortingDataWithActive
     *
     * @param $by $by [explicite description]
     * @param $dir $dir [explicite description]
     *
     * @return void
     */
    public static function getSortingDataWithActive($by, $dir) {
      $by = $by? $by: self::DEFAULT_BY;
      $dir = $dir? $dir: self::DEFAULT_DIR;

      $options = self::getSortingData();
      $options_map = array_map(function($item) use($by, $dir) {
        $item['active'] = $item['by'] === $by && $item['dir'] === $dir? true: false;
        return $item;
      }, $options);

      return $options_map;
    }

    public static function getCacheCases() {
      $config = config('backpack.store.cache.cases');
      return $config;
    }
}
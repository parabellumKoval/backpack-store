<?php

namespace Backpack\Store\app\Traits;

trait Resources {
  
  protected static $resources = [
    'product' => [
      'tiny' => '',
      'small' => '',
      'medium' => '',
      'large' => '',
      'cart' => '',
    ],
    'category' => [
      'tiny' => '',
      'small' => '',
      'large' => '',
    ],
    'attribute' => [
      'small' => '',
      'large' => '',
      'product' => '',
    ],
    'order' => [
      'large' => '',
    ],
    'promocode' => [
      'small' => '',
      'large' => ''
    ],
    'brand' => [
      'small' => '',
      'large' => '',
      'product' => ''
    ]
  ];

  // No realization
  protected static $models = [];

  public static function resources_init()
  {
    self::$resources['product']['tiny'] = config('backpack.store.product.resource.tiny', 'Backpack\Store\app\Http\Resources\ProductTinyResource');
    self::$resources['product']['small'] = config('backpack.store.product.resource.small', 'Backpack\Store\app\Http\Resources\ProductSmallResource');
    self::$resources['product']['medium'] = config('backpack.store.product.resource.medium', 'Backpack\Store\app\Http\Resources\ProductMediumResource');
    self::$resources['product']['large'] = config('backpack.store.product.resource.large', 'Backpack\Store\app\Http\Resources\ProductLargeResource');
    self::$resources['product']['cart'] = config('backpack.store.product.resource.cart', 'Backpack\Store\app\Http\Resources\ProductCartResource');

    self::$resources['category']['tiny'] = config('backpack.store.category.resource.tiny', 'Backpack\Store\app\Http\Resources\CategoryTinyResource');
    self::$resources['category']['small'] = config('backpack.store.category.resource.small', 'Backpack\Store\app\Http\Resources\CategorySmallResource');
    self::$resources['category']['large'] = config('backpack.store.category.resource.large', 'Backpack\Store\app\Http\Resources\CategoryLargeResource');

    self::$resources['attribute']['small'] = config('backpack.store.attribute.resource.small', 'Backpack\Store\app\Http\Resources\AttributeSmallResource');
    self::$resources['attribute']['large'] = config('backpack.store.attribute.resource.large', 'Backpack\Store\app\Http\Resources\AttributeLargeResource');
    self::$resources['attribute']['product'] = config('backpack.store.attribute.resource.product', 'Backpack\Store\app\Http\Resources\AttributeProductResource');

    self::$resources['order']['large'] = config('backpack.store.order.resource.large', 'Backpack\Store\app\Http\Resources\OrderLargeResource');

    self::$resources['brand']['small'] = config('backpack.store.brand.resource.small', 'Backpack\Store\app\Http\Resources\BrandSmallResource');
    self::$resources['brand']['large'] = config('backpack.store.brand.resource.large', 'Backpack\Store\app\Http\Resources\BrandLargeResource');
    self::$resources['brand']['product'] = config('backpack.store.brand.resource.product', 'Backpack\Store\app\Http\Resources\BrandProductResource');
    self::$resources['brand']['filter'] = config('backpack.store.brand.resource.product', 'Backpack\Store\app\Http\Resources\BrandFilterResource');

    self::$resources['promocode']['small'] = config('backpack.store.promocode.resource.small', 'Backpack\Store\app\Http\Resources\PromocodeSmallResource');
    self::$resources['promocode']['large'] = config('backpack.store.promocode.resource.large', 'Backpack\Store\app\Http\Resources\PromocodeLargeResource');
  }
}
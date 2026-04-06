<?php

namespace Backpack\Store\app\Traits;

trait Resources {
  
  protected static $resources = [
    'product' => [
      'tiny' => '',
      'small' => '',
      'medium' => '',
      'large' => '',
      'kratom_small' => '',
      'kratom_large' => '',
      'cart' => '',
      'mod' => '',
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
    self::$resources['product']['tiny'] = \Settings::get('dress.product.resource.tiny', 'Backpack\Store\app\Http\Resources\ProductTinyResource');
    self::$resources['product']['small'] = \Settings::get('dress.product.resource.small', 'Backpack\Store\app\Http\Resources\ProductSmallResource');
    self::$resources['product']['medium'] = \Settings::get('dress.product.resource.medium', 'Backpack\Store\app\Http\Resources\ProductMediumResource');
    self::$resources['product']['large'] = \Settings::get('dress.product.resource.large', 'Backpack\Store\app\Http\Resources\ProductLargeResource');
    self::$resources['product']['kratom_small'] = \Settings::get('dress.product.resource.kratom_small', 'Backpack\Store\app\Http\Resources\ProductKratomSmallResource');
    self::$resources['product']['kratom_large'] = \Settings::get('dress.product.resource.kratom_large', 'Backpack\Store\app\Http\Resources\ProductKratomLargeResource');
    self::$resources['product']['cart'] = \Settings::get('dress.product.resource.cart', 'Backpack\Store\app\Http\Resources\ProductCartResource');
    self::$resources['product']['mod'] = \Settings::get('dress.product.resource.mod', 'Backpack\Store\app\Http\Resources\ProductModificationResource');

    self::$resources['category']['tiny'] = \Settings::get('dress.category.resource.tiny', 'Backpack\Store\app\Http\Resources\CategoryTinyResource');
    self::$resources['category']['small'] = \Settings::get('dress.category.resource.small', 'Backpack\Store\app\Http\Resources\CategorySmallResource');
    self::$resources['category']['large'] = \Settings::get('dress.category.resource.large', 'Backpack\Store\app\Http\Resources\CategoryLargeResource');

    self::$resources['attribute']['small'] = \Settings::get('dress.attribute.resource.small', 'Backpack\Store\app\Http\Resources\AttributeSmallResource');
    self::$resources['attribute']['large'] = \Settings::get('dress.attribute.resource.large', 'Backpack\Store\app\Http\Resources\AttributeLargeResource');
    self::$resources['attribute']['product'] = \Settings::get('dress.attribute.resource.product', 'Backpack\Store\app\Http\Resources\AttributeProductResource');

    self::$resources['order']['large'] = \Settings::get('dress.order.resource.large', 'Backpack\Store\app\Http\Resources\OrderLargeResource');

    self::$resources['brand']['small'] = \Settings::get('dress.brand.resource.small', 'Backpack\Store\app\Http\Resources\BrandSmallResource');
    self::$resources['brand']['large'] = \Settings::get('dress.brand.resource.large', 'Backpack\Store\app\Http\Resources\BrandLargeResource');
    self::$resources['brand']['product'] = \Settings::get('dress.brand.resource.product', 'Backpack\Store\app\Http\Resources\BrandProductResource');
    self::$resources['brand']['filter'] = \Settings::get('dress.brand.resource.product', 'Backpack\Store\app\Http\Resources\BrandFilterResource');
    self::$resources['brand']['filter_tiny'] = \Settings::get('dress.brand.resource.product', 'Backpack\Store\app\Http\Resources\BrandFilterTinyResource');

    self::$resources['promocode']['small'] = \Settings::get('dress.promocode.resource.small', 'Backpack\Store\app\Http\Resources\PromocodeSmallResource');
    self::$resources['promocode']['large'] = \Settings::get('dress.promocode.resource.large', 'Backpack\Store\app\Http\Resources\PromocodeLargeResource');
  }

  protected static function resolveResourceClass(string $group, ?string $requestedKey = null, ?string $fallbackKey = null): string
  {
    self::resources_init();

    $groupResources = self::$resources[$group] ?? [];
    $normalizedKey = is_string($requestedKey) ? trim($requestedKey) : '';

    if ($normalizedKey !== '' && !empty($groupResources[$normalizedKey])) {
      return $groupResources[$normalizedKey];
    }

    $fallback = $fallbackKey && !empty($groupResources[$fallbackKey])
      ? $fallbackKey
      : array_key_first(array_filter($groupResources));

    return $fallback ? $groupResources[$fallback] : '';
  }
}

<?php

return [
    'currency_default' => 'usd',
    
    'enable_modifications' => false, // false = only base modification
    'enable_complectations' => false,
    'enable_product_promotions' => false,
    'enable_attribute_groups' => false,
    'enable_attribute_icon' => false,

    // CATALOG
    'per_page' => 12,

    // GUARD
    'auth_guard' => 'profile',
    
    // USER
    'user_model' => 'Backpack\Profile\app\Models\Profile',

    // REVIEW
    'review_model' => 'Backpack\Reviews\app\Models\Review',
    'enable_reviews_in_product_crud' => true,

    // ORDER
    'order_model' => 'Backpack\Store\app\Models\Order',
    'enable_orders_in_product_crud' => true,
    'order_per_page' => 12,

    // CATEGORIES
    'category_depth_level' => 3,
    'enable_product_category_pages' => false,
    'is_modifications_category' => true,
    'category_per_page' => 12,

    // PROPUCT
    // PRODUCT -> properties
    // in stock
    'enable_in_stock' => true,
    'enable_in_stock_count' => false, // true = numeric, false = boolean
    
    //is hit
    'enable_is_hit' => false,

    // rating
    'enable_product_rating' => true,

    // price
    'enable_product_price' => true,

    // old price
    'enable_product_old_price' => true,

    // images
    'enable_multiple_product_images' => false, // false = one image per product

    // PRODUCT -> resources
    'product_tiny_resource' => 'Backpack\Store\app\Http\Resources\ProductTinyResource',
    
    // Small product resource used for catalog pages (index route)
    'product_small_resource' => 'Backpack\Store\app\Http\Resources\ProductSmallResource',
    'product_medium_resource' => 'Backpack\Store\app\Http\Resources\ProductMediumResource',
    
    // Large product resource used for product page (show route)
    'product_large_resource' => 'Backpack\Store\app\Http\Resources\ProductLargeResource',

    // BRANDS
    'enable_brands' => false,

    // ATTRIBUTES
    'enable_attributes' => true,
];

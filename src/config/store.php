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
    
    // USER
    'user_model' => 'App\Models\User',

    // REVIEW
    'review_model' => 'Backpack\Reviews\app\Models\Review',
    'enable_reviews_in_product_crud' => true,

    // ORDER
    'enable_orders_in_product_crud' => true,

    // CATEGORIES
    'category_depth_level' => 3,
    'enable_product_category_pages' => false,
    'is_modifications_category' => true,

    // PROPUCT PROPERTIES
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

    // BRANDS
    'enable_brands' => false,

    // ATTRIBUTES
    'enable_attributes' => true,
];

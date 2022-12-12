<?php

return [
    'is_modifications_category' => true,
    'currency_default' => 'usd',
    'enable_in_stock' => true,
    'enable_in_stock_count' => false, // true = numeric, false = boolean
    'enable_modifications' => false, // false = only base modification
    'enable_multiple_product_images' => false, // false = one image per product
    'enable_complectations' => false,
    'enable_product_promotions' => false,
    'enable_is_hit' => false,
    'enable_brands' => false,
    'enable_attribute_groups' => false,
    'enable_attribute_icon' => false,
    'enable_product_rating' => true,
    'enable_product_category_pages' => false,
    'per_page' => 2,

    'user_model' => 'App\Models\User',
    'category_depth_level' => 3
];

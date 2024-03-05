<?php

return [
    'currency' => [
      'value' => 'usd',
      'symbol' => '$',
    ],
    
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
    
    'order' => [

      'enable_bonus' => false,

      'per_page' => 12,

      'resource' => [
        'large' => 'Backpack\Store\app\Http\Resources\OrderLargeResource',
      ],

      // Common order statuses
      'status' => [
        'default' => 'new',
        'values' => ['new', 'canceled', 'failed', 'completed']
      ],
      // Payment statuses
      'pay_status' => [
        'default' => 'waiting',
        'values' => ['waiting', 'failed', 'paied']
      ],
      // Delivery statuses 
      'delivery_status' => [
        'default' => 'waiting',
        'values' => ['waiting', 'sent', 'failed', 'delivered', 'pickedup']
      ],
      // Validation fields
      'fields' => [
        'orderable_id' => [
          'rules' => 'nullable|uuid',
        ],

        'orderable_type' => [
          'rules' => 'nullable|max:255',
        ],

        'provider' => [
          'rules' => 'required|in:auth,data,outer',
          'store_in' => 'info'
        ],
  
        'payment' => [
          'rules' => 'array:method,status',
          'store_in' => 'info',
          'method' => [
            'rules' => 'required|in:liqpay,cash'
          ]
        ],
        
        'delivery' => [
          'rules' => 'array:city,address,zip,method,warehouse',
          'store_in' => 'info',
          'method' => [
            'rules' => 'required|in:address,warehouse,pickup'
          ],
          'warehouse' => [
            'rules' => 'required_if:delivery.method,warehouse|string|min:1|max:500'
          ],
          'city' => [
            'rules' => 'required_if:delivery.method,address,warehouse|string|min:2|max:255'
          ],
          'address' => [
            'rules' => 'required_if:delivery.method,address|string|min:2|max:255'
          ],
          'zip' => [
            'rules' => 'required_if:delivery.method,address|string|min:5|max:255'
          ],
        ],
        
        'products' => [
          'rules' => 'required|array',
          'hidden' => true,
        ],
        
        'bonusesUsed' => [
          'rules' => 'nullable|numeric',
          'store_in' => 'info'
        ],
        
        'promocode' => [
          'rules' => 'nullable',
          'store_in' => 'info'
        ],
  
        'user' => [
          'rules' => 'array:firstname,lastname,phone,email',
          'store_in' => 'info',
          'firstname' => [
            'rules' => 'required_if:provider,data|string|min:2|max:150'
          ],
          'lastname' => [
            'rules' => 'nullable|string|min:2|max:150'
          ],
          'phone' => [
            'rules' => 'required_if:provider,data|string|min:2|max:80'
          ],
          'email' => [
            'rules' => 'required_if:provider,data|email|min:2|max:150'
          ],
        ]
      ]
    ],

    // CATEGORIES
    'category' => [
      'depth_level' => 3,

      'per_page' => 12,

      'resource' => [
        'tiny' => 'Backpack\Store\app\Http\Resources\CategoryTinyResource',

        'small' => 'Backpack\Store\app\Http\Resources\CategorySmallResource',

        'large' => 'Backpack\Store\app\Http\Resources\CategoryLargeResource',
      ]
    ],

    // PROPUCT
    'product' => [
      'class' => 'Backpack\Store\app\Models\Product',

      'seo' => [
        'enable' => true
      ],

      'image' => [
        'enable' => true
      ],

      'code' => [
        'enable' => true
      ],

      'price' => [
        'enable' => true
      ],
      
      'old_price' => [
        'enable' => true
      ],
      
      'modifications' => [
        'enable' => false
      ],

      'in_stock' => [
        'enable' => true,
        'fixed' => false
      ],


      'resource' => [
        // PRODUCT -> resources
        'tiny' => 'Backpack\Store\app\Http\Resources\ProductTinyResource',
        
        // Small product resource used for catalog pages (index route)
        'small' => 'Backpack\Store\app\Http\Resources\ProductSmallResource',
        'medium' => 'Backpack\Store\app\Http\Resources\ProductMediumResource',
        
        // Large product resource used for product page (show route)
        'large' => 'Backpack\Store\app\Http\Resources\ProductLargeResource',
    
        // Cart product resource used for order
        'cart' => 'Backpack\Store\app\Http\Resources\ProductCartResource',
      ]
    ],

    // ATTRIBUTES
    'attribute' => [
      'enable' => true,

      // Is pivot values translatable
      'translatable_value' => false,

      'enable_icon' => false,

      'resource' => [

        'product' => 'Backpack\Store\app\Http\Resources\AttributeProductResource',

        'large' => 'Backpack\Store\app\Http\Resources\AttributeLargeResource',

        'small' => 'Backpack\Store\app\Http\Resources\AttributeSmallResource'
      ]
    ],

    // BRAND
    'brands' => [
      'enable' => true,

      'resource' => [

        'large' => 'Backpack\Store\app\Http\Resources\BrandLargeResource',

        'small' => 'Backpack\Store\app\Http\Resources\BrandSmallResource'
      ]
    ],

    // PRODUCT -> properties
    
    //is hit
    'enable_is_hit' => false,

    // rating
    'enable_product_rating' => true,


    // PRODUCT -> resources
    'product_tiny_resource' => 'Backpack\Store\app\Http\Resources\ProductTinyResource',
    
    // Small product resource used for catalog pages (index route)
    'product_small_resource' => 'Backpack\Store\app\Http\Resources\ProductSmallResource',
    'product_medium_resource' => 'Backpack\Store\app\Http\Resources\ProductMediumResource',
    
    // Large product resource used for product page (show route)
    'product_large_resource' => 'Backpack\Store\app\Http\Resources\ProductLargeResource',

    // Cart product resource used for order
    'product_cart_resource' => 'Backpack\Store\app\Http\Resources\ProductCartResource',

    // BRANDS
    'enable_brands' => false,
];

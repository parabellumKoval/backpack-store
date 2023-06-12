<?php

Route::group([
    'prefix'     => config('backpack.base.route_prefix', 'admin'),
    'middleware' => ['web', config('backpack.base.middleware_key', 'admin')],
    'namespace'  => 'Backpack\Store\app\Http\Controllers\Admin',
], function () { // custom admin routes
    Route::crud('product', 'ProductCrudController');
    Route::crud('category', 'CategoryCrudController');
    // Route::crud('attribute_group', 'AttributeGroupCrudController');
    // Route::crud('delivery', 'DeliveryCrudController');
    // Route::crud('payment', 'PaymentCrudController');
    Route::crud('order', 'OrderCrudController');
    Route::crud('promocode', 'PromocodeCrudController');
    
    if(config('backpack.store.attributes.enable')) {
      Route::crud('attribute', 'AttributeCrudController');
    }

    if(config('backpack.store.enable_brands')) {
      Route::crud('brand', 'BrandCrudController');
    }
    
}); // this should be the absolute last line of this file
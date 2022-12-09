<?php

Route::group([
    'prefix'     => config('backpack.base.route_prefix', 'admin'),
    'middleware' => ['web', config('backpack.base.middleware_key', 'admin')],
    'namespace'  => 'Backpack\Store\app\Http\Controllers\Admin',
], function () { // custom admin routes
    Route::crud('product', 'ProductCrudController');
    Route::crud('prod_category', 'CategoryCrudController');
    Route::crud('attribute', 'AttributeCrudController');
    // Route::crud('attribute_group', 'AttributeGroupCrudController');
    // Route::crud('modification', 'ModificationCrudController');
    // Route::crud('delivery', 'DeliveryCrudController');
    // Route::crud('payment', 'PaymentCrudController');
    Route::crud('order', 'OrderCrudController');
    
    if(config('aimix.shop.enable_brands')) {
      Route::crud('brand', 'BrandCrudController');
    }
    
    Route::post('modification/remove/{id}', 'ModificationCrudController@removeModification');
}); // this should be the absolute last line of this file
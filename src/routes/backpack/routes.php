<?php



Route::get('/admin/api/product', 'Backpack\Store\app\Http\Controllers\Admin\OrderCrudController@getProducts');
Route::get('/admin/api/attribute_values/{attribute_id}', 'Backpack\Store\app\Http\Controllers\Admin\ProductCrudController@getAttributeValues');
// Route::post('/admin/api/attribute_values/{attribute_id}', 'Backpack\Store\app\Http\Controllers\Admin\ProductCrudController@getAttributeValues');

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
    
    if(config('backpack.store.attribute.enable')) {
      Route::crud('attribute', 'AttributeCrudController');
      Route::crud('value', 'AttributeValueCrudController');
    }

    if(config('backpack.store.brands.enable')) {
      Route::crud('brand', 'BrandCrudController');
    }
    
}); // this should be the absolute last line of this file
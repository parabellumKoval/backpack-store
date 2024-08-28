<?php


Route::any('/admin/api/brand', 'Backpack\Store\app\Http\Controllers\Admin\BrandCrudController@getBrands');
Route::any('/admin/api/category', 'Backpack\Store\app\Http\Controllers\Admin\CategoryCrudController@getCategories');
Route::any('/admin/api/product', 'Backpack\Store\app\Http\Controllers\Admin\ProductCrudController@getProducts');
Route::any('/admin/api/attribute_values/{attribute_id}', 'Backpack\Store\app\Http\Controllers\Admin\AttributeCrudController@getAttributeValues');

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

    if(config('backpack.store.supplier.enable')) {
      Route::crud('supplier', 'SupplierCrudController');
    }

    if(config('backpack.store.source.enable')) {
      Route::crud('source', 'SourceCrudController');
      Route::crud('upload', 'UploadCrudController');
    }
    
}); // this should be the absolute last line of this file
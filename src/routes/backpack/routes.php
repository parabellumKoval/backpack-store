<?php


Route::any('/admin/api/brand', 'Backpack\Store\app\Http\Controllers\Admin\BrandCrudController@getBrands');
Route::any('/admin/api/category', 'Backpack\Store\app\Http\Controllers\Admin\CategoryCrudController@getCategories');
Route::any('/admin/api/product', 'Backpack\Store\app\Http\Controllers\Admin\ProductCrudController@getProductsRouter');
Route::any('/admin/api/attribute/{category?}', 'Backpack\Store\app\Http\Controllers\Admin\AttributeCrudController@getAttribute');
Route::any('/admin/api/attribute_values/{attribute_id}', 'Backpack\Store\app\Http\Controllers\Admin\AttributeCrudController@getAttributeValues');

Route::group([
    'prefix'     => config('backpack.base.route_prefix', 'admin'),
    'middleware' => ['web', config('backpack.base.middleware_key', 'admin')],
    'namespace'  => 'Backpack\Store\app\Http\Controllers\Admin',
], function () { // custom admin routes
    Route::crud('product', 'ProductCrudController');
    Route::crud('category', 'CategoryCrudController');
    Route::post('category/{id}/toggle', [
        'as' => 'category.toggle',
        'uses' => 'CategoryCrudController@toggleColumnRouter',
        'operation' => 'list',
    ]);
    // Route::crud('attribute_group', 'AttributeGroupCrudController');
    // Route::crud('delivery', 'DeliveryCrudController');
    // Route::crud('payment', 'PaymentCrudController');
    Route::crud('order', 'OrderCrudController');
    Route::crud('promocode', 'PromocodeCrudController');
    Route::post('promocode/{id}/toggle', [
        'as' => 'promocode.toggle',
        'uses' => 'PromocodeCrudController@toggleColumnRouter',
        'operation' => 'list',
    ]);
    
    Route::crud('search-queries', 'SearchQueryCrudController');
    
    // lists
    Route::crud('product-list', ProductListCrudController::class);

    Route::get('dashboard/store-widgets/orders', [
        'uses' => 'DashboardWidgetController@orders',
        'as' => 'backpack.store.dashboard.orders',
    ]);

    // seo page
    Route::crud('seo-page', SeoPageCrudController::class);

    // Currency
    // CRUD (index + show; create/update/delete отключены в контроллере)
    Route::crud('currency-rates', 'CurrencyRateCrudController');

    // Кнопка "Обновить сейчас"
    Route::post('currency-rates/refresh', 'CurrencyRateCrudController@refreshNow')
        ->name('backpack.store.currency-rates.refresh');

    //
    if(\Settings::get('dress.attribute.enable')) {
      Route::crud('attribute', 'AttributeCrudController');
      Route::crud('value', 'AttributeValueCrudController');
      Route::post('attribute/{id}/toggle', [
        'as' => 'attribute.toggle',
        'uses' => 'AttributeCrudController@toggleColumnRouter',
        'operation' => 'list',
      ]);
    }

    if(\Settings::get('dress.brand.enable')) {
      Route::crud('brand', 'BrandCrudController');
      Route::post('brand/{id}/toggle', [
        'as' => 'brand.toggle',
        'uses' => 'BrandCrudController@toggleColumnRouter',
        'operation' => 'list',
      ]);
    }

    if(\Settings::get('dress.supplier.enable')) {
      Route::crud('supplier', 'SupplierCrudController');
      Route::post('supplier/{id}/toggle', [
        'as' => 'supplier.toggle',
        'uses' => 'SupplierCrudController@toggleColumnRouter',
        'operation' => 'list',
      ]);
    }

    if(\Settings::get('dress.source.enable')) {
      Route::crud('source', 'SourceCrudController');
      Route::crud('upload', 'UploadCrudController');
    }
    
    Route::post('product/{id}/toggle', [
      'as' => 'product.toggle',
      'uses' => 'ProductCrudController@toggleColumnRouter',
      'operation' => 'list',
    ]);

    // Add route for select2_multiple updates
    // Route::post('{crud}/{id}/select2-multiple', [
    //     'as' => 'crud.select2-multiple',
    //     'uses' => 'Base\CrudController@handleSelect2Multiple'
    // ]);
    Route::post('product/{id}/select2-multiple', [
      'as' => 'crud.select2-multiple',
      'uses' => 'ProductCrudController@handleSelect2MultipleRouter'
    ]);

    Route::get('product/{productId}/orders-tab', [
        'as' => 'product.orders-tab',
        'uses' => 'ProductCrudController@ordersTabData',
        // 'operation' => 'update',
    ]);


    Route::post('product/bulk-action/{action}', [
        'as' => 'product.bulk-action',
        'uses' => 'ProductCrudController@handleBulkActionRouter',
        'operation' => 'list',
    ]); 


}); // this should be the absolute last line of this file

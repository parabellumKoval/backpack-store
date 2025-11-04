<?php

use Illuminate\Support\Facades\Route;
use Backpack\Store\app\Http\Controllers\Api\ProductListsController;

Route::prefix('api/lists')->middleware([Backpack\Store\app\Http\Middleware\AddXRegionHeadersToRequest::class])
    ->group(function () {
        // все списки для страницы
        Route::get('{page}', [ProductListsController::class, 'index']);
        // конкретный список
        Route::get('{page}/{slug}', [ProductListsController::class, 'show']);
});

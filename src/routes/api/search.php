<?php

use Illuminate\Support\Facades\Route;
use Backpack\Store\app\Http\Controllers\Api\SearchController;

Route::get('api/search/products', [SearchController::class, 'products'])->middleware([
    Backpack\Store\app\Http\Middleware\AddXRegionHeadersToRequest::class,
    Backpack\Store\app\Http\Middleware\SetLocaleFromHeader::class,
]);

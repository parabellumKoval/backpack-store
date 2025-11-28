<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use Backpack\Store\app\Http\Controllers\Api\CatalogController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('api/catalog')->middleware([
    'api',
    Backpack\Store\app\Http\Middleware\AddXRegionHeadersToRequest::class,
    Backpack\Store\app\Http\Middleware\SetLocaleFromHeader::class,
])
  ->controller(CatalogController::class)->group(function () {
  
    Route::get('/cache', 'cache');
    Route::get('', 'catalog');

    Route::get('/{slug}', 'show');
});

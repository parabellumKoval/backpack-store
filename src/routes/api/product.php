<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use Backpack\Store\app\Http\Controllers\Api\ProductController;

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

Route::prefix('api/product')->middleware(['api', Backpack\Store\app\Http\Middleware\AddXRegionHeadersToRequest::class])
  ->controller(ProductController::class)->group(function () {
    Route::get('', 'index');
    Route::get('/random', 'random');
    Route::get('/ids', 'getByIds');
    Route::get('/cart', 'cart');
    Route::get('/{slug}', 'show');
});

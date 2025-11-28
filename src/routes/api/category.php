<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use Backpack\Store\app\Http\Controllers\Api\CategoryController;

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

Route::prefix('api/category')->middleware([
    'api',
    Backpack\Store\app\Http\Middleware\AddXRegionHeadersToRequest::class,
    Backpack\Store\app\Http\Middleware\SetLocaleFromHeader::class,
])
  ->controller(CategoryController::class)->group(function () {
    Route::get('', 'index');
    Route::get('/main', 'main');
    Route::get('/simple', 'indexSimple');
    Route::get('/{slug}', 'show');
});

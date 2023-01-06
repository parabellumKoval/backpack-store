<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use Backpack\Store\app\Http\Controllers\Api\OrderController;

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

$guard = config('backpack.store.auth_guard', 'profile');

Route::prefix('api/orders')->controller(OrderController::class)->group(function () use($guard) {
  
  Route::get('', 'index')->middleware(['api', "auth:${guard}"]);

  Route::get('/all', 'all');

  Route::get('/{slug}', 'show');

  Route::post('', 'create')->middleware('api');

});

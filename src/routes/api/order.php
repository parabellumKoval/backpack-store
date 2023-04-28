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
  

  // GET orders list with pagination for authed user
  Route::post('/get', 'index')->middleware(['api', "auth:${guard}"]);
  Route::get('', 'index')->middleware(['api', "auth:${guard}"]);
  
  // Clone exists order
  Route::post('/copy', 'copy')->middleware(['api', "auth:${guard}"]);

  // GET orders list with pagination by params
  Route::get('/all', 'all');

  // Get One order by code
  Route::get('/{code}', 'show');

  // Create new order
  Route::post('', 'create')->middleware('api');

  // Validate order without creation
  Route::post('/validate', 'validateData')->middleware('api');

});

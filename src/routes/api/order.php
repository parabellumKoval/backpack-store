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

// $guard = \Settings::get('dress.store.auth_guard', 'profile');
$guard = 'sanctum';

Route::prefix('api/order')
  ->middleware([Backpack\Store\app\Http\Middleware\AddXRegionHeadersToRequest::class, \Backpack\Store\app\Http\Middleware\ForceJsonResponse::class])
  ->controller(OrderController::class)->group(function () use($guard) {
    // GET orders list with pagination for authed user
    Route::post('/get', 'index')->middleware(['api', "auth:${guard}"]);
    Route::get('', 'index')->middleware(['api', "auth:${guard}"]);
    
    // Clone exists order
    Route::post('/copy', 'copy')->middleware(['api', "auth:${guard}"]);

    // GET orders list with pagination by params
    Route::get('/all', 'all');

    // Validate order without creation
    Route::get('/rules', 'getRequestRules')->middleware('api');

    // Get One order by code
    Route::get('/{code}', 'show');

    // Create new order
    Route::post('', 'create')->middleware('api');

    // Validate order without creation
    Route::post('/validate', 'validateOrder')->middleware('api');
  });

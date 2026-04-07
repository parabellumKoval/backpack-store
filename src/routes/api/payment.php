<?php

use Backpack\Store\app\Http\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/payment')
    ->middleware('api')
    ->controller(PaymentController::class)
    ->group(function () {
        Route::post('/{provider}/create', 'create');
        Route::post('/{provider}/callback', 'callback');
        Route::match(['GET', 'POST'], '/{provider}/results', 'results');
    });

Route::prefix('api/liqpay')
    ->middleware('api')
    ->controller(PaymentController::class)
    ->group(function () {
        Route::post('/form', 'liqpayForm');
        Route::post('/callback', 'liqpayCallback');
        Route::match(['GET', 'POST'], '/results', 'liqpayResults');
    });

Route::post('/niftipay/webhook', [PaymentController::class, 'niftipayWebhook'])
    ->middleware('api');

Route::post('/api/niftipay/webhook', [PaymentController::class, 'niftipayWebhook'])
    ->middleware('api');

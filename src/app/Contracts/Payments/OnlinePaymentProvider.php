<?php

namespace Backpack\Store\app\Contracts\Payments;

use Illuminate\Http\Request;

interface OnlinePaymentProvider
{
    public function key(): string;

    public function createPayment(array $payload): array;

    public function handleCallback(Request $request);

    public function handleResult(Request $request);
}

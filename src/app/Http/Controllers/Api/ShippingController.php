<?php

namespace Backpack\Store\app\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Backpack\Store\app\Services\Shipping\ShippingCalculator;
use Backpack\Store\app\DTO\ShippingQuoteRequest;

class ShippingController extends Controller
{
    public function quote(Request $http, ShippingCalculator $calculator)
    {
        // ожидаемые поля из фронтенда:
        // methodKey (string, "name_type")
        // destinationCountry (ISO2)
        // weightG (int)
        // lengthCm,widthCm,heightCm (int, optional)
        // codEnabled (bool), codAmount (float)
        // lockerSize (S|M|L для НП почтомата)
        // declaredValue (float) — для страховки (НП)
        $payload = $http->validate([
            'methodKey'          => 'required|string',
            'destinationCountry' => 'required|string|size:2',
            'weightG'            => 'required|integer|min:0',
            'lengthCm'           => 'nullable|integer|min:0',
            'widthCm'            => 'nullable|integer|min:0',
            'heightCm'           => 'nullable|integer|min:0',
            'codEnabled'         => 'nullable|boolean',
            'codAmount'          => 'nullable|numeric|min:0',
            'lockerSize'         => 'nullable|in:S,M,L',
            'declaredValue'      => 'nullable|numeric|min:0',
            'meta'               => 'array',
        ]);

        // dd($payload);
        $dto = new ShippingQuoteRequest($payload);;
        $result = $calculator->calculate($dto);

        return response()->json([
            'provider'  => $result->provider,
            'methodKey' => $result->methodKey,
            'currency'  => $result->currency,
            'amount'    => $result->amount,
            'breakdown' => $result->breakdown,
        ]);
    }
}

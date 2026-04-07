<?php

namespace Backpack\Store\app\Http\Controllers\Api;

use Backpack\Store\app\Exceptions\Payments\PaymentProviderException;
use Backpack\Store\app\Services\Payments\PaymentProviderRegistry;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends \App\Http\Controllers\Controller
{
    public function __construct(protected PaymentProviderRegistry $registry)
    {
    }

    public function create(Request $request, string $provider)
    {
        return $this->runProviderAction($provider, fn() => response()->json(
            $this->registry->provider($provider)->createPayment($request->all())
        ));
    }

    public function callback(Request $request, string $provider)
    {
        return $this->runProviderAction($provider, fn() => $this->normalizeResponse(
            $this->registry->provider($provider)->handleCallback($request)
        ));
    }

    public function results(Request $request, string $provider)
    {
        return $this->runProviderAction($provider, fn() => $this->normalizeResponse(
            $this->registry->provider($provider)->handleResult($request)
        ));
    }

    public function liqpayForm(Request $request)
    {
        return $this->create($request, 'liqpay');
    }

    public function liqpayCallback(Request $request)
    {
        return $this->callback($request, 'liqpay');
    }

    public function liqpayResults(Request $request)
    {
        return $this->results($request, 'liqpay');
    }

    public function niftipayWebhook(Request $request)
    {
        return $this->callback($request, 'niftipay');
    }

    protected function runProviderAction(string $provider, callable $callback)
    {
        try {
            return $callback();
        } catch (PaymentProviderException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'options' => $exception->context(),
            ], $this->responseStatus($exception->getCode()));
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'provider' => $provider,
            ], 404);
        }
    }

    protected function normalizeResponse(mixed $response)
    {
        if ($response instanceof Response) {
            return $response;
        }

        if (is_array($response)) {
            return response()->json($response);
        }

        if ($response === null) {
            return response()->noContent();
        }

        return $response;
    }

    protected function responseStatus(int $status): int
    {
        return $status >= 400 && $status <= 599 ? $status : 422;
    }
}

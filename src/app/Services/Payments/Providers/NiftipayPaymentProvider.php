<?php

namespace Backpack\Store\app\Services\Payments\Providers;

use Backpack\Store\app\Contracts\Payments\OnlinePaymentProvider;
use Backpack\Store\app\Exceptions\Payments\PaymentProviderException;
use Backpack\Store\app\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class NiftipayPaymentProvider extends AbstractPaymentProvider implements OnlinePaymentProvider
{
    public function key(): string
    {
        return 'niftipay';
    }

    public function createPayment(array $payload): array
    {
        $validator = Validator::make($payload, [
            'amount' => 'nullable|numeric',
            'currency' => 'nullable|string|size:3',
            'description' => 'nullable|string|max:255',
            'order' => 'required',
            'email' => 'nullable|email',
        ]);

        if ($validator->fails()) {
            throw new PaymentProviderException('Niftipay payment payload is invalid.', 400, null, $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $order = $this->findOrderByCode((string) $data['order']);

        $amount = $data['amount'] ?? $order?->price;
        $currency = strtoupper((string) ($data['currency'] ?? $order?->currency_code ?? ''));

        if ($amount === null || !is_numeric($amount)) {
            throw new PaymentProviderException('Niftipay amount is required.', 422);
        }

        if ($currency === '') {
            throw new PaymentProviderException('Niftipay currency is required.', 422);
        }

        $integrationId = $this->integrationId($order);

        $body = [
            'integrationId' => $integrationId,
            'currency' => $currency,
            'amount' => $this->normalizeAmount($amount),
            'description' => $data['description'] ?? $this->defaultDescription((string) $data['order']),
            'reference' => (string) $data['order'],
        ];

        if ($email = ($data['email'] ?? data_get($order?->info, 'user.email'))) {
            $body['email'] = $email;
        }

        $serviceFeePayer = $this->serviceFeePayer($order);
        if ($serviceFeePayer) {
            $body['serviceFeePayer'] = $serviceFeePayer;
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey($order),
            'Accept' => 'application/json',
        ])->post($this->baseUrl($order) . '/api/fiat/orders', $body);

        $responseData = $response->json() ?: [];

        if (!$response->successful()) {
            throw new PaymentProviderException(
                (string) (data_get($responseData, 'error') ?: 'Niftipay payment creation failed.'),
                $response->status() >= 400 ? $response->status() : 502,
                null,
                $responseData
            );
        }

        $payUrl = data_get($responseData, 'payUrl')
            ?: data_get($responseData, 'order.payUrl')
            ?: data_get($responseData, 'order.orderUrl');

        if (!$payUrl) {
            throw new PaymentProviderException('Niftipay response does not contain a payment URL.', 502, null, $responseData);
        }

        if ($order) {
            $this->updateOrderPaymentInfo($order, $this->key(), [
                'id' => data_get($responseData, 'order.id'),
                'order_key' => data_get($responseData, 'order.orderKey'),
                'integration_id' => $integrationId,
                'reference' => data_get($responseData, 'reference') ?: (string) $data['order'],
                'pay_url' => $payUrl,
                'qr_url' => data_get($responseData, 'qrUrl'),
                'status' => data_get($responseData, 'order.status'),
                'updated_at' => now()->toIso8601String(),
            ]);
            $order->save();
        }

        return [
            'provider' => $this->key(),
            'type' => 'redirect',
            'url' => $payUrl,
            'payUrl' => $payUrl,
            'qrUrl' => data_get($responseData, 'qrUrl'),
            'reference' => data_get($responseData, 'reference') ?: (string) $data['order'],
            'order' => data_get($responseData, 'order'),
            'pricing' => data_get($responseData, 'pricing'),
            'display' => data_get($responseData, 'display'),
        ];
    }

    public function handleCallback(Request $request)
    {
        $payload = $request->json()->all() ?: $request->all();
        $this->verifyWebhookSignature($request, $payload);

        $event = (string) data_get($payload, 'event');
        $remoteOrder = (array) data_get($payload, 'order', []);
        $orderCode = $this->extractOrderCode($remoteOrder);

        $this->log('info', 'webhook', [
            'event' => $event,
            'order' => data_get($remoteOrder, 'id'),
            'reference' => $orderCode,
        ]);

        $order = $this->findOrderByCode($orderCode);

        if (!$order) {
            $this->log('warning', 'webhook order was not found', [
                'event' => $event,
                'reference' => $orderCode,
            ]);

            return response()->json(['ok' => true]);
        }

        $status = $this->mapWebhookStatus($event, data_get($remoteOrder, 'status'));
        if ($status) {
            $order->pay_status = $status;
        }

        $this->updateOrderPaymentInfo($order, $this->key(), [
            'id' => data_get($remoteOrder, 'id'),
            'order_key' => data_get($remoteOrder, 'orderKey'),
            'integration_id' => data_get($remoteOrder, 'integrationId'),
            'psp_order_id' => data_get($remoteOrder, 'pspOrderId'),
            'event' => $event,
            'status' => data_get($remoteOrder, 'status'),
            'updated_at' => now()->toIso8601String(),
        ]);

        $order->save();

        return response()->json(['ok' => true]);
    }

    public function handleResult(Request $request)
    {
        $orderCode = $this->scalarString($request->input('reference'))
            ?: $this->scalarString($request->input('merchantReference'))
            ?: $this->scalarString($request->input('order'));

        return redirect()->to($this->completeRedirectForOrder($orderCode));
    }

    protected function extractOrderCode(array $remoteOrder): ?string
    {
        foreach ([
            data_get($remoteOrder, 'merchantReference'),
            data_get($remoteOrder, 'reference'),
        ] as $code) {
            if ($code = $this->scalarString($code)) {
                return $code;
            }
        }

        return null;
    }

    protected function verifyWebhookSignature(Request $request, array $payload = []): void
    {
        $secret = $this->webhookSecret($payload);

        if ($secret === '') {
            $this->log('warning', 'webhook secret is not configured; signature verification skipped');
            return;
        }

        $timestamp = $request->header('x-timestamp');
        $signature = $request->header('x-signature');

        if (!$timestamp || !$signature || !Str::startsWith($signature, 'v1=')) {
            throw new PaymentProviderException('Niftipay webhook signature headers are invalid.', 400);
        }

        if (!ctype_digit((string) $timestamp) || abs(time() - (int) $timestamp) > 300) {
            throw new PaymentProviderException('Niftipay webhook timestamp is outside the allowed window.', 401);
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $request->getContent(), $secret);
        $received = substr((string) $signature, 3);

        if (!hash_equals($expected, $received)) {
            throw new PaymentProviderException('Niftipay webhook signature is invalid.', 401);
        }
    }

    protected function webhookSecret(array $payload = []): string
    {
        $integrationId = $this->scalarString(data_get($payload, 'order.integrationId'))
            ?: $this->scalarString(data_get($payload, 'integrationId'));

        if ($integrationId) {
            foreach ($this->storefrontSettings() as $storefront => $settings) {
                $configuredIntegrationId = $this->scalarString(data_get($settings, 'fiat_integration_id'))
                    ?: $this->scalarString(data_get($settings, 'integration_id'));

                if ($configuredIntegrationId === $integrationId) {
                    return trim((string) $this->setting('webhook_secret', '', (string) $storefront));
                }
            }
        }

        $storefront = $this->storefrontFromWebhookPayload($payload);

        return trim((string) $this->setting('webhook_secret', '', $storefront));
    }

    protected function storefrontFromWebhookPayload(array $payload = []): ?string
    {
        $orderCode = $this->extractOrderCode((array) data_get($payload, 'order', []));
        $order = $this->findOrderByCode($orderCode);

        return $this->storefrontFromOrder($order);
    }

    protected function mapWebhookStatus(?string $event, ?string $remoteStatus): ?string
    {
        $value = strtolower((string) ($event ?: $remoteStatus));

        return match ($value) {
            'paid', 'completed' => 'paied',
            'pending', 'new', 'processing' => 'waiting',
            'cancelled', 'canceled', 'expired', 'refunded', 'error' => 'failed',
            default => null,
        };
    }

    protected function integrationId(?Order $order = null): string
    {
        $integrationId = trim((string) $this->setting(
            'fiat_integration_id',
            '',
            $this->storefrontFromOrder($order)
        ));

        if ($integrationId === '') {
            throw new PaymentProviderException('Niftipay fiat integration ID is not configured.', 422);
        }

        return $integrationId;
    }

    protected function apiKey(?Order $order = null): string
    {
        $apiKey = trim((string) $this->setting(
            'api_key',
            '',
            $this->storefrontFromOrder($order)
        ));

        if ($apiKey === '') {
            throw new PaymentProviderException('Niftipay API key is not configured.', 422);
        }

        return $apiKey;
    }

    protected function baseUrl(?Order $order = null): string
    {
        return rtrim((string) $this->setting(
            'base_url',
            'https://www.niftipay.com',
            $this->storefrontFromOrder($order)
        ), '/');
    }

    protected function serviceFeePayer(?Order $order = null): ?string
    {
        $payer = trim((string) $this->setting(
            'service_fee_payer',
            '',
            $this->storefrontFromOrder($order)
        ));

        return in_array($payer, ['customer', 'merchant'], true) ? $payer : null;
    }

    protected function normalizeAmount(mixed $amount): string
    {
        return rtrim(rtrim(number_format((float) $amount, 2, '.', ''), '0'), '.');
    }

    protected function defaultDescription(string $orderCode): string
    {
        return "Order {$orderCode}";
    }
}

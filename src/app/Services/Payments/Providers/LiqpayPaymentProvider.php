<?php

namespace Backpack\Store\app\Services\Payments\Providers;

use Backpack\Store\app\Contracts\Payments\OnlinePaymentProvider;
use Backpack\Store\app\Exceptions\Payments\PaymentProviderException;
use Backpack\Store\app\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LiqpayPaymentProvider extends AbstractPaymentProvider implements OnlinePaymentProvider
{
    protected const STATUS = [
        'error' => 'failed',
        'failure' => 'failed',
        'success' => 'paied',
    ];

    public function key(): string
    {
        return 'liqpay';
    }

    public function createPayment(array $payload): array
    {
        $validator = Validator::make($payload, [
            'amount' => 'required|numeric',
            'order' => 'required',
            'description' => 'nullable|string',
            'action' => 'nullable|string',
            'currency' => 'nullable|string|size:3',
        ]);

        if ($validator->fails()) {
            throw new PaymentProviderException('LiqPay payment payload is invalid.', 400, null, $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $encoded = $this->encodeData([
            'public_key' => $this->liqpaySetting('public_key'),
            'version' => (int) $this->liqpaySetting('version', 3),
            'action' => $data['action'] ?? $this->liqpaySetting('action', 'pay'),
            'amount' => $data['amount'],
            'currency' => strtoupper((string) ($data['currency'] ?? $this->liqpaySetting('currency', 'UAH'))),
            'description' => $data['description'] ?? '',
            'order_id' => (string) $data['order'],
            'server_url' => $this->liqpaySetting('callback'),
            'result_url' => $this->liqpaySetting('result'),
        ]);

        return [
            'provider' => $this->key(),
            'type' => 'form',
            'data' => $encoded,
            'signature' => $this->signature($encoded),
            'action' => $this->liqpaySetting('checkout_url', 'https://www.liqpay.ua/api/3/checkout'),
        ];
    }

    public function handleCallback(Request $request)
    {
        $payload = $this->verifiedPayload($request);

        $this->log('info', 'callback', ['payload' => $payload]);
        $this->updateOrder($payload);

        return response()->json(['ok' => true]);
    }

    public function handleResult(Request $request)
    {
        $payload = $this->decodePayload($request->input('data'));

        $this->log('info', 'result', ['payload' => $payload]);

        $orderCode = $this->extractOrderCode($payload, $request);

        if ($payload && $request->input('data') && $request->input('signature')) {
            if ($this->signature($request->input('data')) !== $request->input('signature')) {
                $this->log('error', 'invalid result signature');
            } else {
                $this->updateOrder($payload);
            }
        }

        return redirect()->to($this->completeRedirectForOrder($orderCode));
    }

    protected function verifiedPayload(Request $request): ?object
    {
        $data = $request->input('data');
        $signature = $request->input('signature');

        $payload = $this->decodePayload($data);

        if (!$payload || !$data || !$signature) {
            throw new PaymentProviderException('LiqPay callback payload is invalid.', 400);
        }

        if ($this->signature($data) !== $signature) {
            throw new PaymentProviderException('LiqPay callback signature is invalid.', 401);
        }

        return $payload;
    }

    protected function decodePayload(?string $data): ?object
    {
        if (!$data) {
            return null;
        }

        $decoded = base64_decode($data, true);

        if ($decoded === false) {
            $this->log('error', 'unable to base64 decode payload');
            return null;
        }

        $payload = json_decode($decoded);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log('error', 'unable to JSON decode payload', ['error' => json_last_error_msg()]);
            return null;
        }

        return $payload;
    }

    protected function updateOrder(?object $payload): bool
    {
        if (!$payload) {
            return false;
        }

        $order = Order::where('code', $payload->order_id ?? null)
            ->where('price', $payload->amount ?? null)
            ->first();

        if (!$order) {
            $this->log('error', 'order was not found', [
                'order_id' => $payload->order_id ?? null,
                'amount' => $payload->amount ?? null,
            ]);
            return false;
        }

        $status = $this->status($payload->status ?? null);

        if ($status) {
            $order->pay_status = $status;
        }

        $this->updateOrderPaymentInfo($order, $this->key(), [
            'status' => $payload->status ?? null,
            'transaction_id' => $payload->transaction_id ?? null,
            'payment_id' => $payload->payment_id ?? null,
            'updated_at' => now()->toIso8601String(),
        ]);

        $order->save();

        return true;
    }

    protected function status(?string $status): ?string
    {
        if (!$status) {
            return null;
        }

        return self::STATUS[$status] ?? config('dress.order.pay_status.default', 'waiting');
    }

    protected function extractOrderCode(?object $payload, Request $request): ?string
    {
        foreach ([
            $payload->order_id ?? null,
            $request->input('order_id'),
            $request->input('orderId'),
            $request->input('order'),
        ] as $code) {
            if ($code = $this->scalarString($code)) {
                return $code;
            }
        }

        return null;
    }

    protected function encodeData(array $data): string
    {
        $data = array_filter($data, fn($value) => $value !== null && $value !== '');

        return base64_encode(json_encode($data));
    }

    protected function signature(string $data): string
    {
        $privateKey = (string) $this->liqpaySetting('private_key');

        return base64_encode(sha1($privateKey . $data . $privateKey, true));
    }

    protected function liqpaySetting(string $key, mixed $default = null): mixed
    {
        return $this->setting($key, config("liqpay.{$key}", $default));
    }
}

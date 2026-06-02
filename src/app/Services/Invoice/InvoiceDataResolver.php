<?php

namespace Backpack\Store\app\Services\Invoice;

use Backpack\Store\app\Models\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

class InvoiceDataResolver
{
    public function build(Order $order, array $context = []): array
    {
        $country = $order->country_code ?: \Store::country();
        $invoiceNumber = $this->resolveInvoiceNumber($order, Arr::get($context, 'number_pattern'));
        $issuedAt = $this->resolveDate($order, 'issued_at', Arr::get($context, 'issued_at'));
        $dueAt = $this->resolveDueDate($order, $issuedAt, Arr::get($context, 'due_days'));
        $taxedAt = $this->resolveTaxDate($order, $issuedAt, Arr::get($context, 'tax_offset_days'));

        $seller = $this->resolveSeller($order, $country);
        $buyer = $this->resolveBuyer($order);
        $bank = $this->resolveBank($country, $seller);


        $lines = $this->resolveLines($order);
        $totals = $this->calculateTotals($lines);

        $qrPayload = [
            'amount' => $totals['grand_total'],
            'currency' => $totals['currency'],
            'variable_symbol' => $this->resolveVariableSymbol($order),
            'message' => strtr($this->config('qr.message_pattern'), [
                '{invoice_number}' => $invoiceNumber,
                '{order_number}' => $order->code ?? $order->getKey(),
            ]),
            'iban' => $bank['iban'] ?? null,
        ];

        return [
            'order' => $order,
            'invoice_number' => $invoiceNumber,
            'order_number' => $order->code ?? $order->getKey(),
            'issued_at' => $issuedAt,
            'due_at' => $dueAt,
            'taxed_at' => $taxedAt,
            'seller' => $seller,
            'buyer' => $buyer,
            'bank' => $bank,
            'lines' => $lines,
            'totals' => $totals,
            'currency' => $totals['currency'],
            'qr_payload' => $qrPayload,
            'notes' => $this->resolveNotes($order),
            'assets' => $this->resolveAssets(),
            'template' => Arr::get($context, 'template'),
            'locale' => Arr::get($context, 'locale', $this->config('locale')),
        ];
    }

    protected function resolveInvoiceNumber(Order $order, ?string $pattern = null): string
    {
        if (!empty($order->invoice_number)) {
            return (string) $order->invoice_number;
        }

        $infoNumber = Arr::get($order->info, 'invoice.number');
        if ($infoNumber) {
            return (string) $infoNumber;
        }

        $pattern = $pattern ?: $this->config('numbering.pattern');
        $issuedAt = $this->resolveDate($order, 'issued_at');

        $replace = [
            '{order_id}' => $order->getKey(),
            '{order_code}' => $order->code ?? '',
            '{country}' => strtoupper($order->country_code ?? ''),
            '{Y}' => $issuedAt->format('Y'),
            '{y}' => $issuedAt->format('y'),
            '{m}' => $issuedAt->format('m'),
            '{d}' => $issuedAt->format('d'),
        ];

        return strtr($pattern, $replace);
    }

    protected function resolveVariableSymbol(Order $order): string
    {
        $vs = $order->code ?? $order->getKey();
        return preg_replace('/\D/', '', (string) $vs) ?: (string) $order->getKey();
    }

    protected function resolveDate(Order $order, string $key, ?string $fallback = null): Carbon
    {
        $infoValue = Arr::get($order->info, "invoice.$key");
        if ($infoValue) {
            return Carbon::parse($infoValue);
        }

        if (!empty($order->{$key})) {
            return Carbon::parse($order->{$key});
        }

        if ($fallback) {
            return Carbon::parse($fallback);
        }

        if ($key === 'issued_at') {
            return Carbon::parse($order->created_at ?? now());
        }

        return Carbon::parse(now());
    }

    protected function resolveDueDate(Order $order, Carbon $issuedAt, ?int $dueDays = null): Carbon
    {
        $infoValue = Arr::get($order->info, 'invoice.due_at');
        if ($infoValue) {
            return Carbon::parse($infoValue);
        }

        $dueDays = $dueDays ?? (int) $this->config('numbering.due_days', 14);

        return (clone $issuedAt)->addDays($dueDays);
    }

    protected function resolveTaxDate(Order $order, Carbon $issuedAt, ?int $taxOffset = null): Carbon
    {
        $infoValue = Arr::get($order->info, 'invoice.taxed_at');
        if ($infoValue) {
            return Carbon::parse($infoValue);
        }

        $offset = $taxOffset ?? (int) $this->config('numbering.tax_date_offset', 0);

        return (clone $issuedAt)->addDays($offset);
    }

    protected function resolveSeller(Order $order, string $country): array
    {
        $configSeller = (array) \Settings::get('dress.invoice.seller', []);

        if (empty($configSeller)) {
            $configSeller = [
                'name' => config('app.name', 'Vivadzen'),
                'ico' => null,
                'dic' => null,
                'vat_number' => null,
                'address' => [
                    'street' => '',
                    'city' => '',
                    'zip' => '',
                    'country' => strtoupper($country),
                ],
                'contacts' => [
                    'email' => config('mail.from.address'),
                    'phone' => null,
                    'website' => config('app.url'),
                ],
            ];
        }

        return $configSeller;
    }

    protected function resolveBank(string $country, array $seller): array
    {
        $bankConfig = \Settings::get('dress.invoice.bank_accounts');
        $bankFromSettings = null;

        if (is_array($bankConfig)) {
            if (array_key_exists($country, $bankConfig)) {
                $bankFromSettings = $bankConfig[$country];
            } else {
                foreach ($bankConfig as $key => $item) {
                    if (strtoupper($key) === strtoupper($country)) {
                        $bankFromSettings = $item;
                        break;
                    }
                }
            }
        }

        if (!$bankFromSettings) {
            $bankFromSettings = $this->config("bank_accounts.$country", []);
        }

        $defaultCurrency = $this->config('currency', 'CZK');

        return array_filter(array_merge([
            'currency' => $defaultCurrency,
        ], (array) $bankFromSettings));
    }

    protected function resolveBuyer(Order $order): array
    {
        $user = Arr::get($order->info, 'user', []);
        $delivery = Arr::get($order->info, 'delivery', []);
        $payment = Arr::get($order->info, 'payment', []);

        $firstName = Arr::get($user, 'first_name');
        $lastName = Arr::get($user, 'last_name');
        $nameParts = array_filter([$firstName, $lastName]);
        $name = trim(implode(' ', $nameParts));
        $contactPhone = Arr::get($user, 'phone');

        $buyer = [
            'name' => $name ?: Arr::get($user, 'company', ''),
            'firstname' => $firstName,
            'lastname' => $lastName,
            'ico' => Arr::get($user, 'ic') ?? Arr::get($user, 'ico'),
            'dic' => Arr::get($user, 'dic'),
            'vat_number' => Arr::get($user, 'vat'),
            'address' => $this->resolveBuyerAddress($order, $delivery, $payment, $user),
            'contacts' => [
                'email' => Arr::get($user, 'email'),
                'phone' => $contactPhone,
            ],
        ];

        return $buyer;
    }

    protected function resolveLines(Order $order): array
    {
        $products = Arr::get($order->info, 'products', []);
        $defaultVat = (float) $this->config('defaults.vat_rate', 21);
        $defaultUnit = $this->config('defaults.unit', 'ks');
        $currency = $order->currency_code ?: $this->config('currency', 'CZK');

        $lines = [];
        foreach ($products as $index => $product) {
            $qty = (float) Arr::get($product, 'amount', 1);
            $unitPrice = (float) Arr::get($product, 'price', 0);
            $vatRate = (float) Arr::get($product, 'vat_rate', $defaultVat);

            $totalExVat = $unitPrice * $qty;
            $vatAmount = round($totalExVat * $vatRate / 100, 2);
            $totalIncVat = $totalExVat + $vatAmount;

            $lines[] = [
                'position' => $index + 1,
                'name' => Arr::get($product, 'name', 'Položka'),
                'quantity' => $qty,
                'unit' => Arr::get($product, 'unit', $defaultUnit),
                'unit_price' => $unitPrice,
                'vat_rate' => $vatRate,
                'total_ex_vat' => $totalExVat,
                'total_vat' => $vatAmount,
                'total_inc_vat' => $totalIncVat,
                'currency' => $currency,
            ];
        }

        $shippingLine = $this->resolveShippingLine($order, count($lines) + 1, $currency);
        if ($shippingLine !== null) {
            $lines[] = $shippingLine;
        }

        return $lines;
    }

    protected function resolveShippingLine(Order $order, int $position, string $currency): ?array
    {
        if ($this->resolveMethodKey(Arr::get($order->info, 'payment')) !== 'bank_transfer') {
            return null;
        }

        $shippingGross = round((float) ($order->shipping_total ?? 0), 2);
        if ($shippingGross <= 0) {
            return null;
        }

        $breakdown = Arr::get($order->info, 'shippingQuote.breakdown', []);
        $vatRate = round((float) Arr::get($breakdown, 'vat_rate', 0), 2);
        $gross = round((float) Arr::get($breakdown, 'gross', $shippingGross), 2);
        $net = Arr::has($breakdown, 'net')
            ? round((float) Arr::get($breakdown, 'net'), 2)
            : $this->resolveNetAmountFromGross($gross, $vatRate);
        $vat = Arr::has($breakdown, 'vat')
            ? round((float) Arr::get($breakdown, 'vat'), 2)
            : round($gross - $net, 2);

        $deliveryLabel = null;
        if (function_exists('store_delivery_lines')) {
            $deliveryLines = store_delivery_lines(Arr::get($order->info, 'delivery'));
            $deliveryLabel = $deliveryLines[0] ?? null;
        }

        if (!$deliveryLabel) {
            $deliveryMethod = $this->resolveMethodKey(Arr::get($order->info, 'delivery'));
            $deliveryLabel = $deliveryMethod ? store_delivery_method_label($deliveryMethod) : null;
        }

        return [
            'position' => $position,
            'name' => $deliveryLabel ? sprintf('Doprava (%s)', $deliveryLabel) : 'Doprava',
            'quantity' => 1.0,
            'unit' => 'služba',
            'unit_price' => $net,
            'vat_rate' => $vatRate,
            'total_ex_vat' => $net,
            'total_vat' => $vat,
            'total_inc_vat' => $gross,
            'currency' => $currency,
        ];
    }

    protected function resolveNetAmountFromGross(float $gross, float $vatRate): float
    {
        if ($gross <= 0 || $vatRate <= 0) {
            return round($gross, 2);
        }

        return round($gross / (1 + ($vatRate / 100)), 2);
    }

    protected function resolveMethodKey(mixed $payload): ?string
    {
        if (is_string($payload)) {
            $payload = trim($payload);
            return $payload !== '' ? $payload : null;
        }

        if (!is_array($payload)) {
            return null;
        }

        $keys = ['method', 'paymentMethod', 'payment_method', 'deliveryMethod', 'delivery_method', 'methodKey', 'method_key', 'code', 'key', 'name', 'label'];

        $extractString = static function (array $source) use (&$extractString, $keys): ?string {
            foreach ($keys as $key) {
                if (!array_key_exists($key, $source)) {
                    continue;
                }

                $value = $source[$key];

                if (is_scalar($value) || is_bool($value)) {
                    $value = trim((string) $value);
                    if ($value !== '') {
                        return $value;
                    }
                }

                if (is_array($value)) {
                    $nested = $extractString($value);
                    if ($nested !== null) {
                        return $nested;
                    }
                }
            }

            foreach ($source as $value) {
                if (!is_array($value)) {
                    continue;
                }

                $nested = $extractString($value);
                if ($nested !== null) {
                    return $nested;
                }
            }

            return null;
        };

        return $extractString($payload);
    }

    protected function calculateTotals(array $lines): array
    {
        $summary = [
            'without_vat' => 0.0,
            'vat' => 0.0,
            'grand_total' => 0.0,
            'currency' => $this->config('currency', 'CZK'),
            'by_vat_rate' => [],
        ];

        foreach ($lines as $line) {
            $rate = (string) $line['vat_rate'];
            $summary['without_vat'] += $line['total_ex_vat'];
            $summary['vat'] += $line['total_vat'];
            $summary['grand_total'] += $line['total_inc_vat'];
            $summary['currency'] = $line['currency'];

            if (!isset($summary['by_vat_rate'][$rate])) {
                $summary['by_vat_rate'][$rate] = [
                    'rate' => (float) $line['vat_rate'],
                    'base' => 0.0,
                    'vat' => 0.0,
                    'total' => 0.0,
                ];
            }

            $summary['by_vat_rate'][$rate]['base'] += $line['total_ex_vat'];
            $summary['by_vat_rate'][$rate]['vat'] += $line['total_vat'];
            $summary['by_vat_rate'][$rate]['total'] += $line['total_inc_vat'];
        }

        foreach ($summary['by_vat_rate'] as &$item) {
            $item['base'] = round($item['base'], 2);
            $item['vat'] = round($item['vat'], 2);
            $item['total'] = round($item['total'], 2);
        }

        $summary['without_vat'] = round($summary['without_vat'], 2);
        $summary['vat'] = round($summary['vat'], 2);
        $summary['grand_total'] = round($summary['grand_total'], 2);

        return $summary;
    }

    protected function resolveNotes(Order $order): array
    {
        $notes = Arr::get($order->info, 'invoice.notes', []);
        if (is_string($notes)) {
            $notes = [$notes];
        }

        return array_filter((array) $notes);
    }

    protected function resolveAssets(): array
    {
        return [
            'logo' => \Settings::get('dress.invoice.assets.logo_url', $this->config('assets.logo_url')),
            'stamp' => \Settings::get('dress.invoice.assets.stamp_url', $this->config('assets.stamp_url')),
            'signature' => \Settings::get('dress.invoice.assets.signature_url', $this->config('assets.signature_url')),
        ];
    }

    protected function resolveBuyerAddress(Order $order, array $delivery, array $payment, array $user): array
    {
        $sources = [$delivery, $payment];

        foreach ($sources as $source) {
            if (!is_array($source) || !array_filter($source)) {
                continue;
            }

            $street = $this->composeStreetLine(
                Arr::get($source, 'street'),
                Arr::get($source, 'house'),
                Arr::get($source, 'room'),
                Arr::get($source, 'address')
            );

            $city = Arr::get($source, 'settlement') ?: Arr::get($source, 'city');
            $zip = Arr::get($source, 'zip');
            $country = Arr::get($source, 'country') ?? Arr::get($source, 'country_code');

            if ($street || $city || $zip || $country) {
                $resolvedCountry = $country ?: Arr::get($user, 'country') ?: $order->country_code;

                return [
                    'street' => $street ?: (Arr::get($user, 'address') ?: null),
                    'city' => $city ?: Arr::get($user, 'city'),
                    'zip' => $zip ?: Arr::get($user, 'zip'),
                    'country' => $resolvedCountry ? strtoupper((string) $resolvedCountry) : null,
                ];
            }
        }

        $fallbackCountry = Arr::get($delivery, 'country') ?? Arr::get($user, 'country') ?? $order->country_code;

        return [
            'street' => Arr::get($delivery, 'address') ?? Arr::get($user, 'address'),
            'city' => Arr::get($delivery, 'city') ?? Arr::get($user, 'city'),
            'zip' => Arr::get($delivery, 'zip') ?? Arr::get($user, 'zip'),
            'country' => $fallbackCountry ? strtoupper((string) $fallbackCountry) : null,
        ];
    }

    protected function composeStreetLine(?string $street, ?string $house, ?string $room, ?string $fallback = null): ?string
    {
        $segments = [];

        if ($street) {
            $segments[] = $street;
        }

        $secondary = array_values(array_filter([$house, $room], static function ($value) {
            return $value !== null && $value !== '';
        }));

        if ($secondary) {
            $segments[] = implode('/', $secondary);
        }

        if ($segments) {
            return implode(', ', $segments);
        }

        return $fallback ?: null;
    }

    protected function config(string $key, $default = null)
    {
        return Config::get("dress.invoice.$key", $default);
    }
}

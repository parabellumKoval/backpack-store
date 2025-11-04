<?php

namespace Backpack\Store\app\Services\Shipping;

use Backpack\Store\app\Contracts\ShippingProviderInterface;
use Backpack\Store\app\DTO\ShippingQuoteRequest;
use Backpack\Store\app\DTO\ShippingQuoteResult;

class ShippingCalculator
{
    /** @var ShippingProviderInterface[] */
    protected array $providers;

    public function __construct(iterable $providers)
    {
        $this->providers = \is_array($providers) ? $providers : \iterator_to_array($providers);
    }

    /**
     * Главный фасад расчёта.
     * - проверяет calculable по настройкам
     * - делегирует нужному провайдеру
     */
    public function calculate(ShippingQuoteRequest $request): ShippingQuoteResult
    {
        $method = $this->resolveMethodByKey($request->methodKey);

        // Если метод не найден — считаем, что нерассчитываемый (или кидаем исключение).
        if (!$method) {
            return new ShippingQuoteResult('unknown', $request->methodKey, 'XXX', 0.0, [
                'note' => 'Unknown delivery method',
            ]);
        }

        if (empty($method['calculable'])) {
            // Нерассчитываемые — всегда 0
            $currency = 'XXX';
            return new ShippingQuoteResult($method['name'], $request->methodKey, $currency, 0.0, [
                'note' => 'Not calculable method',
            ]);
        }

        // Найти провайдера, который поддерживает ключ
        foreach ($this->providers as $provider) {
            if ($provider->supports($request->methodKey)) {
                $quote = $provider->quote($request);
                return $this->applyFreeShipping($quote, $request);
            }
        }

        // Нет провайдера — возвращаем 0
        return new ShippingQuoteResult('unknown', $request->methodKey, 'XXX', 0.0, [
            'note' => 'No provider for this methodKey',
        ]);
    }

    protected function applyFreeShipping(ShippingQuoteResult $quote, ShippingQuoteRequest $request): ShippingQuoteResult
    {
        $country = strtoupper($request->destinationCountry ?? '');
        if ($country === '') {
            return $quote;
        }

        $enabled = \Settings::get('shipping.free_enabled', false, ['country' => $country]);

        if (! $enabled) {
            return $quote;
        }

        $minPrice = \Settings::get('shipping.free_min_price', null, ['country' => $country]);
        if ($minPrice === null) {
            return $quote;
        }

        $minPrice = (float) $minPrice;
        $baseAmount = $this->calculateFreeShippingBaseAmount($request, $country);

        if ($baseAmount < $minPrice) {
            return $quote;
        }

        $breakdown = $quote->breakdown;
        $breakdown['free_shipping'] = true;
        $breakdown['original_amount'] = $quote->amount;
        $breakdown['free_shipping_threshold'] = $minPrice;
        $breakdown['free_shipping_base_amount'] = $baseAmount;

        return new ShippingQuoteResult(
            $quote->provider,
            $quote->methodKey,
            $quote->currency,
            0.0,
            $breakdown
        );
    }

    protected function calculateFreeShippingBaseAmount(ShippingQuoteRequest $request, string $country): float
    {
        $meta = $request->meta ?? [];
        $amount = (float) ($meta['subtotal'] ?? 0.0);

        $amount -= $this->extractDiscountComponent($meta, 'promocode_discount', 'shipping.free_include_promocode', $country);
        $amount -= $this->extractDiscountComponent($meta, 'bonus_discount', 'shipping.free_include_bonuses', $country);
        $amount -= $this->extractDiscountComponent($meta, 'personal_discount', 'shipping.free_include_personal_discount', $country);

        return max(0.0, round($amount, 2));
    }

    protected function extractDiscountComponent(array $meta, string $key, string $settingKey, string $country): float
    {
        $include = \Settings::get($settingKey, false, ['country' => $country]);

        if (! $include) {
            return 0.0;
        }

        return max(0.0, round((float) ($meta[$key] ?? 0.0), 2));
    }

    /**
     * Берём список из настроек dress.delivery.methods и ищем по ключу name_type.
     */
    protected function resolveMethodByKey(string $methodKey): ?array
    {
        $methods = \Settings::get('dress.delivery.methods', []);
        foreach ($methods as $item) {
            $key = ($item['name'] ?? '') . '_' . ($item['type'] ?? '');
            if ($key === $methodKey) {
                return $item;
            }
        }
        return null;
    }
}

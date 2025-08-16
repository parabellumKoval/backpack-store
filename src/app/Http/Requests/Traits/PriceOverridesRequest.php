<?php

namespace App\Http\Requests\Traits;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PriceOverridesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Вы можете переиспользовать эти правила в других FormRequest:
     * array_merge($other, PriceOverridesRequest::rulesArray())
     */
    public static function rulesArray(): array
    {
        // Опциональные белые списки
        $countryCodes = array_keys(\Store::countries() ?? []); // ['uk' => [...]] -> ['uk', ...]
        $currencyCodes = collect(\Store::currencies() ?? [])
            ->pluck('code')
            ->map(fn($c) => strtoupper((string)$c))
            ->all();

        return [
            'priceOverrides'                 => ['nullable', 'array'],
            'priceOverrides.*.country'       => [
                'required',
                'string',
                'regex:/^[a-z]{2}$/i',   // 2 буквы
                'distinct:strict',
                // Rule::in($countryCodes), // включите, если нужен контроль списка стран
            ],
            'priceOverrides.*.currency'      => [
                'required',
                'string',
                'regex:/^[a-z]{3}$/i',   // 3 буквы
                // Rule::in($currencyCodes), // включите, если нужен контроль списка валют
            ],
            'priceOverrides.*.price'         => ['required', 'numeric', 'min:0'],
            'priceOverrides.*.old_price'     => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function rules(): array
    {
        return self::rulesArray();
    }

    public function messages(): array   { return self::messagesArray(); }
    public function attributes(): array { return self::attributesArray(); }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        if (!isset($data['priceOverrides']) || !is_array($data['priceOverrides'])) {
            $data['priceOverrides'] = [];
        }

        // Нормализация значений
        $data['priceOverrides'] = array_values(array_map(function ($row) {
            if (!is_array($row)) $row = [];

            $country = isset($row['country']) ? strtolower(trim((string)$row['country'])) : null;
            $currency = isset($row['currency']) ? strtoupper(trim((string)$row['currency'])) : null;

            // Приводим числа и пустые строки
            $price = $row['price'] ?? null;
            $oldPrice = $row['old_price'] ?? null;

            $price = is_numeric($price) ? (float)$price : $price;
            $oldPrice = ($oldPrice === '' || $oldPrice === null) ? null : (is_numeric($oldPrice) ? (float)$oldPrice : $oldPrice);

            return [
                'country'   => $country,
                'currency'  => $currency,
                'price'     => $price,
                'old_price' => $oldPrice,
            ];
        }, $data['priceOverrides']));

        $this->replace($data);
    }

    public static function messagesArray(): array
    {
        return [
            'priceOverrides.array'                 => __('validation.array'),

            'priceOverrides.*.country.required'    => __('validation.required'),
            'priceOverrides.*.country.string'      => __('validation.string'),
            'priceOverrides.*.country.regex'       => __('validation.regex'),
            'priceOverrides.*.country.distinct'    => __('validation.distinct'),

            'priceOverrides.*.currency.required'   => __('validation.required'),
            'priceOverrides.*.currency.string'     => __('validation.string'),
            'priceOverrides.*.currency.regex'      => __('validation.regex'),

            'priceOverrides.*.price.required'      => __('validation.required'),
            'priceOverrides.*.price.numeric'       => __('validation.numeric'),
            'priceOverrides.*.price.min'           => __('validation.min.numeric'),

            'priceOverrides.*.old_price.numeric'   => __('validation.numeric'),
            'priceOverrides.*.old_price.min'       => __('validation.min.numeric'),
        ];
    }

    public static function attributesArray(): array
    {
        return [
            'priceOverrides'               => __('backpack-store::admin.price_overrides.label'),
            'priceOverrides.*.country'     => __('backpack-store::admin.price_overrides.country'),
            'priceOverrides.*.currency'    => __('backpack-store::admin.price_overrides.currency'),
            'priceOverrides.*.price'       => __('backpack-store::admin.price_overrides.price'),
            'priceOverrides.*.old_price'   => __('backpack-store::admin.price_overrides.old_price'),
        ];
    }
}

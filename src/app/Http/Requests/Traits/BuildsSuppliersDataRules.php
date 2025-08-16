<?php

namespace Backpack\Store\app\Http\Requests\Traits;

use Illuminate\Support\Arr;

trait BuildsSuppliersDataRules
{
    /**
     * Нормализация массива suppliersData (булевая активность и т.п.)
     */
    protected function normalizeSuppliersData(array $suppliers): array
    {
        foreach ($suppliers as $i => $row) {
            $row['is_active'] = filter_var(Arr::get($row, 'is_active', false), FILTER_VALIDATE_BOOLEAN);

            if (isset($row['price']) && is_string($row['price'])) {
                $row['price'] = trim($row['price']);
            }
            if (isset($row['in_stock']) && is_string($row['in_stock'])) {
                $row['in_stock'] = trim($row['in_stock']);
            }

            $suppliers[$i] = $row;
        }
        return $suppliers;
    }

    /**
     * Базовые и условные правила для suppliersData.* по индексам.
     */
    protected function buildSuppliersDataRules(array $suppliers): array
    {
        $rules = [
            'suppliersData'                 => ['array'],
            'suppliersData.*.is_active'     => ['boolean'],
            'suppliersData.*.price'         => ['nullable', 'numeric', 'min:0'],
            'suppliersData.*.in_stock'      => ['nullable', 'integer', 'min:0'],
            // добавляйте другие под‑поля при необходимости
        ];

        foreach ($suppliers as $i => $row) {
            $active = filter_var(Arr::get($row, 'is_active', false), FILTER_VALIDATE_BOOLEAN);
            if ($active) {
                $rules["suppliersData.$i.price"][]    = 'required';
                $rules["suppliersData.$i.in_stock"][] = 'required';
            }
        }

        return $rules;
    }

    protected function suppliersDataAttributes(): array
    {
        return [
            'suppliersData.*.is_active' => 'Активен',
            'suppliersData.*.price'     => 'Цена',
            'suppliersData.*.in_stock'  => 'Остаток на складе',
        ];
    }

    protected function suppliersDataMessages(): array
    {
        return [
            'suppliersData.array'              => 'Некорректный формат данных поставщиков.',
            'suppliersData.*.is_active.boolean'=> 'Поле ":attribute" должно быть булевым.',
            'suppliersData.*.price.required'   => 'Укажите цену для активного поставщика.',
            'suppliersData.*.price.numeric'    => 'Поле ":attribute" должно быть числом.',
            'suppliersData.*.price.min'        => 'Поле ":attribute" должно быть не меньше 0.',
            'suppliersData.*.in_stock.required'=> 'Укажите остаток для активного поставщика.',
            'suppliersData.*.in_stock.integer' => 'Поле ":attribute" должно быть целым числом.',
            'suppliersData.*.in_stock.min'     => 'Поле ":attribute" должно быть не меньше 0.',
        ];
    }
}

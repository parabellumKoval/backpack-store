<?php

namespace Backpack\Store\app\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SearchProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q'         => ['nullable', 'string', 'max:200'],
            'page'      => ['nullable', 'integer', 'min:1'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:100'],

            // простые фильтры
            'brand'     => ['nullable', 'string', 'max:100'],
            'category'  => ['nullable', 'string', 'max:100'],
            'in_stock'  => ['nullable', 'boolean'],

            // сортировка: popularity:desc | price:asc и т.п.
            'sort'      => ['nullable', 'string', 'regex:/^[a-zA-Z0-9_]+:(asc|desc)$/i'],
        ];
    }

    public function messages(): array
    {
        return [
            'sort.regex' => 'Параметр sort должен быть вида field:asc|desc, например popularity:desc',
        ];
    }
}

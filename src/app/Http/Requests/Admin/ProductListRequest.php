<?php

namespace Backpack\Store\app\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ProductListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return backpack_auth()->check();
    }

    public function rules(): array
    {
        return [
            'page'              => ['required','string','max:64'],
            'name'              => ['required','string','max:200'],
            // 'slug'              => ['required','string','max:96'],
            'capacity'          => ['required','integer','min:1','max:200'],
            // 'priority_sources'  => ['nullable','array'],
            // 'sort_order'        => ['nullable','array'],
            // 'kind'              => ['nullable','in:up,cross'],
            // 'foundation'        => ['nullable','array'],
            // 'countries'         => ['nullable','array'],
            'is_active'         => ['boolean'],
        ];
    }
}

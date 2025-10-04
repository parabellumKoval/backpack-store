<?php

namespace Backpack\Store\app\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SeoPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return backpack_auth()->check();
    }

    public function rules(): array
    {
        return [
            'slug'              => ['required','string','max:254'],
            'countries'         => ['required','array'],
            'is_active'         => ['boolean'],
        ];
    }
}

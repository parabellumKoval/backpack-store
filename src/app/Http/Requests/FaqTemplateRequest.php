<?php

namespace Backpack\Store\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FaqTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return backpack_auth()->check();
    }

    public function rules(): array
    {
        return [
            'name' => ['required'],
        ];
    }
}

<?php

namespace Backpack\Store\app\Http\Requests;

use App\Http\Requests\Request;
use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // only allow updates if the user is logged in
        return backpack_auth()->check();
    }

    protected function prepareForValidation(): void
    {
        foreach (['countries', 'store_only_countries', 'storefronts'] as $field) {
            if (!$this->has($field)) {
                continue;
            }

            $this->merge([
                $field => $this->normalizeCountryList($this->input($field)),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'countries' => 'nullable|array',
            'countries.*' => 'string|min:2|max:3',
            'store_only_countries' => 'nullable|array',
            'store_only_countries.*' => 'string|min:2|max:3',
            'storefronts' => 'nullable|array',
            'storefronts.*' => 'string|min:1|max:64',
        ];
    }

    /**
     * Get the validation attributes that apply to the request.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            //
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array
     */
    public function messages()
    {
        return [
            //
        ];
    }

    protected function normalizeCountryList($value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $items = is_array($value) ? $value : [$value];

        $normalized = collect($items)
            ->map(function ($item) {
                return is_string($item) ? trim($item) : $item;
            })
            ->filter(function ($item) {
                return $item !== null && $item !== '';
            })
            ->unique()
            ->values()
            ->all();

        return empty($normalized) ? null : $normalized;
    }
}

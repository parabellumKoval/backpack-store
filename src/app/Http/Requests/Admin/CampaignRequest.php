<?php

namespace Backpack\Store\app\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return backpack_auth()->check();
    }

    public function rules(): array
    {
        $id = $this->route('id') ?? $this->route('campaign');

        return [
            'name' => ['required', 'string', 'max:200'],
            'slug' => [
                'nullable',
                'string',
                'max:200',
                Rule::unique('ak_campaigns', 'slug')->ignore($id),
            ],
            'discount_percent' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'product_source' => ['required', Rule::in(['filters', 'manual', 'mixed'])],
            'catalog_banner_frequency' => ['nullable', 'integer', 'min:1', 'max:500'],
            'catalog_banner_position' => ['nullable', 'integer', 'min:1', 'max:500'],
            'countries' => ['nullable', 'array'],
            'countries.*' => ['string', 'max:8'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
            'is_timed' => ['sometimes', 'boolean'],
            'show_timer_card' => ['sometimes', 'boolean'],
            'show_timer_product' => ['sometimes', 'boolean'],
            'add_to_main_banner' => ['sometimes', 'boolean'],
            'add_banner_to_catalog' => ['sometimes', 'boolean'],
        ];
    }
}

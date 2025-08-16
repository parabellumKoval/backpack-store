<?php

namespace Backpack\Store\app\Http\Requests;

use App\Http\Requests\Request;
use Illuminate\Foundation\Http\FormRequest;

use Backpack\Store\app\Http\Requests\Traits\BuildsSuppliersDataRules;
use App\Http\Requests\Traits\PriceOverridesRequest;

class ProductModificationRequest extends FormRequest
{
    use BuildsSuppliersDataRules;
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
        $this->merge([
            'suppliersData' => $this->normalizeSuppliersData($this->input('suppliersData', [])),
        ]);
    }
    
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'parent_id' => 'required',
            'short_name' => 'required|min:1|max:255',
        ];


        $rules = array_merge(
            $rules,
            $this->buildSuppliersDataRules($this->input('suppliersData', [])),
            PriceOverridesRequest::rulesArray()
        );

        return $rules;
    }

    /**
     * Get the validation attributes that apply to the request.
     *
     * @return array
     */
    public function attributes()
    {
        return array_merge(
            [],
            $this->suppliersDataAttributes(),
            PriceOverridesRequest::attributesArray()
        );
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array
     */
    public function messages()
    {
        return array_merge(
            [],
            $this->suppliersDataMessages(),
            PriceOverridesRequest::messagesArray()
        );
    }
}

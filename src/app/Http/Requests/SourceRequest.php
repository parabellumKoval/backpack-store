<?php

namespace Backpack\Store\app\Http\Requests;

use App\Http\Requests\Request;
use Illuminate\Foundation\Http\FormRequest;

class SourceRequest extends FormRequest
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

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
          'name' => 'required|min:1|max:255',
          'key' => 'required|min:1|max:255',
          'supplier' => 'required',
          'every_minutes' => 'required|integer|min:60',
          'link' => 'required|url',
          'item' => 'required',
          'fieldName' => 'required',
          'fieldPrice' => 'required',
          'fieldInStock' => 'required',
          'fieldCode' => 'required_without:fieldBarcode',
          'fieldBarcode' => 'required_without:fieldCode',
          // 'brandsData.*.brand_id' => 'required',
          // 'brandsData.*.brand' => 'required'
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
}

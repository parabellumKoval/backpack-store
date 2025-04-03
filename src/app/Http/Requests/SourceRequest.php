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
          'every_minutes' => 'nullable|required_if:type,xml_link|integer|min:60',
          'link' => 'nullable|required_if:type,xml_link',
        //   'file' => 'nullable|required_if:type,file',
          'item' => 'required_if:type,xml_link',
          'fieldName' => 'nullable|required_if:type,xml_link',
          'fieldPrice' => 'nullable|required_if:type,xml_link',
          'fieldInStock' => 'nullable|required_if:type,xml_link',
          'fieldCode' => [
            'nullable',
            function ($attribute, $value, $fail) {
                if ($this->input('type') === 'xml_link' && empty($value) && empty($this->input('fieldBarcode'))) {
                    $fail('Поле fieldCode обязательно, если fieldBarcode не заполнено.');
                }
            },
          ],
          'fieldBarcode' => [
            'nullable',
            function ($attribute, $value, $fail) {
                if ($this->input('type') === 'xml_link' && empty($value) && empty($this->input('fieldCode'))) {
                    $fail('Поле fieldBarcode обязательно, если fieldCode не заполнено.');
                }
            },
          ],
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

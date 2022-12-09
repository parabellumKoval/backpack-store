<?php

namespace Aimix\Shop\app\Http\Requests;

use App\Http\Requests\Request;
use Illuminate\Foundation\Http\FormRequest;

class OrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // only allow updates if the user is logged in
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $this->redirect = url()->previous();
        // dd(session()->all());
        session()->flash('inputs', $this->input());

        $array = [
            'firstname' => 'required_if:register,1|nullable|min:2|max:255',
            'telephone' => 'nullable|min:5|max:20',
            'email' => 'nullable|email|min:5|max:255',
            'comment' => 'nullable|max:3000',
            
        ];
        
        if($this->input('register')) {
          $array['email'] = 'required|email|unique:users,email|min:5|max:255';
          $array['password'] = 'required|confirmed|min:5|max:255';
          $this->redirect = $this->redirect . '#register';
        }
        
        return $array;
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

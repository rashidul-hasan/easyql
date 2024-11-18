<?php

namespace Rashidul\EasyQL\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateRequest extends FormRequest
{
    public function rules()
    {
        return [
            'model' => 'required|string|max:50',
            'data' => 'required|array'
        ];
    }

    public function messages()
    {
        return [
            'model.required' => 'model is required'
        ];
    }
}

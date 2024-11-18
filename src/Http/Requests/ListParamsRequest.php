<?php

namespace Rashidul\EasyQL\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListParamsRequest extends FormRequest
{
    public function rules()
    {
        return [
            'model' => 'required|string|max:50',
            'per_page' => 'integer|min:10',
            'page' => 'integer|min:0',
            'filter_type' => 'in:and,or'
        ];
    }

    public function messages()
    {
        return [
            'model.required' => 'model is required',
            'per_page.integer' => 'The per_page parameter must be a valid integer.'
        ];
    }
}

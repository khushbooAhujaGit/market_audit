<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mobile' => 'required|regex:/^[0-9]{10}$/|exists:users,mobile',
            'password' => ['required', 'min:8'],
        ];
    }

    public function messages()
    {
        return [
            'mobile.required' => 'The mobile number is required.',
            'mobile.regex' => 'The mobile number must be exactly 10 digits.',
            'mobile.exits' => 'The mobile number is incorrect or mismatched.',
            'password.required' => 'The password is required.',
            'password.min' => 'The password must be at least :min characters.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation errors',
            'data' => $validator->errors()
        ], 422));
    }
}

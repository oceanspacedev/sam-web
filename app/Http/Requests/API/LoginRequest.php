<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

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
            'version' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (version_compare($value, '2.0.0', '<')) {
                        $fail('Login gagal. Silakan update aplikasi SAM Anda ke versi minimal 2.0.0 melalui Google Play Store.');
                    }
                },
            ],
            'username' => 'required|string',
            'password' => 'required|string',
            'notif_id' => 'required|string',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'version.required' => 'Versi aplikasi wajib diisi',
            'username.required' => 'Username wajib diisi',
            'password.required' => 'Password wajib diisi',
            'notif_id.required' => 'Notification ID wajib diisi',
        ];
    }
}

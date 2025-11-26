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
            'version' => 'required|string|in:1.2.0',
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
            'version.in' => 'Gagal login, Update versi aplikasi SAM anda ke V1.0.3.',
            'username.required' => 'Username wajib diisi',
            'password.required' => 'Password wajib diisi',
            'notif_id.required' => 'Notification ID wajib diisi',
        ];
    }
}

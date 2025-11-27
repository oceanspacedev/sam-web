<?php

namespace App\Http\Requests\API;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'username' => ['required', 'string', 'unique:users,username', 'max:255', 'regex:/^\S*$/', 'alpha_dash'],
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'id_notif' => ['nullable', 'string', 'max:255'],
        ];

        $targetRole = $this->role_id ? Role::find($this->role_id) : null;
        $scopeLevel = $targetRole?->organizational_scope_level;

        if (in_array($scopeLevel, ['badanusaha', 'divisi', 'region', 'cluster'])) {
            $rules['badanusaha_ids'] = ['required', 'array', 'min:1'];
            $rules['badanusaha_ids.*'] = [Rule::exists('badan_usahas', 'id')->whereNull('deleted_at')];
        } else {
            $rules['badanusaha_ids'] = ['nullable', 'array'];
            $rules['badanusaha_ids.*'] = [Rule::exists('badan_usahas', 'id')->whereNull('deleted_at')];
        }

        if (in_array($scopeLevel, ['divisi', 'region', 'cluster'])) {
            $rules['divisi_ids'] = ['required', 'array', 'min:1'];
            $rules['divisi_ids.*'] = [Rule::exists('divisions', 'id')->whereNull('deleted_at')];
        } else {
            $rules['divisi_ids'] = ['nullable', 'array'];
            $rules['divisi_ids.*'] = [Rule::exists('divisions', 'id')->whereNull('deleted_at')];
        }

        if (in_array($scopeLevel, ['region', 'cluster'])) {
            $rules['region_ids'] = ['required', 'array', 'min:1'];
            $rules['region_ids.*'] = [Rule::exists('regions', 'id')->whereNull('deleted_at')];
        } else {
            $rules['region_ids'] = ['nullable', 'array'];
            $rules['region_ids.*'] = [Rule::exists('regions', 'id')->whereNull('deleted_at')];
        }

        if ($scopeLevel === 'cluster') {
            $rules['cluster_ids'] = ['required', 'array', 'min:1'];
            $rules['cluster_ids.*'] = [Rule::exists('clusters', 'id')->whereNull('deleted_at')];
        } else {
            $rules['cluster_ids'] = ['nullable', 'array'];
            $rules['cluster_ids.*'] = [Rule::exists('clusters', 'id')->whereNull('deleted_at')];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'username.required' => 'Username wajib diisi',
            'username.unique' => 'Username sudah digunakan',
            'username.regex' => 'Username tidak boleh mengandung spasi',
            'username.alpha_dash' => 'Username hanya boleh huruf, angka, dash dan underscore',
            'nama_lengkap.required' => 'Nama lengkap wajib diisi',
            'password.required' => 'Password wajib diisi',
            'password.min' => 'Password minimal 8 karakter',
            'role_id.required' => 'Role wajib dipilih',
            'role_id.exists' => 'Role tidak ditemukan',
            'badanusaha_ids.required' => 'Badan usaha wajib dipilih untuk role ini',
            'divisi_ids.required' => 'Divisi wajib dipilih untuk role ini',
            'region_ids.required' => 'Region wajib dipilih untuk role ini',
            'cluster_ids.required' => 'Cluster wajib dipilih untuk role ini',
        ];
    }
}

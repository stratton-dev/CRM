<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StructureUserStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('role')) {
            $this->merge([
                'role' => strtoupper((string) $this->input('role')),
            ]);
        }

        $first = trim((string) $this->input('first_name'));
        $last  = trim((string) $this->input('last_name'));
        $name  = trim((string) $this->input('name'));

        // If client sent first_name/last_name (or both), assemble name.
        if (($first !== '' || $last !== '') && $name === '') {
            $this->merge(['name' => trim($first . ' ' . $last)]);
        }

        // If only name sent, split it for first_name/last_name backfill.
        if ($name !== '' && $first === '' && $last === '') {
            $parts = preg_split('/\s+/u', $name) ?: [];
            $f = array_shift($parts) ?? '';
            $l = trim(implode(' ', $parts));
            $this->merge([
                'first_name' => $f !== '' ? $f : null,
                'last_name'  => $l !== '' ? $l : null,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'supabase_id' => 'nullable|string|max:255',
            'name' => 'required|string|max:255',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:150',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:255',
            'role' => 'required|string|in:ADMIN,DIRECTOR,MANAGER,SALES,LEADOWIEC,CLIENT_HR',
            'parent_supabase_id' => 'nullable|string|max:255',
            'team_group_path' => 'nullable|string|max:255',
            'contract_status' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:255',
            'address_json' => 'nullable|array',
            'documents_json' => 'nullable|array',
            'hierarchical_preview' => 'nullable|string|max:255',
            'password' => 'nullable|string|min:8|max:128',
            'send_password_reset' => 'nullable|boolean',
            'skip_supabase_user' => 'nullable|boolean',
        ];
    }
}

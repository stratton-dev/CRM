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
    }

    public function rules(): array
    {
        return [
            'supabase_id' => 'nullable|string|max:255',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:255',
            'role' => 'required|string|in:ADMIN,DIRECTOR,MANAGER,SALES,LEADOWIEC',
            'parent_supabase_id' => 'nullable|string|max:255',
            'team_group_path' => 'nullable|string|max:255',
            'contract_status' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:255',
            'address_json' => 'nullable|array',
            'documents_json' => 'nullable|array',
            'hierarchical_preview' => 'nullable|string|max:255',
        ];
    }
}

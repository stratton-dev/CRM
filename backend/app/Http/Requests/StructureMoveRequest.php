<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StructureMoveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_keycloak_id' => 'required|string|max:255',
            'new_parent_keycloak_id' => 'nullable|string|max:255',
            'new_team_group_path' => 'nullable|string|max:255',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class KeycloakTeamStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9_-]*$/'],
            'display_name' => ['nullable', 'string', 'max:120'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StructureRemoveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_supabase_id' => 'required|string|max:255',
        ];
    }
}

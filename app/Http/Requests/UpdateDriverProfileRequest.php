<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDriverProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('driver'));
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:20'],
            'racing_number' => ['nullable', 'integer', 'min:1', 'max:999'],
            'avatar_color' => ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{3}){1,2}$/'],
            'avatar_text_color' => ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{3}){1,2}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'nickname.max' => 'Nickname must be 20 characters or less.',
            'racing_number.min' => 'Racing number must be between 1 and 999.',
            'racing_number.max' => 'Racing number must be between 1 and 999.',
            'avatar_color.regex' => 'Avatar color must be a valid hex color like #ef3340.',
            'avatar_text_color.regex' => 'Avatar text color must be a valid hex color like #ffffff.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\DriverProfileVisibility;
use App\Enums\NotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $rules = [
            'driver_profile_visibility' => ['required', Rule::enum(DriverProfileVisibility::class)],
            'notifications' => ['required', 'array'],
        ];

        foreach (NotificationType::cases() as $type) {
            $rules['notifications.'.$type->value] = ['required', 'boolean'];
        }

        return $rules;
    }
}

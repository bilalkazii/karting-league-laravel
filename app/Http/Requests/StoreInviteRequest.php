<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('group');

        return $group !== null && $this->user()?->can('manageMembers', $group) === true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $email = strtolower(trim($email));
            $this->merge(['email' => $email === '' ? null : $email]);
        }
    }

    public function rules(): array
    {
        return [
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }
}

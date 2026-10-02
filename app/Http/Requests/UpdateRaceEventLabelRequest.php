<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRaceEventLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Group admin/organizer, the same rule that governs editing a race.
        return $this->user()->can('update', $this->route('race'));
    }

    public function rules(): array
    {
        return [
            // Empty means "no label": the race keeps existing and the
            // leaderboard falls back to showing the race name.
            'event_label' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9 \-_.&]*$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'event_label.regex' => 'The event label may only contain letters, numbers, spaces and the characters - _ . &',
        ];
    }
}

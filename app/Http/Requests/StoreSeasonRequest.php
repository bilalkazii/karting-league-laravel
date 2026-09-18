<?php

namespace App\Http\Requests;

use App\Enums\SeasonStatus;
use App\Models\Season;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSeasonRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'group_id' => ['required', 'integer', Rule::exists('groups', 'id')],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('seasons', 'name')->where(fn ($query) => $query->where('group_id', $this->input('group_id'))),
            ],
            'status' => ['required', Rule::in(array_column(SeasonStatus::cases(), 'value'))],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('status') !== SeasonStatus::Active->value) {
                return;
            }

            $groupHasActive = Season::query()
                ->where('group_id', $this->input('group_id'))
                ->where('status', SeasonStatus::Active->value)
                ->exists();

            if ($groupHasActive) {
                $validator->errors()->add('status', 'This group already has an active season.');
            }
        });
    }
}

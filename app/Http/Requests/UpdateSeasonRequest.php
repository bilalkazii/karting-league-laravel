<?php

namespace App\Http\Requests;

use App\Enums\SeasonStatus;
use App\Models\Season;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSeasonRequest extends FormRequest
{
    private const TRANSITIONS = [
        'draft' => ['active', 'completed'],
        'active' => ['completed', 'archived'],
        'completed' => ['archived'],
        'archived' => [],
    ];

    public function rules(): array
    {
        $season = $this->route('season');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('seasons', 'name')
                    ->ignore($season->id)
                    ->where(fn ($query) => $query->where('group_id', $season->group_id)),
            ],
            'status' => ['required', Rule::in(array_column(SeasonStatus::cases(), 'value'))],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $season = $this->route('season');
        $status = $this->input('status');

        if ($status === $season->status->value) {
            return;
        }

        $validator->after(function (Validator $validator) use ($season, $status) {
            if (! in_array($status, self::TRANSITIONS[$season->status->value] ?? [], true)) {
                $validator->errors()->add(
                    'status',
                    "Cannot change season status from {$season->status->value} to {$status}."
                );

                return;
            }

            if ($status === SeasonStatus::Active->value) {
                $groupHasActive = Season::query()
                    ->where('group_id', $season->group_id)
                    ->where('id', '!=', $season->id)
                    ->where('status', SeasonStatus::Active->value)
                    ->exists();

                if ($groupHasActive) {
                    $validator->errors()->add('status', 'This group already has an active season.');
                }
            }
        });
    }
}

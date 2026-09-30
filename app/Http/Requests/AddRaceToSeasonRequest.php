<?php

namespace App\Http\Requests;

use App\Enums\RaceStatus;
use App\Models\Race;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AddRaceToSeasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $season = $this->route('season');

        return $season !== null && $this->user()?->can('update', $season) === true;
    }

    public function rules(): array
    {
        return [
            'race_id' => ['required', 'integer', 'exists:races,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $season = $this->route('season');
            $race = Race::find($this->input('race_id'));

            if ($season === null || $race === null) {
                return;
            }

            if ($race->group_id !== $season->group_id) {
                $validator->errors()->add('race_id', 'That race belongs to a different group.');

                return;
            }

            if ($season->races()->where('races.id', $race->id)->exists()) {
                $validator->errors()->add('race_id', 'That race is already part of this season.');

                return;
            }

            if (($race->status?->value ?? $race->getRawOriginal('status')) === RaceStatus::Cancelled->value) {
                $validator->errors()->add('race_id', 'Cancelled races cannot be added to a season.');
            }
        });
    }
}

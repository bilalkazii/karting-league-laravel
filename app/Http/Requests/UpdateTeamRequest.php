<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamRequest extends FormRequest
{
    public function rules(): array
    {
        $team = $this->route('team');

        return [
            'name' => [
                'required', 'string', 'max:40',
                Rule::unique('teams', 'name')
                    ->ignore($team->id)
                    ->where(fn ($query) => $query->where('group_id', $team->group_id)),
            ],
        ];
    }
}

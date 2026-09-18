<?php

namespace App\Http\Requests;

use App\Enums\RaceFormat;
use App\Models\Group;
use App\Models\Race;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = Group::findOrFail($this->input('group_id'));

        return $this->user()->can('create', [Race::class, $group]);
    }

    public function rules(): array
    {
        return [
            'group_id' => ['required', 'integer', Rule::exists('groups', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'venue_name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'format' => ['required', Rule::in(array_column(RaceFormat::cases(), 'value'))],
            'qualifying_lap_count' => ['required', 'integer', 'min:1', 'max:10'],
            'rules' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

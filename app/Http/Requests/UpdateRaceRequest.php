<?php

namespace App\Http\Requests;

use App\Enums\RaceFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('race'));
    }

    public function rules(): array
    {
        return [
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

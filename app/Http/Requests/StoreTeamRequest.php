<?php

namespace App\Http\Requests;

use App\Models\Group;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Team names are edited independently of the drivers on the team: only the
 * teams row is written, so race_entries history is never touched.
 */
class StoreTeamRequest extends FormRequest
{
    public const MAX_MEMBERS = 2;

    public function rules(): array
    {
        return [
            'group_id' => ['required', 'integer', Rule::exists('groups', 'id')],
            'name' => [
                'required', 'string', 'max:40',
                Rule::unique('teams', 'name')->where(
                    fn ($query) => $query->where('group_id', $this->input('group_id'))
                ),
            ],
            'driver_ids' => ['nullable', 'array', 'max:'.self::MAX_MEMBERS],
            'driver_ids.*' => ['integer', Rule::exists('drivers', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'driver_ids.max' => 'A team can have at most '.self::MAX_MEMBERS.' drivers.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $ids = array_map('intval', (array) ($this->input('driver_ids') ?: []));

            if ($ids !== array_values(array_unique($ids))) {
                $validator->errors()->add('driver_ids', 'Each driver can only be listed once.');
            }

            $groupId = (int) $this->input('group_id');

            if ($ids !== [] && Group::find($groupId)?->members()->whereIn('drivers.id', $ids)->count() !== count($ids)) {
                $validator->errors()->add(
                    'driver_ids',
                    'Drivers must be members of this group before they can join a team.'
                );
            }
        });
    }
}

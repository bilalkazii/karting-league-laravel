<?php

namespace App\Http\Requests;

use App\Models\Driver;
use App\Models\Group;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Replaces a team's membership. Only team_members rows change; drivers and
 * their race_entries are left untouched so historical results survive.
 */
class UpdateTeamMembersRequest extends FormRequest
{
    /**
     * A team's existing size is always allowed, so a legacy or manually built
     * team can still be renamed and re-saved without dropping a driver. New
     * and larger lineups are still capped at the pairing size.
     */
    private function maxMembers(): int
    {
        $team = $this->route('team');
        $current = $team instanceof Team ? $team->members()->count() : 0;

        return max(StoreTeamRequest::MAX_MEMBERS, $current);
    }

    public function rules(): array
    {
        return [
            'driver_ids' => ['present', 'array', 'max:'.$this->maxMembers()],
            'driver_ids.*' => ['integer', Rule::exists('drivers', 'id')],
            'confirm_removals' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'driver_ids.required' => 'Select the drivers on this team.',
            'driver_ids.max' => 'A team can have at most '.$this->maxMembers().' drivers.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $ids = array_map('intval', (array) $this->input('driver_ids', []));

            if ($ids !== array_values(array_unique($ids))) {
                $validator->errors()->add('driver_ids', 'Each driver can only be listed once.');
            }

            $team = $this->route('team');

            if ($ids !== [] && Group::find($team->group_id)?->members()->whereIn('drivers.id', $ids)->count() !== count($ids)) {
                $validator->errors()->add(
                    'driver_ids',
                    'Drivers must be members of this group before they can join a team.'
                );
            }

            $this->guardSilentRemovals($validator, $ids);
        });
    }

    /**
     * Dropping a checkbox must not quietly remove a driver.
     *
     * The lineup editor posts the full new set, so a member can disappear
     * simply by not being ticked. Shrinking the team therefore requires the
     * submitter to acknowledge it, and the error names who would be removed so
     * nobody has to guess.
     */
    private function guardSilentRemovals(Validator $validator, array $ids): void
    {
        $team = $this->route('team');

        if (! $team instanceof Team) {
            return;
        }

        $removed = $team->members()
            ->whereNotIn('drivers.id', $ids === [] ? [0] : $ids)
            ->with('profile')
            ->get();

        if ($removed->isEmpty()) {
            return;
        }

        if ($this->boolean('confirm_removals')) {
            return;
        }

        $names = $removed->map(
            static fn (Driver $driver): string => $driver->profile?->full_name ?? $driver->display_name
        )->implode(', ');

        $validator->errors()->add(
            'driver_ids',
            "Confirm to remove {$names} from this team. Their results are kept, but they stop scoring for the team."
        );
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Group;
use Illuminate\Foundation\Http\FormRequest;

class StoreDriverImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Group admin/organizer: the same rule that guards team and race edits.
        $group = Group::find($this->integer('group_id'));

        return $group !== null && $this->user()->can('update', $group);
    }

    public function rules(): array
    {
        return [
            'group_id' => ['required', 'integer'],
            'file' => ['required', 'file', 'max:2048', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel,text/plain'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimetypes' => 'The upload must be a plain .csv file.',
        ];
    }
}

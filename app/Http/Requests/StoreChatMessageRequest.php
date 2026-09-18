<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->driver !== null;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:500'],
        ];
    }
}

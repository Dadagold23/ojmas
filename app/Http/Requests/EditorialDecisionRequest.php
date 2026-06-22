<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EditorialDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['Editor', 'Managing Editor', 'Super Admin']);
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:accepted,rejected,revision_requested'],
            'message' => ['required', 'string'],
        ];
    }
}

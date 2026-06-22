<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignReviewerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['Editor', 'Managing Editor', 'Super Admin']);
    }

    public function rules(): array
    {
        return [
            'reviewer_id' => ['required', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
        ];
    }
}

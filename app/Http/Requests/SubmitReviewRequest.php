<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['Reviewer', 'Super Admin']);
    }

    public function rules(): array
    {
        return [
            'recommendation' => ['required', 'in:accept,minor_revision,major_revision,reject'],
            // A report without author-facing comments is not a review the
            // decision workflow can rely on.
            'comments_for_author' => ['required', 'string', 'max:20000'],
            'comments_for_editor' => ['nullable', 'string', 'max:20000'],
        ];
    }
}

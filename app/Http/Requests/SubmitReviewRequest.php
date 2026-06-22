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
            'comments_for_author' => ['nullable', 'string'],
            'comments_for_editor' => ['nullable', 'string'],
        ];
    }
}

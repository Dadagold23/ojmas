<?php

namespace App\Http\Requests;

use App\Services\Orcid\OrcidId;
use Illuminate\Foundation\Http\FormRequest;

class StoreSubmissionRequest extends FormRequest
{
    private const MAX_UPLOAD_KB = 1048576; // 1 GB

    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['Author', 'Super Admin']);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'abstract' => ['required', 'string'],
            'keywords' => ['nullable', 'string', 'max:500'],
            'subject_area' => ['nullable', 'string', 'max:255'],
            'co_authors' => ['nullable', 'array'],
            'co_authors.*.full_name' => ['nullable', 'string', 'max:255'],
            'co_authors.*.email' => ['nullable', 'email', 'max:255'],
            'co_authors.*.institution' => ['nullable', 'string', 'max:255'],
            'co_authors.*.country' => ['nullable', 'string', 'max:100'],
            // Co-author identifiers are optional metadata: an unparseable value
            // is dropped at storage time rather than rejecting the manuscript.
            'co_authors.*.orcid' => ['nullable', 'string', 'max:39'],
            'author_orcid' => ['nullable', 'string', 'max:39', $this->orcidRule()],
            'manuscript' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:' . self::MAX_UPLOAD_KB],
            'cover_letter' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:' . self::MAX_UPLOAD_KB],
            'supplementary' => ['nullable', 'file', 'mimes:pdf,doc,docx,zip', 'max:' . self::MAX_UPLOAD_KB],
        ];
    }

    public function messages(): array
    {
        return [
            'manuscript.uploaded' => 'The manuscript failed to upload. Please try again with a PDF, DOC, or DOCX file up to 1 GB.',
            'cover_letter.uploaded' => 'The cover letter failed to upload. Please try again with a PDF, DOC, or DOCX file up to 1 GB.',
            'supplementary.uploaded' => 'The supplementary file failed to upload. Please try again with a supported file up to 1 GB.',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                foreach ([
                    'manuscript' => 'manuscript',
                    'cover_letter' => 'cover letter',
                    'supplementary' => 'supplementary file',
                ] as $field => $label) {
                    $file = $this->file($field);

                    if ($file && !$file->isValid()) {
                        $validator->errors()->add($field, $this->friendlyUploadErrorMessage($label, $file->getError()));
                    }
                }
            },
        ];
    }

    private function orcidRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (filled($value) && ! OrcidId::isWellFormed((string) $value)) {
                $fail('Enter a valid ORCID iD, for example 0000-0002-1825-0097.');
            }
        };
    }

    private function friendlyUploadErrorMessage(string $label, int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "The {$label} is larger than the server allows. Please reduce the file size and try again.",
            UPLOAD_ERR_PARTIAL => "The {$label} was only partially uploaded. Please try again.",
            UPLOAD_ERR_NO_TMP_DIR => "The server is missing a temporary upload directory. Please contact support.",
            UPLOAD_ERR_CANT_WRITE => "The server could not save the {$label}. Please try again or contact support.",
            UPLOAD_ERR_EXTENSION => "A server extension stopped the {$label} upload. Please try again or contact support.",
            default => "The {$label} failed to upload. Please try again.",
        };
    }
}

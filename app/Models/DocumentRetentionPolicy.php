<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Configurable retention periods for each class of document.
 */
class DocumentRetentionPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug', 'name', 'description', 'retain_original_days', 'retain_formatted_days',
        'retain_workspace_days', 'retain_review_days', 'retain_audit_days',
        'delete_formatted_on_publication', 'anonymise_after_review', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'retain_original_days' => 'integer',
            'retain_formatted_days' => 'integer',
            'retain_workspace_days' => 'integer',
            'retain_review_days' => 'integer',
            'retain_audit_days' => 'integer',
            'delete_formatted_on_publication' => 'boolean',
            'anonymise_after_review' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The conservative journal default: the original submission is retained
     * indefinitely because it is the scholarly record of what was submitted.
     */
    public static function defaultPolicy(): self
    {
        return self::firstOrCreate(
            ['slug' => 'ojmas_default'],
            [
                'name' => 'OJMAS Default Retention',
                'description' => 'The original submission is retained indefinitely. Derived documents, '
                    .'workspace versions and review records are retained for seven years. '
                    .'Reviewer anonymity material is discarded once review concludes.',
                'retain_original_days' => null,
                'retain_formatted_days' => 2555,
                'retain_workspace_days' => 2555,
                'retain_review_days' => 2555,
                'retain_audit_days' => 2555,
                'delete_formatted_on_publication' => false,
                'anonymise_after_review' => true,
                'is_active' => true,
            ],
        );
    }

    public static function active(): self
    {
        return self::where('is_active', true)->orderBy('id')->first() ?? self::defaultPolicy();
    }

    public function labelFor(?int $days): string
    {
        return $days === null ? 'Indefinitely' : $this->humanise($days);
    }

    private function humanise(int $days): string
    {
        if ($days % 365 === 0 && $days >= 365) {
            $years = intdiv($days, 365);

            return $years.' year'.($years === 1 ? '' : 's');
        }

        if ($days % 30 === 0 && $days >= 30) {
            $months = intdiv($days, 30);

            return $months.' month'.($months === 1 ? '' : 's');
        }

        return $days.' day'.($days === 1 ? '' : 's');
    }

    /** @return array<string, string> */
    public function summary(): array
    {
        return [
            'Original submissions' => $this->labelFor($this->retain_original_days),
            'Formatted documents' => $this->labelFor($this->retain_formatted_days),
            'Workspace versions' => $this->labelFor($this->retain_workspace_days),
            'Review records' => $this->labelFor($this->retain_review_days),
            'Audit trail' => $this->labelFor($this->retain_audit_days),
        ];
    }
}
<?php

namespace App\Services\Orcid;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Records ORCID lifecycle events using the journal's existing audit table.
 *
 * Callers pass only identifiers and outcomes. Tokens, authorization codes and
 * the client secret must never reach this class.
 */
class OrcidAudit
{
    /** @param array<string, mixed> $old */
    public function record(
        ?User $actor,
        string $event,
        Model $subject,
        array $old = [],
        array $new = [],
    ): void {
        try {
            AuditLog::create([
                'user_id' => $actor?->getKey(),
                'event' => $event,
                'auditable_type' => $subject::class,
                'auditable_id' => $subject->getKey(),
                'old_values' => $old ?: null,
                'new_values' => $new ?: null,
                'ip_address' => request()->ip(),
                'user_agent' => mb_substr((string) request()->userAgent(), 0, 255),
            ]);
        } catch (\Throwable $e) {
            // Authorisation must still succeed if the audit table is read-only.
            Log::warning('Could not write ORCID audit record.', [
                'event' => $event,
                'subject' => $subject::class,
                'subject_id' => $subject->getKey(),
                'reason' => $e->getMessage(),
            ]);
        }
    }
}

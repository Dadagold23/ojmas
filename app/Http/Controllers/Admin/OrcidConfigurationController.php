<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArticleAuthor;
use App\Models\AuditLog;
use App\Models\JournalSetting;
use App\Models\OrcidCoauthorClaim;
use App\Models\OrcidConnection;
use App\Models\Submission;
use App\Models\User;
use App\Services\Orcid\OrcidAccountService;
use App\Services\Orcid\OrcidAudit;
use App\Services\Orcid\OrcidSettings;
use Illuminate\Http\Request;

/**
 * Administrator control of the ORCID integration.
 *
 * The OAuth client secret lives only in the environment; this screen reports
 * whether it is configured but never renders it. Releasing a wrongly linked
 * identity and linking a verified identity to a byline are both audited.
 */
class OrcidConfigurationController extends Controller
{
    public function __construct(
        private readonly OrcidSettings $settings,
        private readonly OrcidAccountService $accounts,
        private readonly OrcidAudit $audit,
    ) {
    }

    public function index()
    {
        $this->authorize('manageOrcidSettings', new Submission());

        $setting = JournalSetting::current();

        $connections = OrcidConnection::query()
            ->with('user')
            ->where('status', OrcidConnection::STATUS_CONNECTED)
            ->latest('authenticated_at')
            ->paginate(20, ['*'], 'connections_page');

        $unverifiedUsers = User::query()
            ->whereDoesntHave('orcidConnection', function ($query) {
                $query->where('status', OrcidConnection::STATUS_CONNECTED)
                    ->whereNotNull('orcid_id');
            })
            ->orderBy('name')
            ->paginate(20, ['id', 'name', 'email', 'orcid'], 'unverified_page');

        $pendingClaims = OrcidCoauthorClaim::query()
            ->with('articleAuthor.submission')
            ->whereNull('completed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->take(20)
            ->get();

        $stats = [
            'connected' => OrcidConnection::query()
                ->where('status', OrcidConnection::STATUS_CONNECTED)
                ->whereNotNull('orcid_id')
                ->count(),
            'disconnected' => OrcidConnection::query()
                ->where('status', OrcidConnection::STATUS_DISCONNECTED)
                ->count(),
            'accounts_total' => User::query()->count(),
            'accounts_without_verified' => User::query()
                ->whereDoesntHave('orcidConnection', function ($query) {
                    $query->where('status', OrcidConnection::STATUS_CONNECTED)
                        ->whereNotNull('orcid_id');
                })
                ->count(),
            'verified_bylines' => ArticleAuthor::query()
                ->whereNotNull('orcid_verified_at')
                ->count(),
            'pending_claims' => OrcidCoauthorClaim::query()
                ->whereNull('completed_at')
                ->where('expires_at', '>', now())
                ->count(),
            'failed_attempts_30d' => AuditLog::query()
                ->where('event', 'orcid.authentication.failed')
                ->where('created_at', '>=', now()->subDays(30))
                ->count(),
        ];

        return view('admin.orcid.index', [
            'setting' => $setting,
            'stats' => $stats,
            'connections' => $connections,
            'unverifiedUsers' => $unverifiedUsers,
            'pendingClaims' => $pendingClaims,
            'credentialsConfigured' => $this->settings->credentialsConfigured(),
            'activeHost' => $this->settings->sandbox() ? 'sandbox' : 'production',
            'callbackUrl' => $this->settings->callbackUrl(),
        ]);
    }

    public function update(Request $request)
    {
        $this->authorize('manageOrcidSettings', new Submission());

        $validated = $request->validate([
            'orcid_enabled' => ['nullable', 'boolean'],
            'orcid_sandbox' => ['nullable', 'boolean'],
            'orcid_required_for_new_authors' => ['nullable', 'boolean'],
        ]);

        $setting = JournalSetting::current();

        $old = [
            'orcid_enabled' => (bool) $setting->orcid_enabled,
            'orcid_sandbox' => (bool) $setting->orcid_sandbox,
            'orcid_required_for_new_authors' => (bool) $setting->orcid_required_for_new_authors,
        ];

        $setting->update([
            'orcid_enabled' => (bool) ($validated['orcid_enabled'] ?? false),
            'orcid_sandbox' => (bool) ($validated['orcid_sandbox'] ?? false),
            'orcid_required_for_new_authors' => (bool) ($validated['orcid_required_for_new_authors'] ?? false),
        ]);

        // The setting row is not the subject of a submission policy, so the
        // audit record is attributed to the administrator account itself.
        $this->audit->record($request->user(), 'orcid.settings.updated', $request->user(), $old, [
            'orcid_enabled' => (bool) $setting->orcid_enabled,
            'orcid_sandbox' => (bool) $setting->orcid_sandbox,
            'orcid_required_for_new_authors' => (bool) $setting->orcid_required_for_new_authors,
        ]);

        return back()->with('success', 'ORCID settings updated.');
    }

    /** Correct a wrongly linked identity on an OJMAS account. */
    public function release(Request $request, OrcidConnection $connection)
    {
        $this->authorize('manageOrcidAccounts', new Submission());

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->accounts->release(
            $connection,
            $request->user(),
            $validated['reason'] ?? 'administrator_release',
        );

        return back()->with('success', 'The ORCID iD has been released from that account. The journal record is unchanged.');
    }

    /**
     * Associate a verified ORCID with a byline entry.
     *
     * Only the identity fields are touched; title, DOI, dates and author order
     * remain exactly as published.
     */
    public function linkAuthor(Request $request, ArticleAuthor $author)
    {
        $this->authorize('manageOrcidAccounts', new Submission());

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = User::query()->findOrFail($validated['user_id']);

        if (! $user->hasVerifiedOrcid()) {
            return back()->with('error', 'That OJMAS account does not have a verified ORCID iD.');
        }

        $old = [
            'orcid' => $author->orcid,
            'verified' => $author->hasVerifiedOrcid(),
            'user_id' => $author->user_id,
        ];

        $author->forceFill([
            'orcid' => $user->orcid,
            'orcid_verified_at' => $user->orcidConnection?->authenticated_at ?? now(),
            'user_id' => $user->getKey(),
        ])->save();

        $this->audit->record($request->user(), 'orcid.admin.author_linked', $author, $old, [
            'orcid' => $author->orcid,
            'verified' => true,
            'user_id' => $author->user_id,
        ]);

        return back()->with('success', 'Verified ORCID linked to '.$author->full_name.'. Publication metadata is unchanged.');
    }

    /**
     * Remove a verification association without deleting the byline or its
     * typed identifier. A manual value stays manual.
     */
    public function unlinkAuthor(Request $request, ArticleAuthor $author)
    {
        $this->authorize('manageOrcidAccounts', new Submission());

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $old = [
            'orcid' => $author->orcid,
            'verified' => $author->hasVerifiedOrcid(),
            'user_id' => $author->user_id,
        ];

        $author->forceFill([
            'orcid_verified_at' => null,
            'user_id' => null,
        ])->save();

        $this->audit->record($request->user(), 'orcid.admin.author_unlinked', $author, $old, [
            'orcid' => $author->orcid,
            'verified' => false,
            'reason' => $validated['reason'] ?? 'administrator_unlink',
        ]);

        return back()->with('success', 'Verification removed from '.$author->full_name.'. The byline itself is unchanged.');
    }
}

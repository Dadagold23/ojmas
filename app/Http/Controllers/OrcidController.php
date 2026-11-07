<?php

namespace App\Http\Controllers;

use App\Models\OrcidCoauthorClaim;
use App\Models\User;
use App\Services\Orcid\ClaimInvalidException;
use App\Services\Orcid\DuplicateOrcidException;
use App\Services\Orcid\OrcidAccountService;
use App\Services\Orcid\OrcidAudit;
use App\Services\Orcid\OrcidClaimService;
use App\Services\Orcid\OrcidOAuthService;
use App\Services\Orcid\OrcidOauthException;
use App\Services\Orcid\OrcidSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * OAuth entry point and callback.
 *
 * The callback route is public because a co-author may not hold an OJMAS
 * account. Account linking is nonetheless bound to the authenticated user
 * recorded in the session when the flow started; a guest callback can only
 * complete a co-author claim.
 */
class OrcidController extends Controller
{
    private const SESSION_KEY = 'orcid.oauth';

    private const STATE_LIFETIME_MINUTES = 10;

    public function __construct(
        private readonly OrcidSettings $settings,
        private readonly OrcidOAuthService $oauth,
        private readonly OrcidAccountService $accounts,
        private readonly OrcidClaimService $claims,
        private readonly OrcidAudit $audit,
    ) {
    }

    public function start(Request $request): RedirectResponse
    {
        abort_unless($this->settings->enabled(), 404, 'ORCID sign-in is not enabled.');

        $returnTo = $this->safeReturnPath($request->input('return_to'))
            ?? $this->safeReturnPath(url()->previous())
            ?? route('profile.edit');

        $state = Str::random(48);

        $request->session()->put(self::SESSION_KEY, [
            'state' => $state,
            'intent' => 'user',
            'user_id' => $request->user()->getKey(),
            'return_to' => $returnTo,
            'expires_at' => now()->addMinutes(self::STATE_LIFETIME_MINUTES)->getTimestamp(),
        ]);

        return redirect()->away($this->oauth->authorizationUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $pending = $request->session()->pull(self::SESSION_KEY);
        $state = (string) $request->query('state', '');
        $returnTo = is_array($pending) ? ($pending['return_to'] ?? route('profile.edit')) : route('profile.edit');
        $intent = is_array($pending) ? ($pending['intent'] ?? 'user') : 'user';

        $accountSubject = is_array($pending) && isset($pending['user_id'])
            ? User::find($pending['user_id'])
            : $request->user();

        $claim = null;
        $claimToken = is_array($pending) ? ($pending['claim_token'] ?? null) : null;

        if ($intent === 'coauthor' && is_array($pending) && isset($pending['claim_id'])) {
            $claim = OrcidCoauthorClaim::find($pending['claim_id']);
        }

        $failureTarget = $intent === 'coauthor' && $claimToken
            ? route('orcid.coauthor.show', ['token' => $claimToken])
            : $returnTo;

        if (! is_array($pending)
            || ! isset($pending['state'], $pending['expires_at'])
            || ! hash_equals((string) $pending['state'], $state)
            || now()->getTimestamp() > (int) $pending['expires_at']) {
            return redirect($failureTarget)->with(
                'orcid_error',
                'The ORCID sign-in session expired or could not be verified. Please start again.',
            );
        }

        if ($request->query('error') !== null) {
            $this->auditFailure($accountSubject, ['reason' => 'authorisation_denied']);

            return redirect($failureTarget)->with(
                'orcid_error',
                $this->denialMessage((string) $request->query('error')),
            );
        }

        if ($intent === 'user') {
            if ($request->user() === null || (int) $pending['user_id'] !== (int) $request->user()->getKey()) {
                return redirect(route('login'))->with(
                    'orcid_error',
                    'Sign in to the OJMAS account you started the ORCID connection with, then try again.',
                );
            }
        }

        $code = trim((string) $request->query('code', ''));

        if ($code === '') {
            $this->auditFailure($accountSubject, ['reason' => 'missing_code']);

            return redirect($failureTarget)->with(
                'orcid_error',
                'ORCID did not return an authorisation code. Please try again.',
            );
        }

        try {
            $identity = $this->oauth->exchange($code);
        } catch (OrcidOauthException $e) {
            $this->auditFailure($accountSubject, ['reason' => $e->reason]);

            return redirect($failureTarget)->with('orcid_error', $e->getMessage());
        }

        if ($intent === 'coauthor') {
            return $this->completeCoauthorClaim($claim, $identity, $request->user(), $failureTarget);
        }

        try {
            $this->accounts->connect($request->user(), $identity);
        } catch (DuplicateOrcidException $e) {
            $this->audit->record($request->user(), 'orcid.authentication.failed', $request->user(), [
                'reason' => 'duplicate_orcid',
                'orcid' => $identity->orcid,
            ]);

            return redirect($failureTarget)->with('orcid_error', $e->getMessage());
        }

        return redirect($returnTo)->with(
            'success',
            'Your ORCID iD '.$identity->orcid.' is now verified on your OJMAS profile.',
        );
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $request->validate([
            'confirm' => ['required', 'accepted'],
        ], [
            'confirm.accepted' => 'Confirm that you want to disconnect your ORCID iD.',
        ]);

        $disconnected = $this->accounts->disconnect($request->user());

        return back()->with(
            $disconnected ? 'success' : 'info',
            $disconnected
                ? 'Your ORCID iD has been disconnected. Your manuscripts and publications are unchanged.'
                : 'No ORCID connection was found.',
        );
    }

    private function completeCoauthorClaim(
        ?OrcidCoauthorClaim $claim,
        \App\Services\Orcid\OrcidIdentity $identity,
        ?User $actor,
        string $failureTarget,
    ): RedirectResponse {
        if ($claim === null) {
            return redirect($failureTarget)->with(
                'orcid_error',
                'This co-author verification link is no longer valid.',
            );
        }

        try {
            $author = $this->claims->complete($claim, $identity, $actor);
        } catch (ClaimInvalidException|DuplicateOrcidException $e) {
            return redirect($failureTarget)->with('orcid_error', $e->getMessage());
        }

        return redirect()->route('orcid.coauthor.complete', ['name' => $author->full_name])
            ->with('verified_orcid', $identity->orcid);
    }

    public function coauthorComplete(Request $request): View
    {
        return view('orcid.coauthor-complete', [
            'name' => (string) $request->query('name', ''),
            'orcid' => $request->session()->get('verified_orcid'),
        ]);
    }

    /** @param array<string, mixed> $details */
    private function auditFailure(?User $subject, array $details): void
    {
        if ($subject === null) {
            return;
        }

        $this->audit->record($subject, 'orcid.authentication.failed', $subject, $details);
    }

    private function denialMessage(string $error): string
    {
        return match ($error) {
            'access_denied' => 'You cancelled the ORCID authorisation. No changes were made.',
            'invalid_scope' => 'The journal requested an ORCID permission that is not available.',
            'server_error', 'temporarily_unavailable' => 'ORCID is temporarily unavailable. Please try again shortly.',
            default => 'ORCID authorisation did not complete. Please try again.',
        };
    }

    /**
     * Only local paths are accepted, so the callback can never be used as an
     * open redirect.
     */
    private function safeReturnPath(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || ! str_starts_with($value, '/') || str_starts_with($value, '//')) {
            return null;
        }

        return $value;
    }
}

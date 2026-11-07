<?php

namespace App\Http\Controllers;

use App\Models\OrcidCoauthorClaim;
use App\Services\Orcid\OrcidOAuthService;
use App\Services\Orcid\OrcidSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Co-author invitation/claim flow.
 *
 * The link is possession-based and bound to one `article_authors` row. The
 * submitting author's session is never used to verify a co-author.
 */
class CoauthorOrcidController extends Controller
{
    private const SESSION_KEY = 'orcid.oauth';

    private const STATE_LIFETIME_MINUTES = 10;

    public function __construct(
        private readonly OrcidSettings $settings,
        private readonly OrcidOAuthService $oauth,
    ) {
    }

    public function show(Request $request, string $token): View|Response
    {
        $claim = OrcidCoauthorClaim::findValidByToken($token);

        if ($claim === null) {
            return response()->view('orcid.coauthor-invalid', [], 410);
        }

        $claim->loadMissing('articleAuthor.submission');

        $actorEmail = strtolower(trim((string) $request->user()?->email));
        $authorEmail = strtolower(trim((string) $claim->articleAuthor?->email));

        return view('orcid.coauthor-claim', [
            'claim' => $claim,
            'token' => $token,
            'enabled' => $this->settings->enabled(),
            'signedInAsWrongAccount' => $request->user() !== null && $actorEmail !== $authorEmail,
        ]);
    }

    public function start(Request $request, string $token): RedirectResponse
    {
        abort_unless($this->settings->enabled(), 404, 'ORCID sign-in is not enabled.');

        $claim = OrcidCoauthorClaim::findValidByToken($token);

        abort_if($claim === null, 410, 'This verification link has expired or has already been used.');

        if ($claim->articleAuthor?->hasVerifiedOrcid()) {
            return redirect()->route('orcid.coauthor.show', ['token' => $token])
                ->with('orcid_error', 'This co-author already has a verified ORCID iD.');
        }

        $state = Str::random(48);

        $request->session()->put(self::SESSION_KEY, [
            'state' => $state,
            'intent' => 'coauthor',
            'claim_id' => $claim->getKey(),
            'claim_token' => $token,
            'user_id' => $request->user()?->getKey(),
            'return_to' => route('orcid.coauthor.show', ['token' => $token], false),
            'expires_at' => now()->addMinutes(self::STATE_LIFETIME_MINUTES)->getTimestamp(),
        ]);

        return redirect()->away($this->oauth->authorizationUrl($state));
    }
}

<?php

namespace App\Services\Orcid;

use App\Models\ArticleAuthor;
use App\Models\OrcidCoauthorClaim;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Issues and completes co-author ORCID invitations.
 *
 * A claim is bound to one `article_authors` row, never to the submitting
 * author's session. A signed-in visitor may only complete a claim whose
 * e-mail matches their account; a signed-out visitor may complete it without
 * creating or touching any OJMAS account.
 */
class OrcidClaimService
{
    public function __construct(
        private readonly OrcidAccountService $accounts,
        private readonly OrcidAudit $audit,
    ) {
    }

    public function issue(ArticleAuthor $author, ?User $issuer): OrcidCoauthorClaim
    {
        if ($author->hasVerifiedOrcid()) {
            throw new ClaimInvalidException('This co-author already has a verified ORCID iD.');
        }

        // Only the newest link should work.
        $author->orcidClaims()
            ->whereNull('completed_at')
            ->update(['expires_at' => now()]);

        $claim = OrcidCoauthorClaim::issueFor($author, $issuer);

        $this->audit->record(
            $issuer,
            'orcid.coauthor.claim.issued',
            $author,
            [],
            [
                'claim_id' => $claim->getKey(),
                'email' => $author->email,
                'expires_at' => $claim->expires_at->toIso8601String(),
            ],
        );

        return $claim;
    }

    public function complete(OrcidCoauthorClaim $claim, OrcidIdentity $identity, ?User $actor): ArticleAuthor
    {
        return DB::transaction(function () use ($claim, $identity, $actor) {
            $locked = OrcidCoauthorClaim::query()->lockForUpdate()->find($claim->getKey());

            if ($locked === null || ! $locked->isUsable()) {
                throw new ClaimInvalidException(
                    'This verification link has expired or has already been used. Ask the corresponding author for a new link.',
                );
            }

            $author = ArticleAuthor::query()->lockForUpdate()->find($locked->article_author_id);

            if ($author === null) {
                throw new ClaimInvalidException('The co-author record no longer exists.');
            }

            if ($author->hasVerifiedOrcid()) {
                throw new ClaimInvalidException('This co-author already has a verified ORCID iD.');
            }

            if ($actor !== null && ! $this->emailsMatch($actor->email, $author->email)) {
                throw new ClaimInvalidException(sprintf(
                    'This invitation belongs to %s. Sign in with that co-author e-mail address, or open the link while signed out.',
                    $author->full_name,
                ));
            }

            $linkedUserId = $author->user_id;
            $oldOrcid = $author->orcid;

            if ($actor !== null) {
                // The co-author is also an OJMAS user: verify their profile at
                // the same time, under their own account, never the submitter's.
                $this->accounts->connect($actor, $identity);
                $linkedUserId = $actor->getKey();
            }

            $author->forceFill([
                'orcid' => $identity->orcid,
                'orcid_verified_at' => now(),
                'user_id' => $linkedUserId,
            ])->save();

            $locked->forceFill(['completed_at' => now()])->save();

            $this->audit->record(
                $actor,
                'orcid.coauthor.verified',
                $author,
                ['orcid' => $oldOrcid],
                [
                    'orcid' => $identity->orcid,
                    'verified_at' => now()->toIso8601String(),
                    'user_id' => $linkedUserId,
                ],
            );

            return $author->refresh();
        });
    }

    private function emailsMatch(?string $left, ?string $right): bool
    {
        $left = strtolower(trim((string) $left));
        $right = strtolower(trim((string) $right));

        return $left !== '' && $left === $right;
    }
}

<?php

namespace App\Services\Orcid;

use App\Models\OrcidConnection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Links and unlinks verified ORCID identities on OJMAS accounts.
 *
 * The manuscript and publication records are never touched: they keep their
 * stored bylines, and `users.orcid` is only the profile display value.
 */
class OrcidAccountService
{
    public function __construct(private readonly OrcidAudit $audit)
    {
    }

    /**
     * @throws DuplicateOrcidException
     */
    public function connect(User $user, OrcidIdentity $identity): OrcidConnection
    {
        try {
            return DB::transaction(function () use ($user, $identity) {
                $conflict = OrcidConnection::query()
                    ->where('orcid_id', $identity->orcid)
                    ->where('user_id', '!=', $user->getKey())
                    ->where('status', OrcidConnection::STATUS_CONNECTED)
                    ->lockForUpdate()
                    ->first();

                if ($conflict !== null) {
                    throw new DuplicateOrcidException();
                }

                $previousOrcid = $user->orcid;

                $connection = OrcidConnection::query()->updateOrCreate(
                    ['user_id' => $user->getKey()],
                    [
                        'orcid_id' => $identity->orcid,
                        'orcid_name' => $identity->name,
                        'access_token' => $identity->accessToken,
                        'refresh_token' => $identity->refreshToken,
                        'token_expires_at' => $identity->expiresAt,
                        'scope' => $identity->scope,
                        'status' => OrcidConnection::STATUS_CONNECTED,
                        'authenticated_at' => now(),
                        'revoked_at' => null,
                    ],
                );

                $user->forceFill(['orcid' => $identity->orcid])->save();

                $this->audit->record(
                    $user,
                    'orcid.connected',
                    $user,
                    ['orcid' => $previousOrcid],
                    [
                        'orcid' => $identity->orcid,
                        'connection_status' => OrcidConnection::STATUS_CONNECTED,
                        'authenticated_at' => now()->toIso8601String(),
                    ],
                );

                return $connection;
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // The unique index is the final defence against a race where two
            // accounts authenticate the same ORCID at the same moment.
            if ($this->isUniqueViolation($e)) {
                throw new DuplicateOrcidException();
            }

            throw $e;
        }
    }

    public function disconnect(User $user, string $reason = 'user_request'): bool
    {
        $connection = $user->orcidConnection()->first();

        if ($connection === null) {
            return false;
        }

        $this->releaseConnection($connection, $user, $user, 'orcid.disconnected', $reason);

        return true;
    }

    /**
     * Administrative correction for a wrongly linked association. The action
     * is audited against both the affected account and the administrator.
     */
    public function release(OrcidConnection $connection, User $administrator, string $reason = 'administrator_release'): void
    {
        $subject = $connection->user;

        if ($subject === null) {
            return;
        }

        $this->releaseConnection($connection, $subject, $administrator, 'orcid.admin.released', $reason);
    }

    private function releaseConnection(
        OrcidConnection $connection,
        User $subject,
        User $actor,
        string $event,
        string $reason,
    ): void {
        DB::transaction(function () use ($connection, $subject, $actor, $event, $reason) {
            $oldOrcid = $connection->orcid_id;

            $connection->forceFill([
                'orcid_id' => null,
                'orcid_name' => null,
                'access_token' => null,
                'refresh_token' => null,
                'token_expires_at' => null,
                'scope' => null,
                'status' => OrcidConnection::STATUS_DISCONNECTED,
                'revoked_at' => now(),
            ])->save();

            if ($oldOrcid !== null && $subject->orcid === $oldOrcid) {
                $subject->forceFill(['orcid' => null])->save();
            }

            $this->audit->record(
                $actor,
                $event,
                $subject,
                ['orcid' => $oldOrcid, 'connection_status' => OrcidConnection::STATUS_CONNECTED],
                [
                    'orcid' => null,
                    'connection_status' => OrcidConnection::STATUS_DISCONNECTED,
                    'reason' => $reason,
                    'released_by' => $actor->getKey(),
                ],
            );
        });
    }

    private function isUniqueViolation(\Illuminate\Database\QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'unique')
            || str_contains($message, 'duplicate')
            || str_contains($message, 'orcid_connections.orcid_id');
    }
}

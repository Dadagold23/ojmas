<?php

namespace App\Services\Orcid;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The server side of ORCID's OAuth 2.0 authorization-code flow.
 *
 * The client secret and authorization code never leave the server: the
 * browser only ever sees the ORCID authorisation URL and the opaque `state`.
 */
class OrcidOAuthService
{
    public function __construct(private readonly OrcidSettings $settings)
    {
    }

    public function authorizationUrl(string $state): string
    {
        $query = http_build_query([
            'client_id' => $this->settings->clientId(),
            'response_type' => 'code',
            'scope' => $this->settings->scope(),
            'redirect_uri' => $this->settings->callbackUrl(),
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);

        return $this->settings->authorizeUrl().'?'.$query;
    }

    /**
     * @throws OrcidOauthException
     */
    public function exchange(string $code): OrcidIdentity
    {
        if (! $this->settings->credentialsConfigured()) {
            throw OrcidOauthException::notConfigured();
        }

        if (trim($code) === '') {
            throw OrcidOauthException::exchangeFailed();
        }

        $headers = ['Accept' => 'application/json'];

        if ($this->settings->sandbox()) {
            // ORCID sandbox rejects some default user agents; identify the app.
            $headers['User-Agent'] = config('app.name', 'OJMAS').' ORCID client';
        }

        try {
            $response = Http::withHeaders($headers)
                ->asForm()
                ->timeout($this->settings->timeout())
                ->post($this->settings->tokenUrl(), [
                    'client_id' => $this->settings->clientId(),
                    'client_secret' => $this->settings->clientSecret(),
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $this->settings->callbackUrl(),
                ]);
        } catch (ConnectionException $e) {
            Log::warning('ORCID token exchange could not reach the service.', [
                'reason' => $e->getMessage(),
            ]);

            throw OrcidOauthException::unavailable();
        }

        if ($response->failed()) {
            // Never log the response body: it can echo credentials.
            Log::warning('ORCID token exchange was rejected.', [
                'status' => $response->status(),
            ]);

            throw OrcidOauthException::exchangeFailed();
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw OrcidOauthException::invalidResponse();
        }

        $orcid = OrcidId::normalize($payload['orcid'] ?? null);

        if ($orcid === null || ! OrcidId::isValid($orcid)) {
            throw OrcidOauthException::invalidResponse();
        }

        if (strtolower((string) ($payload['token_type'] ?? '')) !== 'bearer') {
            throw OrcidOauthException::invalidResponse();
        }

        $accessToken = trim((string) ($payload['access_token'] ?? ''));

        if ($accessToken === '') {
            throw OrcidOauthException::invalidResponse();
        }

        $scope = (string) ($payload['scope'] ?? '');

        if (! in_array($this->settings->scope(), preg_split('/\s+/', trim($scope)) ?: [], true)) {
            throw OrcidOauthException::invalidResponse();
        }

        $expiresAt = is_numeric($payload['expires_in'] ?? null)
            ? now()->addSeconds((int) $payload['expires_in'])
            : null;

        $name = trim((string) ($payload['name'] ?? ''));

        if ($name === '') {
            $name = $this->fetchDisplayName($orcid, $accessToken) ?? '';
        }

        return new OrcidIdentity(
            orcid: $orcid,
            accessToken: $accessToken,
            refreshToken: filled($payload['refresh_token'] ?? null) ? (string) $payload['refresh_token'] : null,
            expiresAt: $expiresAt,
            scope: $scope,
            name: $name !== '' ? Str::limit($name, 190, '') : null,
        );
    }

    /**
     * Best-effort profile name. A failure here must never fail a valid sign-in.
     */
    private function fetchDisplayName(string $orcid, string $accessToken): ?string
    {
        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout($this->settings->timeout())
                ->get($this->settings->apiUrl().'/'.$orcid.'/person');
        } catch (\Throwable) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $name = $response->json('name');

        if (! is_array($name)) {
            return null;
        }

        $given = $name['given-names']['value'] ?? null;
        $family = $name['family-name']['value'] ?? null;
        $display = trim((string) $given.' '.(string) $family);

        return $display !== '' ? $display : null;
    }
}

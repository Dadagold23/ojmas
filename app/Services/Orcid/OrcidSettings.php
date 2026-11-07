<?php

namespace App\Services\Orcid;

use App\Models\JournalSetting;

/**
 * Resolves ORCID configuration from the environment plus the administrator's
 * on/off switches. Secrets stay in the environment and are never returned to
 * a view or an API response.
 */
class OrcidSettings
{
    public function clientId(): ?string
    {
        $value = trim((string) config('services.orcid.client_id'));

        return $value !== '' ? $value : null;
    }

    public function clientSecret(): ?string
    {
        $value = trim((string) config('services.orcid.client_secret'));

        return $value !== '' ? $value : null;
    }

    public function credentialsConfigured(): bool
    {
        return $this->clientId() !== null && $this->clientSecret() !== null;
    }

    public function enabled(): bool
    {
        if (! $this->credentialsConfigured()) {
            return false;
        }

        $setting = $this->setting();

        return $setting !== null
            ? (bool) $setting->orcid_enabled
            : (bool) config('services.orcid.enabled', false);
    }

    public function sandbox(): bool
    {
        $setting = $this->setting();

        return $setting !== null
            ? (bool) $setting->orcid_sandbox
            : (bool) config('services.orcid.sandbox', false);
    }

    public function requiredForNewAuthors(): bool
    {
        // A requirement only applies while sign-in is actually available;
        // otherwise authors would be blocked with no way to verify.
        return $this->enabled()
            && (bool) ($this->setting()?->orcid_required_for_new_authors ?? false);
    }

    public function callbackUrl(): string
    {
        $configured = trim((string) config('services.orcid.redirect'));

        return $configured !== '' ? $configured : route('orcid.callback');
    }

    public function authorizeUrl(): string
    {
        return $this->sandbox()
            ? config('services.orcid.sandbox_authorize_url')
            : config('services.orcid.authorize_url');
    }

    public function tokenUrl(): string
    {
        return $this->sandbox()
            ? config('services.orcid.sandbox_token_url')
            : config('services.orcid.token_url');
    }

    public function apiUrl(): string
    {
        return rtrim($this->sandbox()
            ? config('services.orcid.sandbox_api_url')
            : config('services.orcid.api_url'), '/');
    }

    public function profileUrl(string $orcid): string
    {
        $base = $this->sandbox()
            ? config('services.orcid.sandbox_profile_url')
            : config('services.orcid.profile_url');

        return rtrim($base, '/').'/'.$orcid;
    }

    public function timeout(): int
    {
        return max(5, (int) config('services.orcid.timeout', 15));
    }

    public function scope(): string
    {
        return (string) config('services.orcid.scope', '/authenticate');
    }

    private function setting(): ?JournalSetting
    {
        return JournalSetting::query()->orderBy('id')->first();
    }
}

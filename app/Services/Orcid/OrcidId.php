<?php

namespace App\Services\Orcid;

/**
 * ORCID iD parsing and checksum validation (ISO 7064 MOD 11-2).
 *
 * A syntactically plausible 16-digit string is not necessarily a real ORCID
 * iD; the final character is a checksum. Manual entry is checked for
 * structure only, so historical values remain storable; an identifier is
 * never treated as verified without a successful OAuth exchange.
 */
final class OrcidId
{
    public static function normalize(?string $value): ?string
    {
        $value = strtoupper(preg_replace('/[\s\-]+/', '', trim((string) $value)));

        if ($value === '' || $value === 'X') {
            return null;
        }

        if (preg_match('/^\d{15}[0-9X]$/', $value) !== 1) {
            return null;
        }

        return substr($value, 0, 4).'-'.substr($value, 4, 4).'-'.substr($value, 8, 4).'-'.substr($value, 12);
    }

    /**
     * Structurally an ORCID iD (16 digits with the final X/check position).
     *
     * Manual entry uses this check so a legacy or test identifier that is
     * well-formed but not checksum-valid can still be stored for display.
     */
    public static function isWellFormed(?string $value): bool
    {
        return self::normalize($value) !== null;
    }

    public static function isValid(?string $value): bool
    {
        $normalized = self::normalize($value);

        if ($normalized === null) {
            return false;
        }

        $digits = str_replace('-', '', $normalized);
        $total = 0;

        for ($i = 0; $i < 15; $i++) {
            $total = ($total + (int) $digits[$i]) * 2;
        }

        $remainder = $total % 11;
        $result = (12 - $remainder) % 11;
        $check = $result === 10 ? 'X' : (string) $result;

        return $check === $digits[15];
    }

    public static function profileUrl(string $orcid): string
    {
        return rtrim(config('services.orcid.profile_url', 'https://orcid.org'), '/').'/'.$orcid;
    }
}

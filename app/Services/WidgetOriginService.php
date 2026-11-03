<?php

namespace App\Services;

use App\Models\Gym;

/**
 * Decides which browser origins may call the widget API of a gym.
 *
 * The list lives in widget_settings.security.allowed_origins and holds either
 * normalized origins ("https://www.example.com") or the wildcard "*".
 */
class WidgetOriginService
{
    public const ALLOW_ALL = '*';

    public const MAX_ORIGINS = 20;

    /**
     * @return array<int, string>
     */
    public function allowedOrigins(Gym $gym): array
    {
        $origins = $gym->widget_settings['security']['allowed_origins'] ?? null;

        // Gyms that never configured the list keep the previous open behaviour,
        // otherwise every embedded widget would break on deploy.
        if (! is_array($origins)) {
            return [self::ALLOW_ALL];
        }

        return array_values(array_filter($origins, 'is_string'));
    }

    public function isAllowed(Gym $gym, ?string $origin): bool
    {
        // Non-browser clients send no Origin header; CORS does not apply to them
        // and the API key remains the only gate.
        if ($origin === null || $origin === '') {
            return true;
        }

        $allowed = $this->allowedOrigins($gym);

        if (in_array(self::ALLOW_ALL, $allowed, true)) {
            return true;
        }

        // Opaque origins ("null") and anything without a scheme never match.
        if (! preg_match('#^https?://#i', $origin)) {
            return false;
        }

        $origin = $this->normalize($origin);

        if ($origin === null) {
            return false;
        }

        // The widget preview runs on the application's own domain.
        if ($origin === $this->normalize((string) config('app.url'))) {
            return true;
        }

        return in_array($origin, array_map(fn (string $entry) => $this->normalize($entry), $allowed), true);
    }

    /**
     * Normalizes user input like "www.example.com/kontakt" to "https://www.example.com".
     * Returns null when the value cannot be turned into a valid http(s) origin.
     */
    public function normalize(string $value): ?string
    {
        $value = trim($value);

        if ($value === self::ALLOW_ALL) {
            return self::ALLOW_ALL;
        }

        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $value)) {
            $value = 'https://'.$value;
        }

        $parts = parse_url($value);

        if ($parts === false || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
            && filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) === false) {
            return null;
        }

        $origin = $scheme.'://'.$host;
        $port = $parts['port'] ?? null;

        // Browsers omit default ports in the Origin header.
        if ($port !== null && ! ($scheme === 'https' && $port === 443) && ! ($scheme === 'http' && $port === 80)) {
            $origin .= ':'.$port;
        }

        return $origin;
    }

    /**
     * Normalizes and de-duplicates a list of origins for storage. A wildcard
     * makes every other entry redundant.
     *
     * @param  array<int, string>  $origins
     * @return array<int, string>
     */
    public function normalizeList(array $origins): array
    {
        $normalized = array_values(array_unique(array_filter(
            array_map(fn (string $origin) => $this->normalize($origin), $origins)
        )));

        return in_array(self::ALLOW_ALL, $normalized, true) ? [self::ALLOW_ALL] : $normalized;
    }
}

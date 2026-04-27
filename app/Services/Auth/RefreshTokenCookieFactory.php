<?php

namespace App\Services\Auth;

use Symfony\Component\HttpFoundation\Cookie;

class RefreshTokenCookieFactory
{
    public function make(string $refreshToken): Cookie
    {
        return cookie(
            $this->name(),
            $refreshToken,
            $this->minutes(),
            $this->path(),
            $this->domain(),
            $this->secure(),
            $this->httpOnly(),
            false,
            $this->sameSite(),
        );
    }

    public function forget(): Cookie
    {
        return cookie(
            $this->name(),
            '',
            -2628000,
            $this->path(),
            $this->domain(),
            $this->secure(),
            $this->httpOnly(),
            false,
            $this->sameSite(),
        );
    }

    public function name(): string
    {
        return (string) config('auth.refresh_token.cookie.name', 'refresh_token');
    }

    private function minutes(): int
    {
        $seconds = (int) config('auth.refresh_token.ttl_seconds', 86400);

        return max(1, (int) ceil($seconds / 60));
    }

    private function path(): string
    {
        return (string) config('auth.refresh_token.cookie.path', '/api/v1/auth');
    }

    private function domain(): ?string
    {
        $domain = config('auth.refresh_token.cookie.domain');

        if (is_null($domain)) {
            return null;
        }

        $value = trim((string) $domain);

        if ($value === '' || strtolower($value) === 'null') {
            return null;
        }

        return $value;
    }

    private function secure(): bool
    {
        return (bool) config('auth.refresh_token.cookie.secure', true);
    }

    private function httpOnly(): bool
    {
        return (bool) config('auth.refresh_token.cookie.http_only', true);
    }

    private function sameSite(): string
    {
        $sameSite = strtolower((string) config('auth.refresh_token.cookie.same_site', 'lax'));

        return in_array($sameSite, ['lax', 'strict', 'none'], true) ? $sameSite : 'lax';
    }
}

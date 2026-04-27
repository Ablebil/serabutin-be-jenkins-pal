<?php

namespace App\Services\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RefreshTokenService
{
    public function issue(User $user): array
    {
        $plainToken = $this->generateToken();
        $expiresAt = Carbon::now()->addSeconds($this->ttlSeconds());

        $session = RefreshToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => $this->hashToken($plainToken),
            'expires_at' => $expiresAt,
            'created_at' => Carbon::now(),
        ]);

        return [
            'plain_text_token' => $plainToken,
            'expires_at' => $session->expires_at,
            'session' => $session,
        ];
    }

    public function findValid(string $plainToken): ?RefreshToken
    {
        $session = RefreshToken::query()
            ->with('user')
            ->where('token_hash', $this->hashToken($plainToken))
            ->first();

        if (is_null($session)) {
            return null;
        }

        if ($session->expires_at->isPast()) {
            $session->delete();
            return null;
        }

        return $session;
    }

    public function rotate(string $plainToken): ?array
    {
        $currentSession = $this->findValid($plainToken);

        if (is_null($currentSession)) {
            return null;
        }

        $user = $currentSession->user;

        if (is_null($user)) {
            $currentSession->delete();
            return null;
        }

        if (!$user->is_active) {
            $currentSession->delete();
            return null;
        }

        return DB::transaction(function () use ($currentSession, $user): array {
            $currentSession->delete();

            $issued = $this->issue($user);
            $issued['user'] = $user;

            return $issued;
        });
    }

    public function revoke(string $plainToken): bool
    {
        $deleted = RefreshToken::query()
            ->where('token_hash', $this->hashToken($plainToken))
            ->delete();

        return $deleted > 0;
    }

    public function revokeAllByUserId(string $userId): int
    {
        return RefreshToken::query()
            ->where('user_id', $userId)
            ->delete();
    }

    public function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    private function ttlSeconds(): int
    {
        $ttl = (int) config('auth.refresh_token.ttl_seconds', 86400);

        return $ttl > 0 ? $ttl : 86400;
    }

    private function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }
}

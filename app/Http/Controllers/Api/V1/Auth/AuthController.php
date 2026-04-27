<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RefreshTokenRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Requests\Api\V1\Auth\VerifyEmailRequest;
use App\Mail\VerifyEmailMail;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Auth\EmailVerificationTokenService;
use App\Services\Auth\JwtService;
use App\Services\Auth\RefreshTokenCookieFactory;
use App\Services\Auth\RefreshTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, EmailVerificationTokenService $tokenService): JsonResponse
    {
        $payload = $request->validated();

        $exists = User::query()
            ->where('email', $payload['email'])
            ->exists();

        if ($exists) {
            return $this->error(__('auth.register.email_exists'), 409);
        }

        $user = DB::transaction(function () use ($payload, $tokenService): User {
            $user = User::query()->create([
                'email' => $payload['email'],
                'password_hash' => $payload['password'],
                'full_name' => $payload['full_name'],
                'role' => $payload['role'],
                'is_verified' => false,
                'is_active' => true,
            ]);

            UserProfile::query()->create([
                'user_id' => $user->id,
                'avatar_url' => null,
            ]);

            $token = $tokenService->issue($user);
            $verificationUrl = $tokenService->buildVerificationUrl($token);

            Mail::to($user->email)->send(new VerifyEmailMail($user->full_name, $verificationUrl));

            return $user;
        });

        return $this->success(__('auth.register.success'), $user->fresh(), 201);
    }

    public function verify(VerifyEmailRequest $request, EmailVerificationTokenService $tokenService): JsonResponse
    {
        $payload = $request->validated();
        $userId = $tokenService->consume($payload['token']);

        if (is_null($userId)) {
            return $this->error(__('auth.verify.invalid_or_expired'), 400);
        }

        $user = User::query()->find($userId);

        if (is_null($user)) {
            return $this->error(__('auth.verify.invalid_or_expired'), 400);
        }

        if (!$user->is_verified) {
            $user->forceFill(['is_verified' => true])->save();
        }

        return $this->success(__('auth.verify.success'));
    }

    public function login(
        LoginRequest $request,
        JwtService $jwtService,
        RefreshTokenService $refreshTokenService,
        RefreshTokenCookieFactory $cookieFactory,
    ): JsonResponse {
        $payload = $request->validated();

        $user = User::query()
            ->where('email', $payload['email'])
            ->first();

        if (is_null($user) || !Hash::check($payload['password'], $user->password_hash)) {
            return $this->error(__('auth.login.invalid_credentials'), 401);
        }

        if (!$user->is_verified) {
            return $this->error(__('auth.login.email_not_verified'), 403);
        }

        if (!$user->is_active) {
            return $this->error(__('auth.login.account_inactive'), 403);
        }

        $accessToken = $jwtService->issueAccessToken($user);
        $refreshToken = $refreshTokenService->issue($user);

        $data = [
            'access_token' => $accessToken['access_token'],
            'token_type' => $accessToken['token_type'],
            'expires_in' => $accessToken['expires_in'],
            'user' => $user->fresh(),
        ];

        return $this->success(__('auth.login.success'), $data)
            ->cookie($cookieFactory->make($refreshToken['plain_text_token']));
    }

    public function refresh(
        RefreshTokenRequest $request,
        JwtService $jwtService,
        RefreshTokenService $refreshTokenService,
        RefreshTokenCookieFactory $cookieFactory,
    ): JsonResponse {
        $payload = $request->validated();

        $rotated = $refreshTokenService->rotate($payload['refresh_token']);

        if (is_null($rotated)) {
            return $this->error(__('auth.refresh.invalid_or_expired'), 401);
        }

        $accessToken = $jwtService->issueAccessToken($rotated['user']);

        $data = [
            'access_token' => $accessToken['access_token'],
            'token_type' => $accessToken['token_type'],
            'expires_in' => $accessToken['expires_in'],
        ];

        return $this->success(__('auth.refresh.success'), $data)
            ->cookie($cookieFactory->make($rotated['plain_text_token']));
    }

    public function logout(
        RefreshTokenRequest $request,
        RefreshTokenService $refreshTokenService,
        RefreshTokenCookieFactory $cookieFactory,
    ): JsonResponse {
        $payload = $request->validated();

        $refreshTokenService->revoke($payload['refresh_token']);

        return $this->success(__('auth.logout.success'))
            ->cookie($cookieFactory->forget());
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ChangePasswordRequest;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(protected AuditLogger $auditLogger) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $login = $request->string('login')->toString();
        $password = $request->string('password')->toString();

        $user = User::query()
            ->where(function ($query) use ($login) {
                $query->where('email', $login)
                    ->orWhere('username', $login);
            })
            ->first();

        if (! $user) {
            $this->auditLogger->log('auth.login_failed', null, null, [
                'login' => $login,
                'reason' => 'user_not_found',
            ]);

            return ApiResponse::error('Invalid credentials.', [], 401);
        }

        if ($user->isLocked()) {
            $this->auditLogger->log('auth.login_failed', $user, null, [
                'reason' => 'account_locked',
            ]);

            return ApiResponse::error(
                'Account is temporarily locked. Please try again later.',
                [],
                423
            );
        }

        if (! $user->isActive()) {
            $this->auditLogger->log('auth.login_failed', $user, null, [
                'reason' => 'account_disabled',
            ]);

            return ApiResponse::error('Account is disabled.', [], 403);
        }

        if (! Hash::check($password, $user->password)) {
            $user->increment('failed_login_attempts');
            $user->refresh();

            if ($user->failed_login_attempts >= 5) {
                $user->forceFill([
                    'locked_until' => now()->addMinutes(15),
                    'failed_login_attempts' => 0,
                ])->save();

                $this->auditLogger->log('auth.login_failed', $user, null, [
                    'reason' => 'locked_after_failures',
                ]);

                return ApiResponse::error(
                    'Account locked due to too many failed attempts. Try again in 15 minutes.',
                    [],
                    423
                );
            }

            $this->auditLogger->log('auth.login_failed', $user, null, [
                'reason' => 'invalid_password',
                'attempts' => $user->failed_login_attempts,
            ]);

            return ApiResponse::error('Invalid credentials.', [], 401);
        }

        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $tokenName = $request->string('device_name')->toString() ?: 'api-token';
        $token = $user->createToken($tokenName)->plainTextToken;

        $this->auditLogger->log('auth.login_success', $user);

        $user->load(['roles', 'permissions', 'branches']);

        return ApiResponse::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
            'force_password_change' => (bool) $user->force_password_change,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->auditLogger->log('auth.logout', $user);

        $token = $user->currentAccessToken();
        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $token->delete();
        } elseif (method_exists($token, 'delete')) {
            $token->delete();
        }

        return ApiResponse::success(['message' => 'Logged out.'], null, 200);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['roles', 'permissions', 'branches']);

        return ApiResponse::success($this->userPayload($user));
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->string('current_password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->forceFill([
            'password' => $request->string('password')->toString(),
            'force_password_change' => false,
        ])->save();

        // Invalidate existing sessions after a password change.
        $user->tokens()->delete();

        $this->auditLogger->log('auth.password_changed', $user);

        return ApiResponse::success([
            'message' => 'Password changed successfully. Please sign in again.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'phone' => $user->phone,
            'locale' => $user->locale,
            'status' => $user->status,
            'force_password_change' => (bool) $user->force_password_change,
            'last_login_at' => $user->last_login_at,
            'roles' => $user->getRoleNames()->values(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            'branches' => $user->branches->map(fn ($branch) => [
                'id' => $branch->id,
                'code' => $branch->code,
                'name_en' => $branch->name_en,
                'name_fa' => $branch->name_fa,
            ])->values(),
        ];
    }
}

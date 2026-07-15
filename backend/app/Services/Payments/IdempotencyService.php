<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\PaymentIdempotencyKey;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class IdempotencyService
{
    public function hashRequest(array $payload): string
    {
        $normalized = $payload;
        ksort($normalized);

        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
    }

    public function find(string $key, User $user): ?PaymentIdempotencyKey
    {
        return PaymentIdempotencyKey::query()
            ->where('key', $key)
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * @return JsonResponse|null Cached response when key matches prior successful request.
     */
    public function recallOrConflict(string $key, User $user, string $requestHash): ?JsonResponse
    {
        $existing = $this->find($key, $user);

        if (! $existing) {
            return null;
        }

        if ($existing->request_hash !== $requestHash) {
            throw new RuntimeException('Idempotency key reused with a different payload.');
        }

        if ($existing->response_json === null) {
            return null;
        }

        $cached = $existing->response_json;
        $status = (int) ($cached['http_status'] ?? 200);
        $body = $cached['body'] ?? ['success' => true, 'data' => null];

        return response()->json($body, $status);
    }

    public function begin(string $key, User $user, string $requestHash): PaymentIdempotencyKey
    {
        return PaymentIdempotencyKey::query()->create([
            'key' => $key,
            'user_id' => $user->id,
            'request_hash' => $requestHash,
            'expires_at' => now()->addHours((int) config('payments.idempotency_ttl_hours', 48)),
        ]);
    }

    public function complete(PaymentIdempotencyKey $record, ?Payment $payment, array $body, int $httpStatus = 200): void
    {
        $record->update([
            'payment_id' => $payment?->id,
            'response_json' => [
                'http_status' => $httpStatus,
                'body' => $body,
            ],
        ]);
    }

    /**
     * Acquire advisory-style lock via DB transaction + unique key insert.
     */
    public function withKey(string $key, User $user, array $payload, callable $callback): mixed
    {
        $hash = $this->hashRequest($payload);

        return DB::transaction(function () use ($key, $user, $hash, $callback) {
            $existing = PaymentIdempotencyKey::query()
                ->where('key', $key)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->expires_at && $existing->expires_at->isPast()) {
                    $existing->delete();
                    $existing = null;
                } elseif ($existing->request_hash !== $hash) {
                    throw new RuntimeException('Idempotency key reused with a different payload.');
                } elseif ($existing->response_json !== null) {
                    return [
                        'cached' => true,
                        'response' => $existing->response_json,
                        'record' => $existing,
                    ];
                }
            }

            if (! $existing) {
                $existing = $this->begin($key, $user, $hash);
            }

            $result = $callback($existing);

            return [
                'cached' => false,
                'result' => $result,
                'record' => $existing,
            ];
        });
    }
}

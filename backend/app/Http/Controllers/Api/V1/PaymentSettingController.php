<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentSettingController extends Controller
{
    public const KEYS = [
        'payment_draft_expiry_hours',
        'payment_default_currency',
        'payment_receipt_language',
        'payment_wallet_enabled',
        'zoho_payment_dry_run',
    ];

    public function __construct(protected AuditLogger $audit) {}

    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('payment_settings.manage') || $request->user()?->can('payments.view'), 403);

        return ApiResponse::success($this->values());
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('payment_settings.manage'), 403);

        $data = $request->validate([
            'payment_draft_expiry_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
            'payment_default_currency' => ['nullable', 'string', 'size:3'],
            'payment_receipt_language' => ['nullable', 'string', 'max:10'],
            'payment_wallet_enabled' => ['nullable', 'boolean'],
            'zoho_payment_dry_run' => ['nullable', 'boolean'],
        ]);

        $old = $this->values();

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }
            SystemSetting::setValue($key, is_bool($value) ? ($value ? '1' : '0') : (string) $value);
        }

        $new = $this->values();
        $this->audit->log('payment_settings.updated', null, $old, $new);

        return ApiResponse::success($new);
    }

    /**
     * @return array<string, mixed>
     */
    protected function values(): array
    {
        $stored = SystemSetting::query()->whereIn('key', self::KEYS)->get()->keyBy('key');

        return [
            'payment_draft_expiry_hours' => (int) ($stored->get('payment_draft_expiry_hours')?->decoded_value
                ?? config('payments.draft_expiry_hours', 24)),
            'payment_default_currency' => (string) ($stored->get('payment_default_currency')?->decoded_value
                ?? config('payments.default_currency', 'AFN')),
            'payment_receipt_language' => (string) ($stored->get('payment_receipt_language')?->decoded_value
                ?? config('payments.receipt.default_language', 'en')),
            'payment_wallet_enabled' => filter_var(
                $stored->get('payment_wallet_enabled')?->decoded_value ?? config('payments.wallet.enabled', true),
                FILTER_VALIDATE_BOOLEAN
            ),
            'zoho_payment_dry_run' => filter_var(
                $stored->get('zoho_payment_dry_run')?->decoded_value ?? config('zoho.payments.dry_run', false),
                FILTER_VALIDATE_BOOLEAN
            ),
            'config' => [
                'draft_expiry_hours' => config('payments.draft_expiry_hours'),
                'max_amount' => config('payments.max_amount'),
                'gps' => config('payments.gps'),
            ],
        ];
    }
}

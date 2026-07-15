<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Setting\UpdateSettingRequest;
use App\Models\SystemSetting;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public const COMPANY_KEYS = [
        'company_name',
        'currency',
        'timezone',
        'setup_completed',
    ];

    public function __construct(protected AuditLogger $auditLogger) {}

    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('settings.manage'), 403);

        return ApiResponse::success($this->companySettings());
    }

    public function update(UpdateSettingRequest $request): JsonResponse
    {
        $old = $this->companySettings();

        foreach ($request->validated() as $key => $value) {
            SystemSetting::setValue($key, (string) $value);
        }

        $new = $this->companySettings();

        $this->auditLogger->log('settings.updated', null, $old, $new);

        return ApiResponse::success($new);
    }

    /**
     * @return array<string, mixed>
     */
    protected function companySettings(): array
    {
        $settings = SystemSetting::query()
            ->whereIn('key', self::COMPANY_KEYS)
            ->get()
            ->keyBy('key');

        $result = [];
        foreach (self::COMPANY_KEYS as $key) {
            $result[$key] = $settings->get($key)?->decoded_value;
        }

        return $result;
    }
}

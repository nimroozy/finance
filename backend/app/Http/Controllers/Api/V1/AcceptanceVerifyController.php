<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Crm\Lead;
use App\Models\Customer;
use App\Models\Services\Service;
use App\Models\User;
use App\Support\Acceptance\AcceptanceDbAssertions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Acceptance-only DB verification helpers. Registered behind EnsureAcceptanceEnvironment.
 */
class AcceptanceVerifyController extends Controller
{
    public function __construct(
        private readonly AcceptanceDbAssertions $assertions,
    ) {}

    public function ping(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'env' => app()->environment(),
                'acceptance' => true,
                'zoho_mode' => config('services.zoho.write_mode', env('ZOHO_WRITE_MODE', 'test_adapter')),
                'whatsapp_send_enabled' => (bool) env('WHATSAPP_SEND_ENABLED', false),
                'radius_enabled' => (bool) env('RADIUS_ENABLED', false),
            ],
        ]);
    }

    public function assertRecord(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity' => ['required', 'string', 'in:'.implode(',', AcceptanceDbAssertions::ENTITIES)],
            'key' => ['required', 'string'],
            'value' => ['required'],
            'expect' => ['sometimes', 'array'],
        ]);

        $result = $this->assertions->assertEntity(
            $validated['entity'],
            (string) $validated['key'],
            $validated['value'],
            $validated['expect'] ?? [],
        );

        return response()->json([
            'success' => $result['ok'],
            'data' => $result,
        ], $result['ok'] ? 200 : 422);
    }

    public function stockReconciliation(): JsonResponse
    {
        $result = $this->assertions->stockReconciliation();

        return response()->json([
            'success' => $result['ok'],
            'data' => $result,
        ], $result['ok'] ? 200 : 422);
    }

    public function fixtureSummary(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'customers' => Customer::query()->withoutGlobalScopes()->where('zoho_contact_id', 'like', 'ACCEPTANCE%')->count(),
                'leads' => Lead::query()->withoutGlobalScopes()->where('lead_number', 'like', 'ACC-%')->count(),
                'services' => Service::query()->withoutGlobalScopes()->where('service_number', 'like', 'ACC-%')->count(),
                'users' => User::query()->where('username', 'like', 'ACCEPTANCE-%')->count(),
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Equipment;
use App\Models\Inventory\Reservation;
use App\Models\Tickets\Installation;
use App\Services\Inventory\InstallationEquipmentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class InstallationEquipmentController extends Controller
{
    public function __construct(private InstallationEquipmentService $service) {}

    public function reserve(Request $request, int $installationId): JsonResponse
    {
        $installation = Installation::query()->findOrFail($installationId);
        $this->authorize('update', $installation);
        $data = $request->validate([
            'location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:inventory_products,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.equipment_id' => ['nullable', 'integer', 'exists:inventory_equipment,id'],
        ]);
        try {
            $rsv = $this->service->createReservationFromInstallation(
                $installation,
                (int) $data['location_id'],
                $data['items'],
                $request->user()
            );

            return ApiResponse::success($rsv, null, 201);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }
    }

    public function fulfill(Request $request, int $reservationId): JsonResponse
    {
        $rsv = Reservation::with('items')->findOrFail($reservationId);
        $this->authorize('update', $rsv);
        try {
            return ApiResponse::success($this->service->fulfillForInstallation($rsv, $request->user()));
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }
    }

    public function install(Request $request): JsonResponse
    {
        $data = $request->validate([
            'equipment_id' => ['required', 'integer', 'exists:inventory_equipment,id'],
            'installation_id' => ['required', 'integer', 'exists:installations,id'],
            'service_ref' => ['nullable', 'string', 'max:255'],
        ]);
        $equipment = Equipment::query()->findOrFail($data['equipment_id']);
        $installation = Installation::query()->findOrFail($data['installation_id']);
        $this->authorize('update', $installation);
        try {
            return ApiResponse::success(
                $this->service->installAtCustomer($equipment, $installation, $request->user(), $data['service_ref'] ?? null),
                null,
                201
            );
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }
    }

    public function returnUnused(Request $request, int $equipmentId): JsonResponse
    {
        $equipment = Equipment::query()->findOrFail($equipmentId);
        $this->authorize('update', $equipment);
        $data = $request->validate([
            'to_location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
        ]);
        try {
            return ApiResponse::success($this->service->returnUnused($equipment, (int) $data['to_location_id'], $request->user()));
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }
    }
}
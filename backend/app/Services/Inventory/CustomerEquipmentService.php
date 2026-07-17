<?php

namespace App\Services\Inventory;

use App\Models\Inventory\CustomerEquipment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class CustomerEquipmentService
{
    public function __construct(private AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, ?User $actor = null): CustomerEquipment
    {
        return DB::transaction(function () use ($data) {
            $row = CustomerEquipment::query()->create([
                'customer_id' => $data['customer_id'],
                'branch_id' => $data['branch_id'],
                'service_ref' => $data['service_ref'] ?? null,
                'product_id' => $data['product_id'],
                'equipment_id' => $data['equipment_id'] ?? null,
                'ownership_type' => $data['ownership_type'] ?? 'company_owned',
                'sale_price' => $data['sale_price'] ?? null,
                'install_fee' => $data['install_fee'] ?? null,
                'installed_at' => $data['installed_at'] ?? now()->toDateString(),
                'installation_id' => $data['installation_id'] ?? null,
                'status' => $data['status'] ?? 'installed',
            ]);
            $this->audit->log('inventory.customer_equipment.created', $row, null, $row->toArray(), (int) $row->branch_id);

            return $row;
        });
    }
}

<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Supplier;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupplierService
{
    public function __construct(private AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, ?User $actor = null): Supplier
    {
        return DB::transaction(function () use ($data) {
            $supplier = Supplier::query()->create([
                'code' => $data['code'] ?? Str::upper(Str::random(8)),
                'name' => $data['name'],
                'contact_name' => $data['contact_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'whatsapp' => $data['whatsapp'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'payment_terms' => $data['payment_terms'] ?? null,
                'lead_time_days' => $data['lead_time_days'] ?? null,
                'rating' => $data['rating'] ?? null,
                'zoho_sync_status' => 'pending',
                'zoho_idempotency_key' => (string) Str::uuid(),
                'is_active' => $data['is_active'] ?? true,
            ]);
            $this->audit->log('inventory.supplier.created', $supplier, null, $supplier->toArray());

            return $supplier;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Supplier $supplier, array $data, ?User $actor = null): Supplier
    {
        return DB::transaction(function () use ($supplier, $data) {
            $fields = [
                'name', 'contact_name', 'phone', 'whatsapp', 'email', 'address',
                'payment_terms', 'lead_time_days', 'rating', 'is_active',
            ];
            $old = $supplier->only($fields);
            $supplier->fill(collect($data)->only($fields)->all());
            $supplier->save();
            $this->audit->log('inventory.supplier.updated', $supplier, $old, $supplier->only($fields));

            return $supplier->fresh();
        });
    }
}

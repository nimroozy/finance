<?php

namespace App\Services\ServiceLifecycle;

use App\Models\Services\Service;
use App\Models\Services\ServiceContract;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ServiceContractService
{
    public function __construct(
        private ServiceNumberService $numbers,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $actor = null): ServiceContract
    {
        if (empty($data['customer_id']) || empty($data['branch_id'])) {
            throw new InvalidArgumentException('Contract requires customer_id and branch_id.');
        }

        return DB::transaction(function () use ($data, $actor) {
            $contract = ServiceContract::query()->create([
                'contract_number' => $this->numbers->nextContractNumber((int) $data['branch_id']),
                'customer_id' => $data['customer_id'],
                'branch_id' => $data['branch_id'],
                'service_id' => $data['service_id'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'renewal_type' => $data['renewal_type'] ?? 'manual',
                'setup_fee' => $data['setup_fee'] ?? null,
                'recurring_fee' => $data['recurring_fee'] ?? null,
                'signed_date' => $data['signed_date'] ?? null,
                'file_path' => $data['file_path'] ?? null,
                'status' => $data['status'] ?? ServiceContract::STATUS_DRAFT,
                'zoho_reference' => $data['zoho_reference'] ?? null,
            ]);

            if (! empty($data['service_id'])) {
                $service = Service::query()->findOrFail($data['service_id']);
                $service->contract_id = $contract->id;
                $service->updated_by = $actor?->id;
                $service->save();
            }

            $this->audit->log('service_contract.created', $contract, null, $contract->toArray(), $data['branch_id'] ?? null);

            return $contract;
        });
    }


    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ServiceContract $contract, array $data, ?User $actor = null): ServiceContract
    {
        foreach (['service_id', 'start_date', 'end_date', 'renewal_type', 'setup_fee', 'recurring_fee', 'signed_date', 'file_path', 'status', 'zoho_reference', 'branch_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $contract->{$field} = $data[$field];
            }
        }
        $contract->save();

        if (! empty($data['service_id'])) {
            $service = Service::query()->findOrFail($data['service_id']);
            $service->contract_id = $contract->id;
            $service->updated_by = $actor?->id;
            $service->save();
        }

        $this->audit->log('service_contract.updated', $contract, null, $contract->toArray(), $contract->branch_id);

        return $contract->fresh();
    }
    public function activate(ServiceContract $contract, ?User $actor = null): ServiceContract
    {
        $contract->status = ServiceContract::STATUS_ACTIVE;
        $contract->signed_date = $contract->signed_date ?? now()->toDateString();
        $contract->save();
        $this->audit->log('service_contract.activated', $contract, null, $contract->toArray());

        return $contract;
    }
}

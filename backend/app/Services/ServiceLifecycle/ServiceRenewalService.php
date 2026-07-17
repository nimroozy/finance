<?php

namespace App\Services\ServiceLifecycle;

use App\Models\Services\Service;
use App\Models\Services\ServiceRenewal;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ServiceRenewalService
{
    public function __construct(
        private ServiceLifecycleService $lifecycle,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function renew(Service $service, array $data, ?User $actor = null): ServiceRenewal
    {
        $term = (int) ($data['term_months'] ?? 12);
        if ($term < 1) {
            throw new InvalidArgumentException('term_months must be >= 1.');
        }

        return DB::transaction(function () use ($service, $data, $actor, $term) {
            $oldExpiration = $service->expiration_date;
            $base = $oldExpiration && $oldExpiration->isFuture() ? $oldExpiration->copy() : now();
            $newExpiration = isset($data['new_expiration'])
                ? $data['new_expiration']
                : $base->copy()->addMonths($term)->toDateString();

            $renewal = ServiceRenewal::query()->create([
                'service_id' => $service->id,
                'old_expiration' => $oldExpiration,
                'new_expiration' => $newExpiration,
                'term_months' => $term,
                'price' => $data['price'] ?? $service->mrr,
                'approved_by' => $actor?->id,
                'zoho_invoice_ref' => $data['zoho_invoice_ref'] ?? null,
                'payment_ref' => $data['payment_ref'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $service->expiration_date = $newExpiration;
            if ($service->commercial_status === Service::COMMERCIAL_EXPIRED) {
                $this->lifecycle->transition($service, [
                    'commercial' => Service::COMMERCIAL_ACTIVE,
                ], $actor, 'renewal', 'renewal');
                $service->refresh();
            }
            $service->updated_by = $actor?->id;
            $service->save();

            $this->audit->log('service.renewed', $service, ['expiration_date' => $oldExpiration], [
                'expiration_date' => $newExpiration,
            ], $service->branch_id);

            return $renewal;
        });
    }
}

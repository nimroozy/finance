<?php

namespace App\Services\Crm;

use App\Models\Crm\Lead;
use App\Models\Customer;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Search and link CRM leads to local customers that already mirror Zoho contacts.
 * Does not create Zoho customers or call Radius — Stage 12 / Zoho sync own those paths.
 */
class CrmZohoCustomerLinkService
{
    public function __construct(
        private AuditLogger $audit,
    ) {}

    /**
     * Search locally synced Zoho-mirrored customers (must have zoho_contact_id).
     *
     * @param  array{q?: string, phone?: string, name?: string, zoho_contact_id?: string, branch_id?: int, limit?: int}  $filters
     * @return Collection<int, Customer>
     */
    public function searchMirror(array $filters = []): Collection
    {
        $limit = min(50, max(1, (int) ($filters['limit'] ?? 20)));

        $query = Customer::query()
            ->whereNotNull('zoho_contact_id')
            ->where('zoho_contact_id', '!=', '')
            ->where('is_unmapped', false);

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', (int) $filters['branch_id']);
        }

        if (! empty($filters['zoho_contact_id'])) {
            $query->where('zoho_contact_id', $filters['zoho_contact_id']);
        }

        if (! empty($filters['phone'])) {
            $raw = '%'.$filters['phone'].'%';
            $query->where(function (Builder $q) use ($raw) {
                $q->where('phone', 'like', $raw)
                    ->orWhere('mobile', 'like', $raw)
                    ->orWhere('whatsapp_number', 'like', $raw);
            });
        }

        if (! empty($filters['name'])) {
            $term = '%'.$filters['name'].'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('contact_name', 'like', $term)
                    ->orWhere('company_name', 'like', $term)
                    ->orWhere('clean_display_name', 'like', $term);
            });
        }

        if (! empty($filters['q']) && empty($filters['phone']) && empty($filters['name']) && empty($filters['zoho_contact_id'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('contact_name', 'like', $term)
                    ->orWhere('company_name', 'like', $term)
                    ->orWhere('customer_number', 'like', $term)
                    ->orWhere('zoho_contact_id', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('mobile', 'like', $term);
            });
        }

        return $query->orderBy('contact_name')->limit($limit)->get();
    }

    /**
     * Link a lead to an existing Zoho-mirrored local customer (sets converted_customer_id).
     */
    public function linkLeadToCustomer(Lead $lead, Customer $customer, ?User $actor = null): Lead
    {
        if ($lead->converted_customer_id && (int) $lead->converted_customer_id !== (int) $customer->id) {
            throw new InvalidArgumentException('Lead is already linked to a different customer.');
        }

        if (! $customer->zoho_contact_id) {
            throw new InvalidArgumentException(
                'Customer must already be linked to Zoho (zoho_contact_id required). Create/sync the contact in Zoho first, then search and link.'
            );
        }

        if ((int) $customer->branch_id !== (int) $lead->branch_id) {
            throw new InvalidArgumentException('Customer branch must match the lead branch.');
        }

        $lead->converted_customer_id = $customer->id;
        $lead->save();

        $this->audit->log('crm.lead.zoho_customer_linked', $lead, null, [
            'customer_id' => $customer->id,
            'zoho_contact_id' => $customer->zoho_contact_id,
        ], $lead->branch_id);

        return $lead->fresh(['convertedCustomer']);
    }
}

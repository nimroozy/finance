<?php

namespace App\Services\Search;

use App\Models\Crm\Lead;
use App\Models\Customer;
use App\Models\Inventory\Equipment;
use App\Models\Inventory\Product;
use App\Models\Inventory\Site;
use App\Models\Inventory\Tower;
use App\Models\Inventory\Transfer;
use App\Models\Payment;
use App\Models\Services\Service;
use App\Models\Tickets\Installation;
use App\Models\Tickets\Task;
use App\Models\Tickets\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;

class OperationalSearchService
{
    /**
     * @param  list<int>  $branchIds
     * @return array<string, Collection>
     */
    public function search(string $query, array $branchIds, User $actor, int $limit = 25): array
    {
        $branchIds = array_values(array_unique(array_map('intval', $branchIds)));
        if ($branchIds === []) {
            return $this->empty();
        }

        if (! $actor->isSuperAdmin() && ! $actor->isCentralFinanceAdmin()) {
            $branchIds = array_values(array_intersect($branchIds, $actor->branchIds()));
        }

        $q = trim($query);
        if ($q === '' || $branchIds === []) {
            return $this->empty();
        }

        $like = '%'.$q.'%';
        $limit = max(1, min(100, $limit));

        return [
            'customers' => $this->searchCustomers($like, $q, $branchIds, $actor, $limit),
            'services' => $this->searchServices($like, $q, $branchIds, $actor, $limit),
            'leads' => $this->searchLeads($like, $q, $branchIds, $actor, $limit),
            'tickets' => $this->searchTickets($like, $q, $branchIds, $actor, $limit),
            'tasks' => $this->searchTasks($like, $q, $branchIds, $actor, $limit),
            'installations' => $this->searchInstallations($like, $q, $branchIds, $actor, $limit),
            'payments' => $this->searchPayments($like, $q, $branchIds, $actor, $limit),
            'products' => $this->searchProducts($like, $actor, $limit),
            'equipment' => $this->searchEquipment($like, $branchIds, $actor, $limit),
            'transfers' => $this->searchTransfers($like, $q, $branchIds, $actor, $limit),
            'sites' => $this->searchSites($like, $branchIds, $actor, $limit),
            'towers' => $this->searchTowers($like, $branchIds, $actor, $limit),
            'users' => $this->searchUsers($like, $branchIds, $actor, $limit),
        ];
    }

    /**
     * @return array<string, Collection>
     */
    private function empty(): array
    {
        return [
            'customers' => collect(),
            'services' => collect(),
            'leads' => collect(),
            'tickets' => collect(),
            'tasks' => collect(),
            'installations' => collect(),
            'payments' => collect(),
            'products' => collect(),
            'equipment' => collect(),
            'transfers' => collect(),
            'sites' => collect(),
            'towers' => collect(),
            'users' => collect(),
        ];
    }

    /**
     * @param  list<int>  $branchIds
     */
    private function searchCustomers(string $like, string $q, array $branchIds, User $actor, int $limit): Collection
    {
        if (! $actor->can('customers.view')) {
            return collect();
        }

        return Customer::query()
            ->whereIn('branch_id', $branchIds)
            ->where(function ($qq) use ($like, $q) {
                $qq->where('contact_name', 'like', $like)
                    ->orWhere('clean_display_name', 'like', $like)
                    ->orWhere('company_name', 'like', $like)
                    ->orWhere('customer_number', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('mobile', 'like', $like)
                    ->orWhere('whatsapp_number', 'like', $like)
                    ->orWhere('zoho_contact_id', 'like', $like)
                    ->orWhere('email', 'like', $like);
                if (ctype_digit($q)) {
                    $qq->orWhere('id', (int) $q);
                }
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'branch_id', 'customer_number', 'contact_name', 'company_name', 'phone', 'zoho_contact_id']);
    }

    /**
     * @param  list<int>  $branchIds
     */
    private function searchServices(string $like, string $q, array $branchIds, User $actor, int $limit): Collection
    {
        if (! $actor->can('services.view')) {
            return collect();
        }

        return Service::query()
            ->whereIn('branch_id', $branchIds)
            ->where(function ($qq) use ($like, $q) {
                $qq->where('service_number', 'like', $like)
                    ->orWhere('circuit_id', 'like', $like)
                    ->orWhere('customer_reference', 'like', $like)
                    ->orWhere('zoho_customer_id', 'like', $like)
                    ->orWhere('static_ip', 'like', $like)
                    ->orWhere('public_ip', 'like', $like)
                    ->orWhere('private_ip', 'like', $like);
                if (ctype_digit($q)) {
                    $qq->orWhere('id', (int) $q);
                }
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get([
                'id', 'branch_id', 'service_number', 'customer_id', 'commercial_status',
                'circuit_id', 'customer_reference', 'zoho_customer_id',
            ]);
    }

    /**
     * @param  list<int>  $branchIds
     */
    private function searchLeads(string $like, string $q, array $branchIds, User $actor, int $limit): Collection
    {
        if (! $actor->can('crm.leads.view')) {
            return collect();
        }

        return Lead::query()
            ->whereIn('branch_id', $branchIds)
            ->where(function ($qq) use ($like, $q) {
                $qq->where('lead_number', 'like', $like)
                    ->orWhere('company', 'like', $like)
                    ->orWhere('contact_person', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('whatsapp', 'like', $like)
                    ->orWhere('email', 'like', $like);
                if (ctype_digit($q)) {
                    $qq->orWhere('id', (int) $q);
                }
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'branch_id', 'lead_number', 'company', 'contact_person', 'phone', 'pipeline_stage']);
    }

    /**
     * @param  list<int>  $branchIds
     */
    private function searchTickets(string $like, string $q, array $branchIds, User $actor, int $limit): Collection
    {
        if (! $actor->can('tickets.view')) {
            return collect();
        }

        return Ticket::query()
            ->whereIn('branch_id', $branchIds)
            ->where(function ($qq) use ($like, $q) {
                $qq->where('ticket_number', 'like', $like)
                    ->orWhere('subject', 'like', $like)
                    ->orWhere('description', 'like', $like);
                if (ctype_digit($q)) {
                    $qq->orWhere('id', (int) $q);
                }
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'branch_id', 'ticket_number', 'subject', 'status']);
    }

    /**
     * @param  list<int>  $branchIds
     */
    private function searchTasks(string $like, string $q, array $branchIds, User $actor, int $limit): Collection
    {
        if (! $actor->can('tasks.view')) {
            return collect();
        }

        return Task::query()
            ->whereIn('branch_id', $branchIds)
            ->where(function ($qq) use ($like, $q) {
                $qq->where('task_number', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('description', 'like', $like);
                if (ctype_digit($q)) {
                    $qq->orWhere('id', (int) $q);
                }
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'branch_id', 'task_number', 'title', 'status']);
    }

    /**
     * @param  list<int>  $branchIds
     */
    private function searchInstallations(string $like, string $q, array $branchIds, User $actor, int $limit): Collection
    {
        if (! $actor->can('installations.view')) {
            return collect();
        }

        return Installation::query()
            ->whereIn('branch_id', $branchIds)
            ->where(function ($qq) use ($like, $q) {
                $qq->where('installation_number', 'like', $like)
                    ->orWhere('address', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhere('prospect_name', 'like', $like)
                    ->orWhere('phone', 'like', $like);
                if (ctype_digit($q)) {
                    $qq->orWhere('id', (int) $q);
                }
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'branch_id', 'installation_number', 'prospect_name', 'phone', 'status']);
    }

    /**
     * @param  list<int>  $branchIds
     */
    private function searchPayments(string $like, string $q, array $branchIds, User $actor, int $limit): Collection
    {
        if (! $actor->can('payments.view')) {
            return collect();
        }

        return Payment::query()
            ->whereIn('branch_id', $branchIds)
            ->where(function ($qq) use ($like, $q) {
                $qq->where('payment_reference', 'like', $like)
                    ->orWhere('external_reference', 'like', $like)
                    ->orWhere('zoho_payment_id', 'like', $like)
                    ->orWhere('zoho_reference', 'like', $like)
                    ->orWhere('uuid', 'like', $like);
                if (ctype_digit($q)) {
                    $qq->orWhere('id', (int) $q);
                }
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'branch_id', 'payment_reference', 'amount', 'status', 'customer_id']);
    }

    private function searchProducts(string $like, User $actor, int $limit): Collection
    {
        if (! $actor->can('inventory.products.view')) {
            return collect();
        }

        return Product::query()
            ->where(function ($qq) use ($like) {
                $qq->where('code', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('name_en', 'like', $like)
                    ->orWhere('barcode', 'like', $like);
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'code', 'sku', 'name_en', 'tracking_mode']);
    }

    /**
     * @param  list<int>  $branchIds
     */
    private function searchEquipment(string $like, array $branchIds, User $actor, int $limit): Collection
    {
        if (! $actor->can('inventory.equipment.view')) {
            return collect();
        }

        return Equipment::query()
            ->whereIn('branch_id', $branchIds)
            ->where(function ($qq) use ($like) {
                $qq->where('equipment_number', 'like', $like)
                    ->orWhere('serial_number', 'like', $like)
                    ->orWhere('mac_address', 'like', $like)
                    ->orWhere('barcode', 'like', $like);
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'branch_id', 'equipment_number', 'serial_number', 'mac_address', 'status', 'product_id']);
    }

    /**
     * @param  list<int>  $branchIds
     */
    private function searchTransfers(string $like, string $q, array $branchIds, User $actor, int $limit): Collection
    {
        if (! $actor->can('inventory.transfers.view')) {
            return collect();
        }

        return Transfer::query()
            ->whereIn('branch_id', $branchIds)
            ->where(function ($qq) use ($like, $q) {
                $qq->where('transfer_number', 'like', $like)
                    ->orWhere('notes', 'like', $like);
                if (ctype_digit($q)) {
                    $qq->orWhere('id', (int) $q);
                }
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'branch_id', 'transfer_number', 'status']);
    }

    /**
     * @param  list<int>  $branchIds
     */
    private function searchSites(string $like, array $branchIds, User $actor, int $limit): Collection
    {
        if (! $actor->can('inventory.sites.view')) {
            return collect();
        }

        return Site::query()
            ->whereIn('branch_id', $branchIds)
            ->where(function ($qq) use ($like) {
                $qq->where('code', 'like', $like)->orWhere('name', 'like', $like);
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'branch_id', 'code', 'name', 'status']);
    }

    /**
     * @param  list<int>  $branchIds
     */
    private function searchTowers(string $like, array $branchIds, User $actor, int $limit): Collection
    {
        if (! $actor->can('inventory.towers.view')) {
            return collect();
        }

        return Tower::query()
            ->whereIn('branch_id', $branchIds)
            ->where(function ($qq) use ($like) {
                $qq->where('code', 'like', $like)->orWhere('owner', 'like', $like);
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'branch_id', 'code', 'site_id', 'operational_status']);
    }

    /**
     * @param  list<int>  $branchIds
     */
    private function searchUsers(string $like, array $branchIds, User $actor, int $limit): Collection
    {
        if (! $actor->can('users.view')) {
            return collect();
        }

        return User::query()
            ->where(function ($qq) use ($like) {
                $qq->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('username', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            })
            ->whereHas('branches', fn ($b) => $b->whereIn('branches.id', $branchIds))
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'name', 'email', 'username', 'phone', 'status']);
    }
}

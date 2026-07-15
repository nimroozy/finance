<?php

namespace App\Services\Assignments;

use App\Jobs\ProcessBulkAssignmentJob;
use App\Models\AssignmentBulkOperation;
use App\Models\Collector;
use App\Models\Customer;
use App\Models\CustomerAssignment;
use App\Models\CustomerAssignmentHistory;
use App\Models\Invoice;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentService
{
    public function __construct(
        protected AuditLogger $audit,
        protected NotificationService $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): CustomerAssignment
    {
        return DB::transaction(function () use ($data, $actor) {
            $customer = Customer::withoutGlobalScopes()->lockForUpdate()->findOrFail($data['customer_id']);
            $this->assertCustomerAssignable($customer);

            $existing = CustomerAssignment::withoutGlobalScopes()
                ->where('customer_id', $customer->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'customer_id' => ['Customer already has an active assignment.'],
                ]);
            }

            $collector = Collector::with('user')->findOrFail($data['collector_id']);
            $this->assertCollectorEligible($collector, (int) $customer->branch_id);

            $snapshot = $this->debtSnapshot($customer);

            $assignment = CustomerAssignment::withoutGlobalScopes()->create([
                'branch_id' => $customer->branch_id,
                'customer_id' => $customer->id,
                'collector_id' => $collector->id,
                'assigned_by' => $actor->id,
                'status' => CustomerAssignment::STATUS_ASSIGNED,
                'is_active' => true,
                'is_primary' => true,
                'allow_shared_assignment' => (bool) ($data['allow_shared_assignment'] ?? false),
                'priority' => $data['priority'] ?? CustomerAssignment::PRIORITY_NORMAL,
                'assignment_source' => $data['assignment_source'] ?? 'manual',
                'assignment_reason' => $data['assignment_reason'] ?? null,
                'debt_snapshot_outstanding' => $snapshot['outstanding'],
                'debt_snapshot_overdue' => $snapshot['overdue'],
                'debt_snapshot_invoice_count' => $snapshot['invoice_count'],
                'debt_snapshot_currency' => $snapshot['currency'],
                'oldest_due_date_snapshot' => $snapshot['oldest_due_date'],
                'days_overdue_snapshot' => $snapshot['days_overdue'],
                'due_date' => $data['due_date'] ?? null,
                'planned_visit_date' => $data['planned_visit_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'manager_notes' => $data['manager_notes'] ?? null,
                'collector_instructions' => $data['collector_instructions'] ?? null,
                'bulk_operation_id' => $data['bulk_operation_id'] ?? null,
                'reassigned_from_id' => $data['reassigned_from_id'] ?? null,
                'reassignment_count' => (int) ($data['reassignment_count'] ?? 0),
            ]);

            $this->recordHistory($assignment, 'created', null, CustomerAssignment::STATUS_ASSIGNED, $actor, null, $collector->id, $data['notes'] ?? null);

            $this->audit->log('assignment.created', $assignment, null, $assignment->toArray(), $assignment->branch_id);

            if ($collector->user) {
                $this->notifications->notify(
                    $collector->user,
                    'assignment.created',
                    'New assignment',
                    'You have been assigned customer '.$customer->contact_name,
                    ['assignment_id' => $assignment->id, 'customer_id' => $customer->id],
                    $assignment->branch_id
                );
            }

            return $assignment->fresh(['customer', 'collector.user', 'branch']);
        });
    }

    /**
     * @param  array{customer_ids: list<int>, collector_id: int, priority?: string, due_date?: string|null, notes?: string|null}  $data
     * @return array{operation: AssignmentBulkOperation, assignments?: list<CustomerAssignment>, queued?: bool}
     */
    public function bulk(array $data, User $actor): array
    {
        $customerIds = array_values(array_unique($data['customer_ids']));
        $threshold = (int) config('collection.bulk_assignment_sync_threshold', 50);

        $customers = Customer::withoutGlobalScopes()
            ->whereIn('id', $customerIds)
            ->get();

        $totalOutstanding = $customers->sum(fn (Customer $c) => (float) $c->outstanding_receivable);

        $operation = AssignmentBulkOperation::withoutGlobalScopes()->create([
            'branch_id' => $data['branch_id'] ?? $customers->first()?->branch_id,
            'created_by' => $actor->id,
            'strategy' => AssignmentBulkOperation::STRATEGY_MANUAL,
            'status' => count($customerIds) > $threshold
                ? AssignmentBulkOperation::STATUS_QUEUED
                : AssignmentBulkOperation::STATUS_PROCESSING,
            'preview_payload' => [
                'customer_ids' => $customerIds,
                'collector_id' => $data['collector_id'],
                'priority' => $data['priority'] ?? CustomerAssignment::PRIORITY_NORMAL,
                'due_date' => $data['due_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ],
            'selected_count' => count($customerIds),
            'total_outstanding' => $totalOutstanding,
        ]);

        if (count($customerIds) > $threshold) {
            ProcessBulkAssignmentJob::dispatch($operation->id, $actor->id);

            return ['operation' => $operation, 'queued' => true];
        }

        $assignments = $this->executeBulk($operation, $actor);

        return ['operation' => $operation->fresh(), 'assignments' => $assignments, 'queued' => false];
    }

    /**
     * @return list<CustomerAssignment>
     */
    public function executeBulk(AssignmentBulkOperation $operation, User $actor): array
    {
        $payload = $operation->preview_payload ?? [];
        $customerIds = $payload['customer_ids'] ?? [];
        $created = [];
        $errors = [];

        $operation->update(['status' => AssignmentBulkOperation::STATUS_PROCESSING]);

        foreach ($customerIds as $customerId) {
            try {
                $created[] = $this->create([
                    'customer_id' => $customerId,
                    'collector_id' => $payload['collector_id'],
                    'priority' => $payload['priority'] ?? CustomerAssignment::PRIORITY_NORMAL,
                    'due_date' => $payload['due_date'] ?? null,
                    'notes' => $payload['notes'] ?? null,
                    'assignment_source' => 'bulk',
                    'bulk_operation_id' => $operation->id,
                ], $actor);
            } catch (\Throwable $e) {
                $errors[] = ['customer_id' => $customerId, 'error' => $e->getMessage()];
            }
        }

        $operation->update([
            'status' => AssignmentBulkOperation::STATUS_COMPLETED,
            'result_payload' => [
                'created_ids' => collect($created)->pluck('id')->all(),
                'errors' => $errors,
            ],
            'confirmed_at' => now(),
        ]);

        return $created;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function reassign(CustomerAssignment $assignment, array $data, User $actor): CustomerAssignment
    {
        return DB::transaction(function () use ($assignment, $data, $actor) {
            $assignment = CustomerAssignment::withoutGlobalScopes()->lockForUpdate()->findOrFail($assignment->id);

            if (! $assignment->is_active) {
                throw ValidationException::withMessages([
                    'assignment' => ['Only active assignments can be reassigned.'],
                ]);
            }

            $newCollector = Collector::with('user')->findOrFail($data['collector_id']);
            $this->assertCollectorEligible($newCollector, (int) $assignment->branch_id);

            if ((int) $newCollector->id === (int) $assignment->collector_id) {
                throw ValidationException::withMessages([
                    'collector_id' => ['Assignment is already with this collector.'],
                ]);
            }

            $fromCollectorId = $assignment->collector_id;
            $old = $assignment->toArray();

            $assignment->update([
                'status' => CustomerAssignment::STATUS_REASSIGNED,
                'is_active' => false,
                'closed_at' => now(),
                'notes' => $data['notes'] ?? $assignment->notes,
            ]);

            $this->recordHistory(
                $assignment,
                'reassigned',
                $old['status'],
                CustomerAssignment::STATUS_REASSIGNED,
                $actor,
                $fromCollectorId,
                $newCollector->id,
                $data['notes'] ?? null
            );

            $this->audit->log('assignment.reassigned', $assignment, $old, $assignment->fresh()->toArray(), $assignment->branch_id, $data['notes'] ?? null);

            $new = $this->create([
                'customer_id' => $assignment->customer_id,
                'collector_id' => $newCollector->id,
                'priority' => $data['priority'] ?? $assignment->priority,
                'due_date' => $data['due_date'] ?? $assignment->due_date?->toDateString(),
                'planned_visit_date' => $data['planned_visit_date'] ?? $assignment->planned_visit_date?->toDateString(),
                'notes' => $data['notes'] ?? null,
                'manager_notes' => $data['manager_notes'] ?? $assignment->manager_notes,
                'collector_instructions' => $data['collector_instructions'] ?? $assignment->collector_instructions,
                'assignment_source' => 'reassign',
                'assignment_reason' => $data['assignment_reason'] ?? $data['notes'] ?? 'Reassignment',
                'reassigned_from_id' => $assignment->id,
                'reassignment_count' => ((int) $assignment->reassignment_count) + 1,
            ], $actor);

            return $new;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function cancel(CustomerAssignment $assignment, array $data, User $actor): CustomerAssignment
    {
        return DB::transaction(function () use ($assignment, $data, $actor) {
            $assignment = CustomerAssignment::withoutGlobalScopes()->lockForUpdate()->findOrFail($assignment->id);

            if (! $assignment->is_active) {
                throw ValidationException::withMessages([
                    'assignment' => ['Assignment is not active.'],
                ]);
            }

            $old = $assignment->toArray();

            $assignment->update([
                'status' => CustomerAssignment::STATUS_CANCELLED,
                'is_active' => false,
                'closed_at' => now(),
                'cancel_reason' => $data['reason'] ?? $data['cancel_reason'] ?? 'Cancelled',
            ]);

            $this->recordHistory(
                $assignment,
                'cancelled',
                $old['status'],
                CustomerAssignment::STATUS_CANCELLED,
                $actor,
                $assignment->collector_id,
                null,
                $assignment->cancel_reason
            );

            $this->audit->log('assignment.cancelled', $assignment, $old, $assignment->fresh()->toArray(), $assignment->branch_id, $assignment->cancel_reason);

            return $assignment->fresh(['customer', 'collector.user']);
        });
    }

    public function accept(CustomerAssignment $assignment, User $actor): CustomerAssignment
    {
        $collector = $actor->collector;

        if (! $collector || (int) $assignment->collector_id !== (int) $collector->id) {
            throw ValidationException::withMessages([
                'assignment' => ['You can only accept your own assignments.'],
            ]);
        }

        if (! $assignment->is_active) {
            throw ValidationException::withMessages([
                'assignment' => ['Assignment is not active.'],
            ]);
        }

        $oldStatus = $assignment->status;

        $assignment->update([
            'status' => CustomerAssignment::STATUS_ACCEPTED,
            'accepted_at' => $assignment->accepted_at ?? now(),
        ]);

        $this->recordHistory($assignment, 'accepted', $oldStatus, CustomerAssignment::STATUS_ACCEPTED, $actor, $collector->id, $collector->id);
        $this->audit->log('assignment.accepted', $assignment, null, $assignment->toArray(), $assignment->branch_id);

        return $assignment->fresh();
    }

    public function markViewed(CustomerAssignment $assignment, User $actor): CustomerAssignment
    {
        $collector = $actor->collector;

        if (! $collector || (int) $assignment->collector_id !== (int) $collector->id) {
            throw ValidationException::withMessages([
                'assignment' => ['You can only view your own assignments.'],
            ]);
        }

        if ($assignment->first_viewed_at === null) {
            $assignment->update(['first_viewed_at' => now()]);
            $this->recordHistory($assignment, 'viewed', $assignment->status, $assignment->status, $actor, $collector->id, $collector->id);
        }

        return $assignment->fresh();
    }

    /**
     * @param  array{branch_id: int, strategy: string, customer_ids?: list<int>, collector_ids?: list<int>}  $data
     */
    public function autoPreview(array $data, User $actor): AssignmentBulkOperation
    {
        $branchId = (int) $data['branch_id'];
        $strategy = $data['strategy'] ?? AssignmentBulkOperation::STRATEGY_LEAST_LOADED;

        $query = Customer::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('is_unmapped', false)
            ->where('status', Customer::STATUS_ACTIVE)
            ->whereDoesntHave('assignments', fn ($q) => $q->withoutGlobalScopes()->where('is_active', true));

        if (! empty($data['customer_ids'])) {
            $query->whereIn('id', $data['customer_ids']);
        }

        $customers = $query->orderByDesc('outstanding_receivable')->get();

        $collectorsQuery = Collector::query()
            ->where('is_active', true)
            ->whereHas('user.branches', fn ($q) => $q->where('branches.id', $branchId));

        if (! empty($data['collector_ids'])) {
            $collectorsQuery->whereIn('id', $data['collector_ids']);
        }

        $collectors = $collectorsQuery->withCount(['activeAssignments'])->get();

        if ($collectors->isEmpty()) {
            throw ValidationException::withMessages([
                'collector_ids' => ['No eligible collectors in this branch.'],
            ]);
        }

        $plan = [];
        $loads = $collectors->mapWithKeys(fn (Collector $c) => [$c->id => $c->active_assignments_count])->all();

        foreach ($customers as $customer) {
            if ($strategy === AssignmentBulkOperation::STRATEGY_ROUND_ROBIN) {
                asort($loads);
                $collectorId = (int) array_key_first($loads);
            } else {
                asort($loads);
                $collectorId = (int) array_key_first($loads);
            }

            $plan[] = [
                'customer_id' => $customer->id,
                'collector_id' => $collectorId,
                'outstanding' => (string) $customer->outstanding_receivable,
            ];
            $loads[$collectorId] = ($loads[$collectorId] ?? 0) + 1;
        }

        return AssignmentBulkOperation::withoutGlobalScopes()->create([
            'branch_id' => $branchId,
            'created_by' => $actor->id,
            'strategy' => $strategy,
            'status' => AssignmentBulkOperation::STATUS_PREVIEW,
            'preview_payload' => ['plan' => $plan],
            'selected_count' => count($plan),
            'total_outstanding' => $customers->sum(fn ($c) => (float) $c->outstanding_receivable),
        ]);
    }

    public function autoConfirm(AssignmentBulkOperation $operation, User $actor): AssignmentBulkOperation
    {
        if ($operation->status !== AssignmentBulkOperation::STATUS_PREVIEW) {
            throw ValidationException::withMessages([
                'operation' => ['Only preview operations can be confirmed.'],
            ]);
        }

        $plan = $operation->preview_payload['plan'] ?? [];
        $created = [];
        $errors = [];

        $operation->update(['status' => AssignmentBulkOperation::STATUS_PROCESSING]);

        foreach ($plan as $row) {
            try {
                $created[] = $this->create([
                    'customer_id' => $row['customer_id'],
                    'collector_id' => $row['collector_id'],
                    'assignment_source' => 'auto',
                    'bulk_operation_id' => $operation->id,
                ], $actor);
            } catch (\Throwable $e) {
                $errors[] = ['customer_id' => $row['customer_id'], 'error' => $e->getMessage()];
            }
        }

        $operation->update([
            'status' => AssignmentBulkOperation::STATUS_COMPLETED,
            'result_payload' => [
                'created_ids' => collect($created)->pluck('id')->all(),
                'errors' => $errors,
            ],
            'confirmed_at' => now(),
        ]);

        return $operation->fresh();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function workload(?int $branchId = null): array
    {
        $query = Collector::query()
            ->with(['user:id,name,email'])
            ->withCount(['activeAssignments'])
            ->where('is_active', true);

        if ($branchId) {
            $query->whereHas('user.branches', fn ($q) => $q->where('branches.id', $branchId));
        }

        return $query->get()->map(fn (Collector $c) => [
            'collector_id' => $c->id,
            'user_id' => $c->user_id,
            'name' => $c->user?->name,
            'employee_code' => $c->employee_code,
            'active_assignments' => $c->active_assignments_count,
            'max_active_assignments' => $c->max_active_assignments,
        ])->all();
    }

    public function exportCsv(iterable $assignments): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="assignments-export.csv"',
        ];

        return response()->stream(function () use ($assignments) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'id', 'branch_id', 'customer_id', 'collector_id', 'status', 'is_active',
                'debt_snapshot_outstanding', 'priority', 'due_date', 'created_at',
            ]);

            foreach ($assignments as $a) {
                fputcsv($out, [
                    $a->id,
                    $a->branch_id,
                    $a->customer_id,
                    $a->collector_id,
                    $a->status,
                    $a->is_active ? '1' : '0',
                    $a->debt_snapshot_outstanding,
                    $a->priority,
                    $a->due_date,
                    $a->created_at,
                ]);
            }

            fclose($out);
        }, 200, $headers);
    }

    protected function assertCustomerAssignable(Customer $customer): void
    {
        if ($customer->is_unmapped || $customer->branch_id === null) {
            throw ValidationException::withMessages([
                'customer_id' => ['Cannot assign an unmapped customer.'],
            ]);
        }

        if ($customer->status === Customer::STATUS_INACTIVE || $customer->status === Customer::STATUS_ARCHIVED) {
            throw ValidationException::withMessages([
                'customer_id' => ['Cannot assign inactive or archived customers.'],
            ]);
        }
    }

    protected function assertCollectorEligible(Collector $collector, int $branchId): void
    {
        if (! $collector->is_active) {
            throw ValidationException::withMessages([
                'collector_id' => ['Collector is inactive.'],
            ]);
        }

        $sharesBranch = $collector->user
            ? $collector->user->branches()->withoutGlobalScopes()->where('branches.id', $branchId)->exists()
            : false;

        if (! $sharesBranch) {
            throw ValidationException::withMessages([
                'collector_id' => ['Collector must share the customer branch.'],
            ]);
        }

        if ($collector->max_active_assignments !== null) {
            $active = $collector->activeAssignments()->count();
            if ($active >= $collector->max_active_assignments) {
                throw ValidationException::withMessages([
                    'collector_id' => ['Collector has reached max active assignments.'],
                ]);
            }
        }
    }

    /**
     * @return array{outstanding: string, overdue: string|null, invoice_count: int, currency: string, oldest_due_date: string|null, days_overdue: int|null}
     */
    protected function debtSnapshot(Customer $customer): array
    {
        $invoices = Invoice::withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->where('balance', '>', 0)
            ->get();

        $overdueInvoices = $invoices->filter(fn (Invoice $i) => $i->due_date && $i->due_date->isPast());
        $overdue = $overdueInvoices->sum(fn (Invoice $i) => (float) $i->balance);
        $oldestDue = $invoices->pluck('due_date')->filter()->sort()->first();
        $daysOverdue = null;
        if ($oldestDue) {
            $daysOverdue = $oldestDue->isPast()
                ? (int) $oldestDue->diffInDays(now()->startOfDay())
                : 0;
        }

        return [
            'outstanding' => (string) $customer->outstanding_receivable,
            'overdue' => number_format((float) $overdue, 4, '.', ''),
            'invoice_count' => $invoices->count(),
            'currency' => $customer->currency ?: 'AFN',
            'oldest_due_date' => $oldestDue?->toDateString(),
            'days_overdue' => $daysOverdue,
        ];
    }

    protected function recordHistory(
        CustomerAssignment $assignment,
        string $event,
        ?string $fromStatus,
        ?string $toStatus,
        User $actor,
        ?int $fromCollectorId = null,
        ?int $toCollectorId = null,
        ?string $notes = null,
        ?array $metadata = null,
    ): void {
        CustomerAssignmentHistory::create([
            'assignment_id' => $assignment->id,
            'event' => $event,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => $actor->id,
            'from_collector_id' => $fromCollectorId,
            'to_collector_id' => $toCollectorId,
            'notes' => $notes,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}

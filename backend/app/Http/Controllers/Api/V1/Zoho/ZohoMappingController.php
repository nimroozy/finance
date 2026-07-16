<?php

namespace App\Http\Controllers\Api\V1\Zoho;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAssignment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ZohoBranchMapping;
use App\Models\ZohoLocation;
use App\Models\ZohoLocationDecision;
use App\Services\AuditLogger;
use App\Services\Zoho\ZohoBranchReprocessService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ZohoMappingController extends Controller
{
    public function __construct(
        private ZohoBranchReprocessService $reprocess,
        private AuditLogger $audit,
    ) {}

    public function review(): JsonResponse
    {
        $branches = Branch::withoutGlobalScopes()->get();
        $decisions = ZohoLocationDecision::query()->get()->keyBy('zoho_location_id');

        $rows = ZohoLocation::query()->orderBy('name')->get()->map(function (ZohoLocation $location) use ($branches, $decisions) {
            $invoices = Invoice::withoutGlobalScopes()->where('zoho_location_id', $location->zoho_location_id);
            $customerIds = (clone $invoices)->select('customer_id')->distinct();
            $linked = $branches->firstWhere('zoho_location_id', $location->zoho_location_id);
            [$candidate, $confidence] = $this->candidate($location, $branches);
            $decision = $decisions->get($location->zoho_location_id);

            return [
                'zoho_location_id' => $location->zoho_location_id,
                'name' => $location->name,
                'is_active' => (bool) $location->is_active,
                'linked_branch' => $linked ? $linked->only(['id', 'code', 'name_en']) : null,
                'invoice_count' => (clone $invoices)->count(),
                'mapped_invoice_count' => (clone $invoices)->whereNotNull('branch_id')->count(),
                'customer_count' => DB::query()->fromSub($customerIds, 'location_customers')->count(),
                'payment_count' => Payment::withoutGlobalScopes()->whereIn('customer_id', $customerIds)->count(),
                'assignment_count' => CustomerAssignment::withoutGlobalScopes()->whereIn('customer_id', $customerIds)->count(),
                'recommended_action' => $linked
                    ? 'already_mapped'
                    : ($decision ? $decision->decision : ($candidate && $confidence === 'exact' ? 'link_existing' : ($location->is_active ? 'import_branch' : 'ignore'))),
                'candidate_branch' => $candidate?->only(['id', 'code', 'name_en']),
                'confidence' => $linked ? 'exact' : $confidence,
                'decision' => $decision?->decision,
            ];
        });

        return ApiResponse::success($rows);
    }

    public function conflicts(): JsonResponse
    {
        $customerIds = Invoice::withoutGlobalScopes()
            ->whereNotNull('zoho_location_id')
            ->select('customer_id')
            ->groupBy('customer_id')
            ->havingRaw('COUNT(DISTINCT zoho_location_id) > 1')
            ->pluck('customer_id');
        $branchByLocation = Branch::withoutGlobalScopes()->whereNotNull('zoho_location_id')
            ->get()->keyBy('zoho_location_id');

        $rows = Customer::withoutGlobalScopes()->whereIn('id', $customerIds)->orderBy('id')->get()->map(
            function (Customer $customer) use ($branchByLocation) {
                $locations = Invoice::withoutGlobalScopes()
                    ->where('customer_id', $customer->id)
                    ->whereNotNull('zoho_location_id')
                    ->select('zoho_location_id')
                    ->distinct()
                    ->pluck('zoho_location_id')
                    ->map(function ($id) use ($branchByLocation) {
                        $location = ZohoLocation::query()->where('zoho_location_id', $id)->first();
                        $branch = $branchByLocation->get($id);

                        return [
                            'zoho_location_id' => $id,
                            'name' => $location?->name,
                            'branch' => $branch?->only(['id', 'code', 'name_en']),
                        ];
                    })->values();
                $mappedBranchIds = $locations->pluck('branch.id')->filter()->unique();
                $allMapped = $locations->every(fn (array $location) => isset($location['branch']));

                return [
                    'classification' => $allMapped && $mappedBranchIds->count() === 1
                        ? 'multi_location_same_local_branch'
                        : 'multi_location_conflict',
                    'customer_id' => $customer->id,
                    'name' => $customer->company_name ?: $customer->contact_name,
                    'locations' => $locations,
                    'payments_count' => Payment::withoutGlobalScopes()->where('customer_id', $customer->id)->count(),
                    'assignments_count' => CustomerAssignment::withoutGlobalScopes()->where('customer_id', $customer->id)->count(),
                ];
            }
        );

        return ApiResponse::success($rows);
    }

    public function decide(Request $request, string $zohoId): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['import_branch', 'link_existing', 'ignore', 'defer', 'non_operational'])],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id', 'required_if:action,link_existing'],
            'confirmed' => ['required', 'accepted'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $location = ZohoLocation::query()->where('zoho_location_id', $zohoId)->firstOrFail();
        $branch = null;

        DB::transaction(function () use ($data, $location, $request, &$branch) {
            if ($data['action'] === 'import_branch') {
                $branch = $this->importBranch($location);
                $this->link($branch, $location);
            } elseif ($data['action'] === 'link_existing') {
                $branch = Branch::withoutGlobalScopes()->findOrFail($data['branch_id']);
                $this->link($branch, $location);
            }

            $decision = ZohoLocationDecision::query()->updateOrCreate(
                ['zoho_location_id' => $location->zoho_location_id],
                [
                    'decision' => $data['action'],
                    'branch_id' => $branch?->id ?? $data['branch_id'] ?? null,
                    'decided_by' => $request->user()->id,
                    'notes' => $data['notes'] ?? null,
                ]
            );
            $this->audit->log('zoho.location.mapping_decided', $decision, null, $decision->toArray(), $branch?->id);
        });

        return ApiResponse::success([
            'zoho_location_id' => $location->zoho_location_id,
            'decision' => $data['action'],
            'branch' => $branch?->fresh()?->only(['id', 'code', 'name_en']),
        ]);
    }

    public function reprocess(Request $request, string $zohoId): JsonResponse
    {
        $data = $request->validate([
            'confirmed' => ['required', 'accepted'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:5000'],
        ]);
        $location = ZohoLocation::query()->where('zoho_location_id', $zohoId)->firstOrFail();
        $branch = Branch::withoutGlobalScopes()->where('zoho_location_id', $zohoId)->first();
        if (! $branch) {
            return ApiResponse::error('Location must be linked to a branch before reprocessing.', [], 422);
        }

        $limit = $data['limit'] ?? 500;
        $before = $this->locationCounts($location, $branch);
        $invoiceStats = $this->reprocess->invoices(true, $branch->id, null, $limit, $location->zoho_location_id);
        $customerStats = $this->reprocess->customers(true, $branch->id, null, $limit, $location->zoho_location_id);
        $after = $this->locationCounts($location, $branch);
        $this->audit->log('zoho.location.reprocessed', $location, $before, $after, $branch->id);

        return ApiResponse::success([
            'before' => $before,
            'after' => $after,
            'invoices' => $invoiceStats,
            'customers' => $customerStats,
        ]);
    }

    private function candidate(ZohoLocation $location, Collection $branches): array
    {
        $normalize = fn (?string $value) => mb_strtolower(trim((string) $value));
        $keys = collect([$location->name, $location->code])->map($normalize)->filter();
        $exact = $branches->first(fn (Branch $branch) => collect([$branch->name_en, $branch->code])->map($normalize)->intersect($keys)->isNotEmpty());
        if ($exact) {
            return [$exact, 'exact'];
        }
        $probable = $branches->first(function (Branch $branch) use ($keys, $normalize) {
            return collect([$branch->name_en, $branch->code])->map($normalize)->filter()->contains(
                fn ($branchKey) => $keys->contains(fn ($locationKey) => str_contains($branchKey, $locationKey) || str_contains($locationKey, $branchKey))
            );
        });

        return [$probable, $probable ? 'probable' : 'none'];
    }

    private function importBranch(ZohoLocation $location): Branch
    {
        $base = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $location->code ?: $location->name), 0, 12)) ?: 'ZOH';
        $code = $base;
        for ($suffix = 1; Branch::withoutGlobalScopes()->where('code', $code)->exists(); $suffix++) {
            $code = $base.'-'.$suffix;
        }

        return Branch::withoutGlobalScopes()->create([
            'code' => $code, 'name_en' => $location->name, 'name_fa' => $location->name,
            'province_en' => 'Zoho', 'province_fa' => 'Zoho', 'receipt_prefix' => substr($code, 0, 8),
            'is_active' => $location->is_active,
        ]);
    }

    private function link(Branch $branch, ZohoLocation $location): void
    {
        Branch::withoutGlobalScopes()->where('zoho_location_id', $location->zoho_location_id)
            ->whereKeyNot($branch->id)->update(['zoho_location_id' => null, 'zoho_sync_status' => 'unlinked']);
        $branch->update([
            'zoho_location_id' => $location->zoho_location_id,
            'mapping_type' => 'zoho_location',
            'zoho_sync_status' => 'linked',
            'last_structure_sync_at' => now(),
            'local_override' => false,
        ]);
        ZohoBranchMapping::query()->updateOrCreate(['branch_id' => $branch->id], [
            'mapping_method' => ZohoBranchMapping::METHOD_ZOHO_LOCATION,
            'zoho_value' => $location->zoho_location_id,
            'zoho_label' => $location->name,
            'is_active' => true,
        ]);
    }

    private function locationCounts(ZohoLocation $location, Branch $branch): array
    {
        $invoices = Invoice::withoutGlobalScopes()->where('zoho_location_id', $location->zoho_location_id);
        $customerIds = (clone $invoices)->select('customer_id')->distinct();

        return [
            'invoice_count' => (clone $invoices)->count(),
            'mapped_invoice_count' => (clone $invoices)->where('branch_id', $branch->id)->count(),
            'customer_count' => DB::query()->fromSub($customerIds, 'location_customers')->count(),
            'mapped_customer_count' => Customer::withoutGlobalScopes()->whereIn('id', $customerIds)->where('branch_id', $branch->id)->count(),
        ];
    }
}

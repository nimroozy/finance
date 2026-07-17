<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Lead;
use App\Models\Crm\Quotation;
use App\Services\Crm\QuotationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class QuotationController extends Controller
{
    public function __construct(private QuotationService $quotations) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('crm.quotations.view'), 403);

        $query = Quotation::query()
            ->when($request->filled('lead_id'), fn ($q) => $q->where('lead_id', $request->integer('lead_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('id');

        return ApiResponse::paginated($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('crm.quotations.create'), 403);

        $data = $request->validate([
            'lead_id' => ['required', 'integer', 'exists:crm_leads,id'],
            'monthly_package' => ['nullable', 'numeric'],
            'installation_fee' => ['nullable', 'numeric'],
            'discount' => ['nullable', 'numeric'],
            'equipment_json' => ['nullable', 'array'],
            'taxes' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
            'valid_until' => ['nullable', 'date'],
        ]);

        $lead = Lead::query()->findOrFail($data['lead_id']);

        try {
            $quote = $this->quotations->create($lead, $data, Auth::user());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($quote, null, 201);
    }

    public function send(int $id): JsonResponse
    {
        abort_unless(Auth::user()->can('crm.quotations.create'), 403);

        $quote = Quotation::query()->findOrFail($id);

        try {
            return ApiResponse::success($this->quotations->send($quote, Auth::user()));
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }
    }

    public function accept(int $id): JsonResponse
    {
        abort_unless(Auth::user()->can('crm.quotations.approve'), 403);

        $quote = Quotation::query()->findOrFail($id);

        try {
            return ApiResponse::success($this->quotations->accept($quote, Auth::user()));
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        abort_unless(Auth::user()->can('crm.quotations.approve') || Auth::user()->can('crm.quotations.create'), 403);

        $quote = Quotation::query()->findOrFail($id);
        $data = $request->validate(['reason' => ['nullable', 'string']]);

        try {
            return ApiResponse::success($this->quotations->reject($quote, Auth::user(), $data['reason'] ?? null));
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }
    }
}

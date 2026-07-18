<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tickets\SlaPolicy;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SlaPolicyController extends Controller
{
    public function index(): JsonResponse
    {
        abort_unless(auth()->user()->can('sla.manage') || auth()->user()->can('tickets.view'), 403);

        return ApiResponse::success(SlaPolicy::query()->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->can('sla.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'ticket_type_code' => ['nullable', 'string', 'max:64'],
            'priority' => ['nullable', 'string', 'max:32'],
            'customer_tier' => ['nullable', 'string', 'max:32'],
            'business_hours' => ['nullable', 'array'],
            'response_minutes' => ['required', 'integer', 'min:1'],
            'resolution_minutes' => ['required', 'integer', 'min:1'],
            'escalation_intervals' => ['nullable', 'array'],
            'pause_statuses' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return ApiResponse::success(SlaPolicy::query()->create($data), null, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        abort_unless(auth()->user()->can('sla.manage'), 403);

        $policy = SlaPolicy::query()->findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes', 'string'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'ticket_type_code' => ['nullable', 'string', 'max:64'],
            'priority' => ['nullable', 'string', 'max:32'],
            'customer_tier' => ['nullable', 'string', 'max:32'],
            'business_hours' => ['nullable', 'array'],
            'response_minutes' => ['sometimes', 'integer', 'min:1'],
            'resolution_minutes' => ['sometimes', 'integer', 'min:1'],
            'escalation_intervals' => ['nullable', 'array'],
            'pause_statuses' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $policy->fill($data)->save();

        return ApiResponse::success($policy->fresh());
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tickets\EscalationRule;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EscalationRuleController extends Controller
{
    public function index(): JsonResponse
    {
        abort_unless(auth()->user()->can('escalations.manage') || auth()->user()->can('sla.manage'), 403);

        return ApiResponse::success(EscalationRule::query()->orderBy('sort_order')->get());
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->can('escalations.manage') || auth()->user()->can('sla.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'ticket_type_code' => ['nullable', 'string', 'max:64'],
            'priority' => ['nullable', 'string', 'max:32'],
            'department_code' => ['nullable', 'string', 'max:64'],
            'trigger_type' => ['nullable', 'string', 'max:32'],
            'trigger_after_minutes' => ['nullable', 'integer', 'min:0'],
            'from_status' => ['nullable', 'string', 'max:32'],
            'escalate_to_role' => ['nullable', 'string', 'max:64'],
            'escalate_to_department_code' => ['nullable', 'string', 'max:64'],
            'escalate_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'notify_whatsapp' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'meta' => ['nullable', 'array'],
        ]);

        return ApiResponse::success(EscalationRule::query()->create($data), null, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        abort_unless(auth()->user()->can('escalations.manage') || auth()->user()->can('sla.manage'), 403);

        $rule = EscalationRule::query()->findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes', 'string'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'ticket_type_code' => ['nullable', 'string', 'max:64'],
            'priority' => ['nullable', 'string', 'max:32'],
            'department_code' => ['nullable', 'string', 'max:64'],
            'trigger_type' => ['nullable', 'string', 'max:32'],
            'trigger_after_minutes' => ['nullable', 'integer', 'min:0'],
            'from_status' => ['nullable', 'string', 'max:32'],
            'escalate_to_role' => ['nullable', 'string', 'max:64'],
            'escalate_to_department_code' => ['nullable', 'string', 'max:64'],
            'escalate_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'notify_whatsapp' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'meta' => ['nullable', 'array'],
        ]);

        $rule->fill($data)->save();

        return ApiResponse::success($rule->fresh());
    }
}

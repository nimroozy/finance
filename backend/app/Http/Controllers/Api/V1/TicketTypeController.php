<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tickets\TicketType;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketTypeController extends Controller
{
    public function index(): JsonResponse
    {
        abort_unless(auth()->user()->can('tickets.view') || auth()->user()->can('ticket_types.manage'), 403);

        return ApiResponse::success(
            TicketType::query()->where('is_active', true)->orderBy('code')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->can('ticket_types.manage'), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'unique:ticket_types,code'],
            'name_en' => ['required', 'string'],
            'name_fa' => ['nullable', 'string'],
            'default_department_code' => ['nullable', 'string', 'max:64'],
            'default_priority' => ['nullable', 'string', 'max:32'],
            'sla_policy_id' => ['nullable', 'integer', 'exists:sla_policies,id'],
            'requires_customer' => ['nullable', 'boolean'],
            'requires_site' => ['nullable', 'boolean'],
            'expects_field_visit' => ['nullable', 'boolean'],
            'requires_finance_review' => ['nullable', 'boolean'],
            'requires_radius_review' => ['nullable', 'boolean'],
            'required_fields' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $type = TicketType::query()->create($data);

        return ApiResponse::success($type, null, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        abort_unless(auth()->user()->can('ticket_types.manage'), 403);

        $type = TicketType::query()->findOrFail($id);
        $data = $request->validate([
            'name_en' => ['sometimes', 'string'],
            'name_fa' => ['nullable', 'string'],
            'default_department_code' => ['nullable', 'string', 'max:64'],
            'default_priority' => ['nullable', 'string', 'max:32'],
            'sla_policy_id' => ['nullable', 'integer', 'exists:sla_policies,id'],
            'requires_customer' => ['nullable', 'boolean'],
            'requires_site' => ['nullable', 'boolean'],
            'expects_field_visit' => ['nullable', 'boolean'],
            'requires_finance_review' => ['nullable', 'boolean'],
            'requires_radius_review' => ['nullable', 'boolean'],
            'required_fields' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $type->fill($data)->save();

        return ApiResponse::success($type->fresh());
    }
}

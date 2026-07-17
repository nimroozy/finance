<?php

namespace App\Http\Controllers\Api\V1\Services;

use App\Http\Controllers\Controller;
use App\Models\Services\ServiceAccessTechnology;
use App\Models\Services\ServiceSlaTemplate;
use App\Models\Services\ServiceType;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ServiceCatalogController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function types(): JsonResponse
    {
        abort_unless(Auth::user()?->can('services.view'), 403);

        return ApiResponse::success(ServiceType::query()->where('is_active', true)->orderBy('code')->get());
    }

    public function technologies(): JsonResponse
    {
        abort_unless(Auth::user()?->can('services.view'), 403);

        return ApiResponse::success(ServiceAccessTechnology::query()->where('is_active', true)->orderBy('code')->get());
    }

    public function slaTemplates(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('services.view') || Auth::user()?->can('services.sla.manage'), 403);

        $query = ServiceSlaTemplate::query()->orderBy('name');
        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        return ApiResponse::success($query->get());
    }

    public function storeSlaTemplate(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('services.sla.manage'), 403);
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:64', 'unique:service_sla_templates,code'],
            'name' => ['required', 'string', 'max:255'],
            'response_minutes' => ['nullable', 'integer', 'min:1'],
            'resolution_minutes' => ['nullable', 'integer', 'min:1'],
            'availability_target' => ['nullable', 'numeric'],
            'support_hours' => ['nullable', 'array'],
            'escalation_rules' => ['nullable', 'array'],
            'priority_matrix' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $template = ServiceSlaTemplate::query()->create([
            'code' => $data['code'] ?? null,
            'name' => $data['name'],
            'response_minutes' => $data['response_minutes'] ?? 60,
            'resolution_minutes' => $data['resolution_minutes'] ?? 240,
            'availability_target' => $data['availability_target'] ?? null,
            'support_hours' => $data['support_hours'] ?? null,
            'escalation_rules' => $data['escalation_rules'] ?? null,
            'priority_matrix' => $data['priority_matrix'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->audit->log('service_sla_template.created', $template, null, $template->toArray());

        return ApiResponse::success($template, null, 201);
    }

    public function updateSlaTemplate(Request $request, int $id): JsonResponse
    {
        abort_unless(Auth::user()?->can('services.sla.manage'), 403);
        $template = ServiceSlaTemplate::query()->findOrFail($id);
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:64', 'unique:service_sla_templates,code,'.$id],
            'name' => ['sometimes', 'string', 'max:255'],
            'response_minutes' => ['nullable', 'integer', 'min:1'],
            'resolution_minutes' => ['nullable', 'integer', 'min:1'],
            'availability_target' => ['nullable', 'numeric'],
            'support_hours' => ['nullable', 'array'],
            'escalation_rules' => ['nullable', 'array'],
            'priority_matrix' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $template->fill($data);
        $template->save();
        $this->audit->log('service_sla_template.updated', $template, null, $template->toArray());

        return ApiResponse::success($template->fresh());
    }

    public function deactivateSlaTemplate(int $id): JsonResponse
    {
        abort_unless(Auth::user()?->can('services.sla.manage'), 403);
        $template = ServiceSlaTemplate::query()->findOrFail($id);
        $template->is_active = false;
        $template->save();
        $this->audit->log('service_sla_template.deactivated', $template, null, $template->toArray());

        return ApiResponse::success($template->fresh());
    }
}

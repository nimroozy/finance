<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tickets\TaskTemplate;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        abort_unless(auth()->user()->can('task_templates.manage') || auth()->user()->can('tasks.view'), 403);

        return ApiResponse::success(TaskTemplate::query()->orderBy('code')->get());
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->can('task_templates.manage'), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'unique:task_templates,code'],
            'name' => ['required', 'string'],
            'workflow_type' => ['nullable', 'string', 'max:32'],
            'steps' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return ApiResponse::success(TaskTemplate::query()->create($data), null, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        abort_unless(auth()->user()->can('task_templates.manage'), 403);

        $template = TaskTemplate::query()->findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes', 'string'],
            'workflow_type' => ['nullable', 'string', 'max:32'],
            'steps' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $template->fill($data)->save();

        return ApiResponse::success($template->fresh());
    }
}

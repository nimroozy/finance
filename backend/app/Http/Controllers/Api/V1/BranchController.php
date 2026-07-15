<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Branch\StoreBranchRequest;
use App\Http\Requests\Api\V1\Branch\UpdateBranchRequest;
use App\Models\Branch;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function __construct(protected AuditLogger $auditLogger) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Branch::class);

        $branches = Branch::query()
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = '%'.$request->string('search').'%';
                $q->where(function ($inner) use ($search) {
                    $inner->where('code', 'like', $search)
                        ->orWhere('name_en', 'like', $search)
                        ->orWhere('name_fa', 'like', $search);
                });
            })
            ->orderBy('code')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::success(
            $branches->items(),
            [
                'current_page' => $branches->currentPage(),
                'last_page' => $branches->lastPage(),
                'per_page' => $branches->perPage(),
                'total' => $branches->total(),
            ]
        );
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        $this->authorize('create', Branch::class);

        $branch = Branch::query()->create($request->validated());

        $this->auditLogger->log('branch.created', $branch, null, $branch->toArray());

        return ApiResponse::success($branch, null, 201);
    }

    public function show(int $branch): JsonResponse
    {
        $model = Branch::query()->find($branch);

        if (! $model) {
            // Distinguish isolation (branch exists but out of scope) from missing.
            $existsOutsideScope = Branch::withoutGlobalScopes()
                ->whereKey($branch)
                ->exists();

            if ($existsOutsideScope) {
                throw new AuthorizationException('You are not authorized to access this branch.');
            }

            return ApiResponse::error('Resource not found.', [], 404);
        }

        $this->authorize('view', $model);

        return ApiResponse::success($model);
    }

    public function update(UpdateBranchRequest $request, int $branch): JsonResponse
    {
        $model = Branch::query()->find($branch);

        if (! $model) {
            $existsOutsideScope = Branch::withoutGlobalScopes()
                ->whereKey($branch)
                ->exists();

            if ($existsOutsideScope) {
                throw new AuthorizationException('You are not authorized to access this branch.');
            }

            return ApiResponse::error('Resource not found.', [], 404);
        }

        $this->authorize('update', $model);

        $old = $model->toArray();
        $model->fill($request->validated())->save();

        $this->auditLogger->log('branch.updated', $model, $old, $model->fresh()->toArray());

        return ApiResponse::success($model->fresh());
    }
}

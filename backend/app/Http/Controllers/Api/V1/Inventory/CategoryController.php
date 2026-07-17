<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\ProductCategory;
use App\Services\Inventory\CategoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(private CategoryService $categories) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ProductCategory::class);
        $q = ProductCategory::query()->orderBy('code');
        if ($request->filled('is_active')) {
            $q->where('is_active', $request->boolean('is_active'));
        }

        return ApiResponse::success($q->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ProductCategory::class);
        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:inventory_product_categories,id'],
            'code' => ['required', 'string', 'max:64', 'unique:inventory_product_categories,code'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_fa' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return ApiResponse::success($this->categories->create($data, $request->user()), null, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $cat = ProductCategory::query()->findOrFail($id);
        $this->authorize('update', $cat);
        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:inventory_product_categories,id'],
            'code' => ['sometimes', 'string', 'max:64', 'unique:inventory_product_categories,code,'.$id],
            'name_en' => ['sometimes', 'string', 'max:255'],
            'name_fa' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return ApiResponse::success($this->categories->update($cat, $data, $request->user()));
    }
}
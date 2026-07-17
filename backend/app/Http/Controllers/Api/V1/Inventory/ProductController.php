<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Product;
use App\Services\Inventory\ProductService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ProductController extends Controller
{
    public function __construct(private ProductService $products) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);
        $q = Product::query()->with('category:id,code,name_en');
        $q->when($request->filled('category_id'), fn ($qq) => $qq->where('category_id', $request->integer('category_id')))
            ->when($request->filled('tracking_mode'), fn ($qq) => $qq->where('tracking_mode', $request->string('tracking_mode')))
            ->when($request->filled('is_active'), fn ($qq) => $qq->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('search'), function ($qq) use ($request) {
                $t = '%'.$request->string('search').'%';
                $qq->where(fn ($w) => $w->where('code', 'like', $t)->orWhere('sku', 'like', $t)->orWhere('name_en', 'like', $t)->orWhere('barcode', 'like', $t));
            });
        $page = $q->orderByDesc('id')->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated($page);
    }

    public function show(int $id): JsonResponse
    {
        $product = Product::with('category')->findOrFail($id);
        $this->authorize('view', $product);

        return ApiResponse::success($product);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Product::class);
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:64', 'unique:inventory_products,code'],
            'sku' => ['nullable', 'string', 'max:64'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_fa' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:inventory_product_categories,id'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'uom' => ['nullable', 'string', 'max:32'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'tracking_mode' => ['nullable', Rule::in(Product::TRACKING_MODES)],
            'is_purchase' => ['nullable', 'boolean'],
            'is_sales' => ['nullable', 'boolean'],
            'is_installable' => ['nullable', 'boolean'],
            'is_fixed_asset_eligible' => ['nullable', 'boolean'],
            'is_consumable' => ['nullable', 'boolean'],
            'is_returnable' => ['nullable', 'boolean'],
            'is_company_owned' => ['nullable', 'boolean'],
            'purchase_price' => ['nullable', 'numeric'],
            'sales_price' => ['nullable', 'numeric'],
            'min_stock' => ['nullable', 'numeric'],
            'reorder_qty' => ['nullable', 'numeric'],
            'warranty_months' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);
        try {
            $product = $this->products->create($data, $request->user());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($product, null, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $product = Product::query()->findOrFail($id);
        $this->authorize('update', $product);
        $data = $request->validate([
            'sku' => ['nullable', 'string', 'max:64'],
            'name_en' => ['sometimes', 'string', 'max:255'],
            'name_fa' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:inventory_product_categories,id'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'uom' => ['nullable', 'string', 'max:32'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'tracking_mode' => ['nullable', Rule::in(Product::TRACKING_MODES)],
            'is_purchase' => ['nullable', 'boolean'],
            'is_sales' => ['nullable', 'boolean'],
            'is_installable' => ['nullable', 'boolean'],
            'is_fixed_asset_eligible' => ['nullable', 'boolean'],
            'is_consumable' => ['nullable', 'boolean'],
            'is_returnable' => ['nullable', 'boolean'],
            'is_company_owned' => ['nullable', 'boolean'],
            'purchase_price' => ['nullable', 'numeric'],
            'sales_price' => ['nullable', 'numeric'],
            'min_stock' => ['nullable', 'numeric'],
            'reorder_qty' => ['nullable', 'numeric'],
            'warranty_months' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        return ApiResponse::success($this->products->update($product, $data, $request->user()));
    }
}
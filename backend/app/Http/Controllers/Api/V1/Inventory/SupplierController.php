<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Supplier;
use App\Services\Inventory\SupplierService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function __construct(private SupplierService $suppliers) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Supplier::class);
        $q = Supplier::query()->when($request->filled('search'), function ($qq) use ($request) {
            $t = '%'.$request->string('search').'%';
            $qq->where(fn ($w) => $w->where('code', 'like', $t)->orWhere('name', 'like', $t));
        });
        $page = $q->orderBy('name')->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated($page);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Supplier::class);
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:64', 'unique:inventory_suppliers,code'],
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'whatsapp' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email'],
            'address' => ['nullable', 'string'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'lead_time_days' => ['nullable', 'integer'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return ApiResponse::success($this->suppliers->create($data, $request->user()), null, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $supplier = Supplier::query()->findOrFail($id);
        $this->authorize('update', $supplier);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'whatsapp' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email'],
            'address' => ['nullable', 'string'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'lead_time_days' => ['nullable', 'integer'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return ApiResponse::success($this->suppliers->update($supplier, $data, $request->user()));
    }
}
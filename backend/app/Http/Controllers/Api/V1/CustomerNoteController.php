<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomerNote;
use App\Services\Notes\CustomerNoteService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerNoteController extends Controller
{
    public function __construct(protected CustomerNoteService $notes) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CustomerNote::class);

        $request->validate(['customer_id' => ['nullable', 'integer', 'exists:customers,id']]);

        $page = CustomerNote::query()
            ->with('author:id,name')
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::success($page->items(), [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CustomerNote::class);

        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'assignment_id' => ['nullable', 'integer', 'exists:customer_assignments,id'],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        return ApiResponse::success($this->notes->create($data, Auth::user()), null, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $note = CustomerNote::findOrFail($id);
        $this->authorize('update', $note);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
        ]);

        return ApiResponse::success($this->notes->update($note, $data['body'], Auth::user()));
    }
}

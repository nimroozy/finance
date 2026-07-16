<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Invoices\InvoiceBalanceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceBalanceService $balances) {}

    public function index(Request $request): JsonResponse
    {
        $invoices = Invoice::query()
            ->with(['customer:id,contact_name,customer_number', 'branch:id,code,name_en'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = '%'.$request->string('search').'%';
                $q->where(function ($inner) use ($search) {
                    $inner->where('invoice_number', 'like', $search)
                        ->orWhereHas('customer', function ($cq) use ($search) {
                            $cq->withoutGlobalScopes()
                                ->where('contact_name', 'like', $search);
                        });
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->orderByDesc('invoice_date')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::success($this->balances->decorateMany($invoices->items()), [
            'current_page' => $invoices->currentPage(),
            'last_page' => $invoices->lastPage(),
            'per_page' => $invoices->perPage(),
            'total' => $invoices->total(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $invoice = Invoice::query()
            ->with(['customer', 'branch', 'customFields'])
            ->findOrFail($id);

        return ApiResponse::success($this->balances->decorateOne($invoice));
    }
}

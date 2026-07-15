<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DebtorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $debtors = Customer::query()
            ->with('branch:id,code,name_en')
            ->where('outstanding_receivable', '>', 0)
            ->where('is_unmapped', false)
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = '%'.$request->string('search').'%';
                $q->where(function ($inner) use ($search) {
                    $inner->where('contact_name', 'like', $search)
                        ->orWhere('company_name', 'like', $search)
                        ->orWhere('customer_number', 'like', $search);
                });
            })
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->orderByDesc('outstanding_receivable')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::success($debtors->items(), [
            'current_page' => $debtors->currentPage(),
            'last_page' => $debtors->lastPage(),
            'per_page' => $debtors->perPage(),
            'total' => $debtors->total(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filename = 'debtors-'.now()->format('Ymd-His').'.csv';

        $query = Customer::query()
            ->with('branch:id,code,name_en')
            ->where('outstanding_receivable', '>', 0)
            ->where('is_unmapped', false)
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->orderByDesc('outstanding_receivable');

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'customer_number',
                'contact_name',
                'company_name',
                'branch_code',
                'currency',
                'outstanding_receivable',
                'phone',
                'email',
                'status',
            ]);

            $query->chunk(200, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->customer_number,
                        $row->contact_name,
                        $row->company_name,
                        $row->branch?->code,
                        $row->currency,
                        $row->outstanding_receivable,
                        $row->phone,
                        $row->email,
                        $row->status,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}

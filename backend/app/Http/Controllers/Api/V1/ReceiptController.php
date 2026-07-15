<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use App\Services\Receipts\ReceiptService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReceiptController extends Controller
{
    public function __construct(protected ReceiptService $receipts) {}

    public function show(string $uuid): JsonResponse
    {
        $receipt = Receipt::with(['payment.customer', 'payment.method', 'payment.allocations.invoice', 'branch'])
            ->where('uuid', $uuid)
            ->firstOrFail();
        $this->authorize('view', $receipt);

        return ApiResponse::success($receipt);
    }

    public function pdf(string $uuid): BinaryFileResponse|JsonResponse
    {
        $receipt = Receipt::where('uuid', $uuid)->firstOrFail();
        $this->authorize('view', $receipt);

        $path = $this->receipts->pdfAbsolutePath($receipt);
        if (! $path) {
            return ApiResponse::error('Receipt PDF is not available.', [], 404);
        }

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$receipt->receipt_number.'.pdf"',
        ]);
    }

    public function printLog(Request $request, string $uuid): JsonResponse
    {
        $receipt = Receipt::where('uuid', $uuid)->firstOrFail();
        $this->authorize('print', $receipt);

        $data = $request->validate([
            'channel' => ['nullable', 'string', 'max:50'],
            'device_info' => ['nullable', 'string', 'max:500'],
        ]);

        $log = $this->receipts->logPrint($receipt, Auth::user(), $data);

        return ApiResponse::success($log, null, 201);
    }

    public function verify(string $token): JsonResponse
    {
        $receipt = $this->receipts->findByVerificationToken($token);
        if (! $receipt) {
            return ApiResponse::error('Receipt not found.', [], 404);
        }

        return ApiResponse::success([
            'receipt_number' => $receipt->receipt_number,
            'status' => $receipt->status,
            'issued_at' => $receipt->issued_at,
            'amount' => $receipt->payment?->amount,
            'currency' => $receipt->payment?->currency,
            'customer' => $receipt->payment?->customer?->only(['contact_name', 'customer_number']),
            'branch' => $receipt->branch?->only(['code', 'name_en', 'name_fa']),
            'payment_reference' => $receipt->payment?->payment_reference,
        ]);
    }
}

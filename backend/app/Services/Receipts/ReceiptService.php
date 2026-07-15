<?php

namespace App\Services\Receipts;

use App\Jobs\GenerateReceiptPdfJob;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\ReceiptPrintLog;
use App\Models\User;
use App\Services\AuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReceiptService
{
    public function __construct(
        protected ReceiptNumberService $numbers,
        protected AuditLogger $audit,
    ) {}

    public function issueForPayment(Payment $payment, ?string $language = null): Receipt
    {
        if ($payment->receipt_id) {
            return Receipt::withoutGlobalScopes()->findOrFail($payment->receipt_id);
        }

        $language = $language ?: (string) config('payments.receipt.default_language', 'en');
        $token = Str::random(48);
        $number = $this->numbers->nextNumber((int) $payment->branch_id);

        $receipt = Receipt::create([
            'uuid' => (string) Str::uuid(),
            'receipt_number' => $number,
            'payment_id' => $payment->id,
            'branch_id' => $payment->branch_id,
            'verification_token' => $token,
            'status' => Receipt::STATUS_ISSUED,
            'language' => $language,
            'template_version' => (string) config('payments.receipt.template_version', '1.0'),
            'issued_at' => now(),
        ]);

        $payment->receipt_id = $receipt->id;
        $payment->save();

        $this->storeHtml($receipt->fresh(['payment.customer', 'payment.method', 'payment.allocations.invoice', 'branch']));

        GenerateReceiptPdfJob::dispatch($receipt->id);

        $this->audit->log('receipt.issued', $receipt, null, $receipt->toArray(), $receipt->branch_id);

        return $receipt->fresh();
    }

    public function renderHtml(Receipt $receipt, string $template = 'pdf'): string
    {
        $receipt->loadMissing(['payment.customer', 'payment.method', 'payment.allocations.invoice', 'payment.collector.user', 'branch']);

        $view = match ($template) {
            'thermal-58' => 'receipts.thermal-58',
            'thermal-80' => 'receipts.thermal-80',
            default => 'receipts.pdf',
        };

        return view($view, [
            'receipt' => $receipt,
            'payment' => $receipt->payment,
        ])->render();
    }

    public function storeHtml(Receipt $receipt): string
    {
        $html = $this->renderHtml($receipt, 'pdf');
        $relative = "receipts/{$receipt->uuid}.html";
        Storage::disk('local')->put($relative, $html);
        $receipt->html_path = $relative;
        $receipt->save();

        return $relative;
    }

    public function generatePdf(Receipt $receipt): string
    {
        $html = $this->renderHtml($receipt, 'pdf');
        $relative = "receipts/{$receipt->uuid}.pdf";

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4')
            ->setOption('defaultFont', 'DejaVu Sans');

        Storage::disk('local')->put($relative, $pdf->output());

        $receipt->pdf_path = $relative;
        $receipt->save();

        return $relative;
    }

    public function pdfAbsolutePath(Receipt $receipt): ?string
    {
        if (! $receipt->pdf_path) {
            $this->generatePdf($receipt);
            $receipt->refresh();
        }

        if (! $receipt->pdf_path || ! Storage::disk('local')->exists($receipt->pdf_path)) {
            return null;
        }

        return Storage::disk('local')->path($receipt->pdf_path);
    }

    public function logPrint(Receipt $receipt, ?User $user = null, array $meta = []): ReceiptPrintLog
    {
        $log = ReceiptPrintLog::create([
            'receipt_id' => $receipt->id,
            'printed_by' => $user?->id,
            'channel' => $meta['channel'] ?? 'api',
            'device_info' => $meta['device_info'] ?? null,
            'ip_address' => $meta['ip_address'] ?? request()->ip(),
            'meta' => $meta,
            'created_at' => now(),
        ]);

        $receipt->increment('reprint_count');

        $this->audit->log('receipt.print', $receipt, null, ['reprint_count' => $receipt->reprint_count], $receipt->branch_id);

        return $log;
    }

    public function findByVerificationToken(string $token): ?Receipt
    {
        return Receipt::withoutGlobalScopes()
            ->with(['payment.customer', 'payment.method', 'branch'])
            ->where('verification_token', $token)
            ->first();
    }

    public function void(Receipt $receipt): void
    {
        $receipt->status = Receipt::STATUS_VOIDED;
        $receipt->voided_at = now();
        $receipt->save();
    }
}

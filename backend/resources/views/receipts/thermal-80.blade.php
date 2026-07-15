<!DOCTYPE html>
<html lang="{{ $receipt->language ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $receipt->receipt_number }}</title>
    <style>
        body { font-family: DejaVu Sans, monospace; font-size: 12px; width: 80mm; margin: 0; padding: 6px; }
        .center { text-align: center; }
        .line { border-top: 1px dashed #000; margin: 8px 0; }
    </style>
</head>
<body>
    <div class="center"><strong>{{ $receipt->branch->name_en ?? '' }}</strong></div>
    <div class="center">{{ $receipt->branch->name_fa ?? '' }}</div>
    <div class="center">{{ $receipt->receipt_number }}</div>
    <div class="line"></div>
    <div>Customer: {{ $payment->customer->contact_name ?? '' }}</div>
    <div>Method: {{ $payment->method->name_en ?? '' }}</div>
    <div>Date: {{ optional($receipt->issued_at)->format('Y-m-d H:i') }}</div>
    <div class="line"></div>
    @foreach($payment->allocations as $allocation)
        <div>{{ $allocation->invoice->invoice_number ?? $allocation->invoice_id }} — {{ $allocation->amount }} {{ $payment->currency }}</div>
    @endforeach
    <div class="line"></div>
    <div class="center"><strong>TOTAL {{ $payment->amount }} {{ $payment->currency }}</strong></div>
    <div class="center" style="margin-top:10px;font-size:10px;">Ref: {{ $payment->payment_reference }}</div>
</body>
</html>

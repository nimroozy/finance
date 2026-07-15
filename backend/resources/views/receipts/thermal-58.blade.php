<!DOCTYPE html>
<html lang="{{ $receipt->language ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $receipt->receipt_number }}</title>
    <style>
        body { font-family: DejaVu Sans, monospace; font-size: 11px; width: 58mm; margin: 0; padding: 4px; }
        .center { text-align: center; }
        .line { border-top: 1px dashed #000; margin: 6px 0; }
        .row { display: flex; justify-content: space-between; }
    </style>
</head>
<body>
    <div class="center"><strong>{{ $receipt->branch->name_en ?? '' }}</strong></div>
    <div class="center">{{ $receipt->receipt_number }}</div>
    <div class="line"></div>
    <div>{{ $payment->customer->contact_name ?? '' }}</div>
    <div>{{ optional($receipt->issued_at)->format('Y-m-d H:i') }}</div>
    <div class="line"></div>
    @foreach($payment->allocations as $allocation)
        <div>{{ $allocation->invoice->invoice_number ?? $allocation->invoice_id }}: {{ $allocation->amount }}</div>
    @endforeach
    <div class="line"></div>
    <div><strong>TOTAL {{ $payment->amount }} {{ $payment->currency }}</strong></div>
    <div class="center" style="margin-top:8px;font-size:9px;">{{ $payment->payment_reference }}</div>
</body>
</html>

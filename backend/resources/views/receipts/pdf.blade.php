<!DOCTYPE html>
<html lang="{{ $receipt->language ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $receipt->receipt_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        .meta { margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
        th { background: #f3f3f3; }
        .total { font-weight: bold; font-size: 14px; margin-top: 12px; }
        .verify { margin-top: 24px; font-size: 10px; color: #555; }
    </style>
</head>
<body>
    <h1>{{ $receipt->branch->name_en ?? 'Receipt' }}</h1>
    <div class="meta">
        <div><strong>Receipt:</strong> {{ $receipt->receipt_number }}</div>
        <div><strong>Payment Ref:</strong> {{ $payment->payment_reference }}</div>
        <div><strong>Date:</strong> {{ optional($receipt->issued_at)->format('Y-m-d H:i') }}</div>
        <div><strong>Customer:</strong> {{ $payment->customer->contact_name ?? '' }}</div>
        <div><strong>Method:</strong> {{ $payment->method->name_en ?? '' }}</div>
        <div><strong>Collector:</strong> {{ $payment->collector->user->name ?? '' }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Invoice</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payment->allocations as $allocation)
                <tr>
                    <td>{{ $allocation->invoice->invoice_number ?? $allocation->invoice_id }}</td>
                    <td>{{ $allocation->amount }} {{ $payment->currency }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total">Total: {{ $payment->amount }} {{ $payment->currency }}</div>
    <div class="verify">Verification token: {{ $receipt->verification_token }}</div>
</body>
</html>

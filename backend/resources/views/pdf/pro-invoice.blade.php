<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: right; }
        th { background: #f5f5f5; }
    </style>
</head>
<body>
    <h1>پیش‌فاکتور / فاکتور</h1>
    <p>شماره: {{ $invoice->number ?? $invoice->id }} — وضعیت: {{ $invoice->status ?? '—' }}</p>
    @if($invoice->total !== null)
        <p><strong>جمع:</strong> {{ $invoice->total }}</p>
    @endif
    @if($invoice->discount)
        <p><strong>تخفیف:</strong> {{ $invoice->discount }}</p>
    @endif
    @if($invoice->notes)
        <p><strong>یادداشت:</strong> {{ $invoice->notes }}</p>
    @endif
    @php
        $items = is_array($invoice->items) ? $invoice->items : [];
    @endphp
    @if(count($items))
        <table>
            <thead>
                <tr>
                    <th>شرح</th>
                    <th>تعداد</th>
                    <th>مبلغ</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $line)
                    <tr>
                        <td>{{ is_array($line) ? ($line['title'] ?? $line['name'] ?? json_encode($line)) : $line }}</td>
                        <td>{{ is_array($line) ? ($line['qty'] ?? $line['quantity'] ?? '—') : '—' }}</td>
                        <td>{{ is_array($line) ? ($line['price'] ?? $line['amount'] ?? '—') : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>

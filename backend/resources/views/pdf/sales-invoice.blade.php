<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin-bottom: 8px; }
        .meta p { margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: right; }
        th { background: #f5f5f5; }
        .totals { margin-top: 16px; }
    </style>
</head>
<body>
    <h1>پیش‌فاکتور</h1>
    <div class="meta">
        <p><strong>شماره:</strong> {{ $invoice->invoice_number ?: $invoice->number }}</p>
        <p><strong>مشتری:</strong> {{ $invoice->customer_name }}</p>
        <p><strong>پروژه:</strong> {{ $invoice->project_title ?: '—' }}</p>
        <p><strong>تاریخ صدور:</strong> {{ $invoice->issue_date?->format('Y-m-d') ?: '—' }}</p>
        <p><strong>وضعیت:</strong> {{ $invoice->status ?: '—' }}</p>
    </div>

    @php $items = is_array($invoice->items) ? $invoice->items : []; @endphp
    @if(count($items))
        <table>
            <thead>
                <tr>
                    <th>عنوان</th>
                    <th>شرح</th>
                    <th>قیمت</th>
                    <th>تخفیف</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $line)
                    <tr>
                        <td>{{ is_array($line) ? ($line['title'] ?? '') : $line }}</td>
                        <td>{{ is_array($line) ? ($line['desc'] ?? '') : '' }}</td>
                        <td>{{ is_array($line) ? ($line['price'] ?? 0) : '' }}</td>
                        <td>{{ is_array($line) ? ($line['discount'] ?? 0) : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="totals">
        <p><strong>جمع کل:</strong> {{ $invoice->subtotal }}</p>
        <p><strong>تخفیف:</strong> {{ $invoice->discount }}</p>
        <p><strong>مبلغ نهایی:</strong> {{ $invoice->total }}</p>
    </div>

    @if($invoice->payment_method)
        <p><strong>نحوه پرداخت:</strong> {{ $invoice->payment_method }}</p>
    @endif
    @if($invoice->notes)
        <p><strong>یادداشت:</strong> {{ $invoice->notes }}</p>
    @endif
</body>
</html>

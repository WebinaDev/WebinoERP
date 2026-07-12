<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: right; }
        th { background: #f5f5f5; }
        .muted { color: #666; font-size: 11px; }
    </style>
</head>
<body>
    <h1>{{ $contract->title ?? 'قرارداد' }}</h1>
    <p class="muted">شناسه: {{ $contract->id }} — وضعیت: {{ $contract->status ?? '—' }}</p>
    @if($contract->amount !== null)
        <p><strong>مبلغ:</strong> {{ $contract->amount }}</p>
    @endif
    @if($contract->signed_at)
        <p><strong>تاریخ امضا:</strong> {{ $contract->signed_at }}</p>
    @endif
    @if($contract->project)
        <p><strong>پروژه:</strong> {{ $contract->project->name ?? '—' }}</p>
    @endif
    @if($contract->installments && $contract->installments->count())
        <h2 style="font-size:14px;margin-top:16px;">اقساط</h2>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>مبلغ</th>
                    <th>سررسید</th>
                    <th>پرداخت</th>
                </tr>
            </thead>
            <tbody>
                @foreach($contract->installments as $i => $row)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $row->amount }}</td>
                        <td>{{ $row->due_date }}</td>
                        <td>{{ $row->paid_at ? 'پرداخت شده' : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @elseif(is_array($contract->installments_data) && count($contract->installments_data))
        <h2 style="font-size:14px;margin-top:16px;">اقساط (ذخیره‌شده)</h2>
        <pre style="white-space:pre-wrap;font-size:11px;">{{ json_encode($contract->installments_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
    @endif
    @if($contract->notes ?? false)
        <p style="margin-top:12px;"><strong>یادداشت:</strong> {{ $contract->notes }}</p>
    @endif
</body>
</html>

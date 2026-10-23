<!doctype html>
<html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>تمدید لایسنس {{ $info['domain'] }}</title>
<style>
body{font-family:Vazirmatn,Tahoma,sans-serif;background:#f5f6fa;margin:0;color:#222}
.wrap{max-width:720px;margin:40px auto;padding:0 16px}
.card{background:#fff;border-radius:16px;padding:24px;box-shadow:0 8px 30px rgba(0,0,0,.06);margin-bottom:16px}
.badge{display:inline-block;padding:2px 10px;border-radius:999px;font-size:13px;background:#eef}
.badge.expired,.badge.suspended{background:#fde8e8;color:#b42318}.badge.expiring{background:#fff4e0;color:#b54708}.badge.active{background:#e6f6ec;color:#067647}
.opts{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}
.opt{border:1px solid #e4e6ef;border-radius:12px;padding:16px;text-align:center;text-decoration:none;color:inherit;display:block}
.opt:hover{border-color:#4f46e5}.price{font-size:20px;font-weight:700;margin:8px 0}.old{text-decoration:line-through;color:#999;font-size:13px}
.btn{display:inline-block;margin-top:8px;background:#4f46e5;color:#fff;border-radius:8px;padding:8px 16px}
.msg{padding:12px;border-radius:8px;background:#e6f6ec;color:#067647}
</style></head><body><div class="wrap">
<div class="card">
  <h1 style="font-size:22px;margin-top:0">تمدید لایسنس {{ $info['domain'] }}</h1>
  @php($labels = ['active' => 'فعال', 'expiring' => 'رو به انقضا', 'expired' => 'منقضی', 'suspended' => 'تعلیق‌شده', 'cancelled' => 'لغوشده'])
  <p>وضعیت: <span class="badge {{ $info['status'] }}">{{ $labels[$info['status']] ?? $info['status'] }}</span>
  @if($info['plan']['name'] || $info['plan']['code']) — پلن: {{ $info['plan']['name'] ?: $info['plan']['code'] }} @endif</p>
  @if($info['expires_at'])<p>تاریخ انقضا: {{ \Illuminate\Support\Carbon::parse($info['expires_at'])->format('Y-m-d') }}</p>@endif
  @if($paid)<p class="msg">پرداخت با موفقیت انجام شد و لایسنس تمدید شد.</p>@endif
  @if($error)<p class="msg" style="background:#fde8e8;color:#b42318">پرداخت انجام نشد. دوباره تلاش کنید.</p>@endif
</div>
<div class="card">
  <h2 style="font-size:18px;margin-top:0">مدت تمدید را انتخاب کنید</h2>
  @if(! $info['renewable'])
    <p>برای این لایسنس قیمت تمدید تعریف نشده است. لطفاً با پشتیبانی تماس بگیرید.</p>
  @else
  <div class="opts">
    @foreach($info['options'] as $o)
      <a class="opt" href="{{ $o['checkout_url'] }}">
        <div>{{ $o['label'] }}</div>
        @if($o['discount_percent'])<div class="old">{{ number_format(intdiv($o['price_irr'], 10)) }} تومان</div>@endif
        <div class="price">{{ number_format($o['final_toman']) }} تومان</div>
        @if($o['discount_percent'])<div class="badge">{{ rtrim(rtrim(number_format($o['discount_percent'], 2), '0'), '.') }}٪ تخفیف</div>@endif
        <div style="font-size:13px;color:#666;margin-top:6px">اعتبار تا {{ \Illuminate\Support\Carbon::parse($o['new_expires_at'])->format('Y-m-d') }}</div>
        <span class="btn">پرداخت</span>
      </a>
    @endforeach
  </div>
  @endif
</div>
</div></body></html>

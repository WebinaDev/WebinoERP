<?php

namespace Modules\Crm\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\AiContent\Services\ErpAiAssistant;
use Modules\Crm\Entities\ContentCalendar;
use Modules\Crm\Entities\ContentItem;
use Modules\Crm\Entities\CrmCatalogProduct;
use Modules\Crm\Entities\CrmCompany;
use Modules\Crm\Entities\CrmCurrency;
use Modules\Crm\Entities\CrmDeal;
use Modules\Crm\Entities\CrmEInvoice;
use Modules\Crm\Entities\CrmEnvelope;
use Modules\Crm\Entities\CrmFxRate;
use Modules\Crm\Entities\CrmLeadForm;
use Modules\Crm\Entities\CrmPriceBook;
use Modules\Crm\Entities\CrmPriceItem;
use Modules\Crm\Entities\CrmSigner;
use Modules\Crm\Services\CpqService;
use Modules\Crm\Services\EInvoiceBuilder;
use Modules\Integrations\Services\NotificationFanout;

class CrmExpansionController extends Controller
{
    public function companies(): JsonResponse
    {
        return response()->json(['data' => CrmCompany::query()->orderBy('name')->get()]);
    }

    public function storeCompany(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'legal_name' => 'nullable|string|max:200',
            'national_id' => 'nullable|string|max:20',
            'economic_code' => 'nullable|string|max:20',
            'currency_code' => 'nullable|string|max:8',
            'is_default' => 'nullable|boolean',
        ]);
        if (! empty($data['is_default'])) {
            CrmCompany::query()->update(['is_default' => false]);
        }
        $company = CrmCompany::query()->create($data);

        return response()->json(['data' => $company], 201);
    }

    public function switchCompany(Request $request, int $id): JsonResponse
    {
        $company = CrmCompany::query()->findOrFail($id);
        DB::table('crm_company_users')->where('user_id', $request->user()->id)->update(['is_active' => false, 'updated_at' => now()]);
        DB::table('crm_company_users')->updateOrInsert(
            ['user_id' => $request->user()->id, 'company_id' => $company->id],
            ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]
        );

        return response()->json(['data' => ['company_id' => $company->id, 'name' => $company->name]]);
    }

    public function activeCompany(Request $request): JsonResponse
    {
        $row = DB::table('crm_company_users')->where('user_id', $request->user()->id)->where('is_active', true)->first();
        $company = $row ? CrmCompany::query()->find($row->company_id) : CrmCompany::query()->where('is_default', true)->first();

        return response()->json(['data' => $company]);
    }

    public function currencies(): JsonResponse
    {
        return response()->json(['data' => CrmCurrency::query()->orderBy('code')->get()]);
    }

    public function storeRate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'base_code' => 'required|string|size:3',
            'quote_code' => 'required|string|size:3',
            'rate' => 'required|numeric|gt:0',
            'effective_on' => 'nullable|date',
        ]);
        $data['base_code'] = strtoupper($data['base_code']);
        $data['quote_code'] = strtoupper($data['quote_code']);
        $data['effective_on'] = $data['effective_on'] ?? now()->toDateString();
        $rate = CrmFxRate::query()->create($data);

        return response()->json(['data' => $rate], 201);
    }

    public function convert(Request $request, CpqService $cpq): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric',
            'from' => 'required|string|size:3',
            'to' => 'required|string|size:3',
        ]);

        return response()->json(['data' => [
            'amount' => $cpq->convert($data['from'], $data['to'], (float) $data['amount']),
            'from' => strtoupper($data['from']),
            'to' => strtoupper($data['to']),
        ]]);
    }

    public function products(): JsonResponse
    {
        return response()->json(['data' => CrmCatalogProduct::query()->orderBy('name')->get()]);
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'nullable|integer',
            'sku' => 'nullable|string|max:64',
            'name' => 'required|string|max:160',
            'unit' => 'nullable|string|max:32',
            'tax_percent' => 'nullable|numeric|min:0|max:100',
            'category' => 'nullable|string|max:80',
        ]);
        $product = CrmCatalogProduct::query()->create($data);

        return response()->json(['data' => $product], 201);
    }

    public function priceBooks(): JsonResponse
    {
        return response()->json(['data' => CrmPriceBook::query()->withCount('items')->orderBy('name')->get()]);
    }

    public function storePriceBook(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'currency_code' => 'nullable|string|max:8',
            'company_id' => 'nullable|integer',
            'items' => 'nullable|array',
            'items.*.product_id' => 'required|integer',
            'items.*.min_qty' => 'nullable|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
        ]);
        $book = CrmPriceBook::query()->create([
            'name' => $data['name'],
            'currency_code' => $data['currency_code'] ?? 'IRR',
            'company_id' => $data['company_id'] ?? null,
        ]);
        foreach ($data['items'] ?? [] as $item) {
            CrmPriceItem::query()->create([
                'price_book_id' => $book->id,
                'product_id' => $item['product_id'],
                'min_qty' => $item['min_qty'] ?? 1,
                'unit_price' => $item['unit_price'],
                'discount_percent' => $item['discount_percent'] ?? 0,
            ]);
        }

        return response()->json(['data' => $book->load('items')], 201);
    }

    public function quote(Request $request, CrmDeal $deal, CpqService $cpq): JsonResponse
    {
        $data = $request->validate([
            'price_book_id' => 'required|integer',
            'currency' => 'nullable|string|size:3',
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => 'required|integer',
            'lines.*.qty' => 'required|numeric|min:0.01',
            'header_percent' => 'nullable|numeric|min:0|max:100',
        ]);
        $book = CrmPriceBook::query()->findOrFail($data['price_book_id']);
        try {
            $result = $cpq->quote($deal, $book->id, strtoupper($data['currency'] ?? $book->currency_code), $data['lines'], [
                'header_percent' => (float) ($data['header_percent'] ?? 0),
                'user_id' => $request->user()->id,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['lines' => [$e->getMessage()]]], 422);
        }

        return response()->json(['data' => $result]);
    }

    public function forms(): JsonResponse
    {
        return response()->json(['data' => CrmLeadForm::query()->orderByDesc('id')->get()]);
    }

    public function storeForm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'slug' => 'nullable|string|max:80',
            'landing_url' => 'nullable|string|max:255',
            'company_id' => 'nullable|integer',
            'notify_user_id' => 'nullable|integer',
            'fields' => 'nullable|array',
        ]);
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $form = CrmLeadForm::query()->create($data);

        return response()->json(['data' => $form], 201);
    }

    public function assist(Request $request, ErpAiAssistant $ai): JsonResponse
    {
        $data = $request->validate([
            'purpose' => 'required|in:lead_suggest,summarize_note,email_draft,deal_risk,content_brief,task_plan',
            'context' => 'nullable|array',
        ]);

        return response()->json(['data' => $ai->assist($data['purpose'], $data['context'] ?? [])]);
    }

    public function envelopes(): JsonResponse
    {
        return response()->json(['data' => CrmEnvelope::query()->with('signers')->orderByDesc('id')->limit(50)->get()]);
    }

    public function invoices(): JsonResponse
    {
        return response()->json(['data' => CrmEInvoice::query()->orderByDesc('id')->limit(50)->get()]);
    }

    public function storeEnvelope(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'body' => 'nullable|string',
            'company_id' => 'nullable|integer',
            'deal_id' => 'nullable|integer',
            'signers' => 'required|array|min:1',
            'signers.*.name' => 'required|string',
            'signers.*.national_id' => 'nullable|string|max:20',
            'signers.*.mobile' => 'nullable|string|max:20',
            'signers.*.role' => 'nullable|string|max:40',
        ]);
        $envelope = CrmEnvelope::query()->create([
            'title' => $data['title'],
            'body' => $data['body'] ?? '',
            'company_id' => $data['company_id'] ?? null,
            'deal_id' => $data['deal_id'] ?? null,
            'document_hash' => hash('sha256', ($data['title'] ?? '').($data['body'] ?? '')),
            'status' => 'sent',
        ]);
        foreach ($data['signers'] as $signer) {
            CrmSigner::query()->create([
                'envelope_id' => $envelope->id,
                'name' => $signer['name'],
                'national_id' => $signer['national_id'] ?? null,
                'mobile' => $signer['mobile'] ?? null,
                'role' => $signer['role'] ?? 'signer',
            ]);
        }

        return response()->json(['data' => $envelope->load('signers')], 201);
    }

    public function requestOtp(int $envelopeId, int $signerId): JsonResponse
    {
        $signer = CrmSigner::query()->where('envelope_id', $envelopeId)->findOrFail($signerId);
        $code = (string) random_int(100000, 999999);
        $signer->update(['otp_hash' => Hash::make($code)]);
        $payload = ['sent' => true];
        if (app()->environment('testing')) {
            $payload['debug_code'] = $code;
        }

        return response()->json(['data' => $payload]);
    }

    public function sign(Request $request, int $envelopeId, int $signerId): JsonResponse
    {
        $signer = CrmSigner::query()->where('envelope_id', $envelopeId)->findOrFail($signerId);
        $data = $request->validate(['code' => 'required|string']);
        if (! $signer->otp_hash || ! Hash::check($data['code'], $signer->otp_hash)) {
            return response()->json(['message' => 'invalid_otp', 'errors' => ['code' => ['invalid_otp']]], 422);
        }
        $signer->update([
            'signed_at' => now(),
            'signature_hash' => hash('sha256', $signer->national_id.'|'.$signer->id.'|'.$data['code']),
            'otp_hash' => null,
        ]);
        $pending = CrmSigner::query()->where('envelope_id', $envelopeId)->whereNull('signed_at')->exists();
        $envelope = CrmEnvelope::query()->findOrFail($envelopeId);
        if (! $pending) {
            $envelope->update(['status' => 'signed']);
        }

        return response()->json(['data' => ['signed' => true, 'envelope_status' => $envelope->fresh()->status]]);
    }

    public function storeInvoice(Request $request, EInvoiceBuilder $builder): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'nullable|integer',
            'deal_id' => 'nullable|integer',
            'currency' => 'nullable|string|size:3',
            'buyer_type' => 'nullable|in:legal,natural',
            'buyer_economic_code' => 'nullable|string|max:20',
            'buyer_national_id' => 'nullable|string|max:20',
            'seller_economic_code' => 'nullable|string|max:20',
            'seller_national_id' => 'nullable|string|max:20',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.qty' => 'required|numeric|min:0',
            'items.*.fee' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.vat_rate' => 'nullable|numeric|min:0',
            'items.*.commodity_code' => 'nullable|string',
            'items.*.unit' => 'nullable|string',
        ]);
        $company = isset($data['company_id']) ? CrmCompany::query()->find($data['company_id']) : null;
        $seller = $builder->sellerFromCompany($company);
        $document = $builder->build(array_merge($seller, $data), $data['items'], strtoupper($data['currency'] ?? 'IRR'));
        $invoice = $builder->store($company?->id, $data['deal_id'] ?? null, $document);
        \App\Support\MutationAudit::record($request->user()->id, 'crm', 'einvoice.draft', 'einvoice', $invoice->id, [
            'sandbox' => app(\Modules\Crm\Services\MoadianClient::class)->sandbox(),
        ]);

        return response()->json(['data' => $invoice], 201);
    }

    public function submitInvoice(int $id, EInvoiceBuilder $builder): JsonResponse
    {
        $invoice = CrmEInvoice::query()->findOrFail($id);

        return response()->json(['data' => $builder->submit($invoice)]);
    }

    public function calendars(Request $request): JsonResponse
    {
        $query = ContentCalendar::query()->with(['items' => fn ($q) => $q->orderBy('id')])->withCount('items')->orderByDesc('id');
        if ($request->filled('account_id')) {
            $query->where('account_id', $request->integer('account_id'));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function storeCalendar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'account_id' => 'nullable|integer',
            'company_id' => 'nullable|integer',
            'kind' => 'required|in:blog,social',
            'network' => 'required|string|max:32',
            'name' => 'required|string|max:160',
        ]);

        return response()->json(['data' => ContentCalendar::query()->create($data)], 201);
    }

    public function storeItem(Request $request, int $calendarId): JsonResponse
    {
        ContentCalendar::query()->findOrFail($calendarId);
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'body' => 'nullable|string',
            'status' => 'nullable|in:idea,draft,review,scheduled,published',
            'assignee_id' => 'nullable|integer',
            'publish_start' => 'nullable|date',
            'publish_end' => 'nullable|date',
            'remind_at' => 'nullable|date',
            'image_url' => 'nullable|string|max:500',
        ]);
        $data['calendar_id'] = $calendarId;
        $item = ContentItem::query()->create($data);

        return response()->json(['data' => $item], 201);
    }

    public function updateItem(Request $request, int $id): JsonResponse
    {
        $item = ContentItem::query()->findOrFail($id);
        $data = $request->validate([
            'title' => 'sometimes|string|max:200',
            'body' => 'nullable|string',
            'status' => 'nullable|in:idea,draft,review,scheduled,published',
            'assignee_id' => 'nullable|integer',
            'publish_start' => 'nullable|date',
            'publish_end' => 'nullable|date',
            'remind_at' => 'nullable|date',
        ]);
        $item->update($data);

        return response()->json(['data' => $item->fresh()]);
    }

    public function remindDue(NotificationFanout $fanout): JsonResponse
    {
        $due = ContentItem::query()
            ->whereNotNull('remind_at')
            ->whereNull('reminded_at')
            ->where('remind_at', '<=', now())
            ->limit(50)
            ->get();
        foreach ($due as $item) {
            if ($item->assignee_id) {
                $fanout->dispatch([
                    'title' => 'یادآور محتوا',
                    'body' => $item->title,
                    'priority' => 'high',
                    'event_key' => 'content.reminder',
                    'user_id' => $item->assignee_id,
                    'channels' => ['in_app', 'chat', 'sms'],
                ]);
            }
            $item->update(['reminded_at' => now()]);
        }

        return response()->json(['data' => ['count' => $due->count()]]);
    }
}

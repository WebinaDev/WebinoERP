<?php

namespace Modules\Crm\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Crm\Entities\CrmCompany;
use Modules\Crm\Entities\CrmEInvoice;

class EInvoiceBuilder
{
    /**
     * Iran-friendly sales invoice document (سامانه مودیان shape, local hook).
     *
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $parties
     */
    public function build(array $parties, array $items, string $currency = 'IRR', int $invoiceType = 1): array
    {
        $serial = now()->format('ymd').Str::upper(Str::random(6));
        $normalized = [];
        $total = 0.0;
        $vat = 0.0;
        foreach ($items as $index => $item) {
            $qty = (float) ($item['qty'] ?? 1);
            $fee = (float) ($item['fee'] ?? 0);
            $discount = (float) ($item['discount'] ?? 0);
            $vatRate = (float) ($item['vat_rate'] ?? 10);
            $net = max(0, ($qty * $fee) - $discount);
            $vatAmount = round($net * ($vatRate / 100), 2);
            $line = [
                'row' => $index + 1,
                'commodity_code' => (string) ($item['commodity_code'] ?? ''),
                'description' => (string) ($item['description'] ?? ''),
                'qty' => $qty,
                'unit' => (string) ($item['unit'] ?? '1627'),
                'fee' => $fee,
                'discount' => $discount,
                'vat_rate' => $vatRate,
                'vat_amount' => $vatAmount,
                'amount' => round($net + $vatAmount, 2),
            ];
            $normalized[] = $line;
            $total += $line['amount'];
            $vat += $vatAmount;
        }

        $header = [
            'invoice_type' => $invoiceType,
            'pattern' => 1,
            'serial' => $serial,
            'taxid' => null,
            'issue_date' => now()->toDateString(),
            'currency' => $currency,
            'seller' => [
                'economic_code' => (string) ($parties['seller_economic_code'] ?? ''),
                'national_id' => (string) ($parties['seller_national_id'] ?? ''),
            ],
            'buyer' => [
                'type' => (string) ($parties['buyer_type'] ?? 'legal'),
                'economic_code' => (string) ($parties['buyer_economic_code'] ?? ''),
                'national_id' => (string) ($parties['buyer_national_id'] ?? ''),
            ],
            'items' => $normalized,
            'totals' => [
                'vat' => round($vat, 2),
                'payable' => round($total, 2),
            ],
        ];
        $header['taxid'] = substr(hash('sha256', json_encode($header)), 0, 22);

        return $header;
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public function store(?int $companyId, ?int $dealId, array $document): CrmEInvoice
    {
        return CrmEInvoice::query()->create([
            'company_id' => $companyId,
            'deal_id' => $dealId,
            'invoice_type' => (int) ($document['invoice_type'] ?? 1),
            'pattern' => (int) ($document['pattern'] ?? 1),
            'serial' => (string) $document['serial'],
            'taxid' => (string) ($document['taxid'] ?? ''),
            'seller_economic_code' => $document['seller']['economic_code'] ?? null,
            'seller_national_id' => $document['seller']['national_id'] ?? null,
            'buyer_economic_code' => $document['buyer']['economic_code'] ?? null,
            'buyer_national_id' => $document['buyer']['national_id'] ?? null,
            'buyer_type' => $document['buyer']['type'] ?? 'legal',
            'currency_code' => $document['currency'] ?? 'IRR',
            'total' => $document['totals']['payable'] ?? 0,
            'vat' => $document['totals']['vat'] ?? 0,
            'status' => 'draft',
            'document' => $document,
        ]);
    }

    public function submit(CrmEInvoice $invoice): CrmEInvoice
    {
        $hook = (string) config('integrations.moadian.hook_url');
        $payload = $invoice->document;
        if ($hook === '') {
            $invoice->update(['status' => 'queued', 'provider_response' => ['mode' => 'queued_local']]);

            return $invoice->fresh();
        }
        $response = Http::timeout(20)->acceptJson()->post($hook, $payload);
        $invoice->update([
            'status' => $response->successful() ? 'accepted' : 'rejected',
            'provider_response' => [
                'code' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ],
        ]);

        return $invoice->fresh();
    }

    public function sellerFromCompany(?CrmCompany $company): array
    {
        return [
            'seller_economic_code' => (string) ($company->economic_code ?? ''),
            'seller_national_id' => (string) ($company->national_id ?? ''),
        ];
    }
}

<?php

namespace Modules\Sales\Services;

use Illuminate\Support\Facades\DB;
use Modules\Sales\Entities\MarketingGrowthAttribution;
use Modules\Sales\Entities\SalesInvoice;
use Modules\Sales\Entities\SalesInvoiceLine;
use Modules\Sales\Entities\SalesQuote;

/**
 * Enterprise Order-to-Cash & Growth Attribution Engine.
 * Converts Deals into Quotes, Quotes into Invoices, and connects Marketing campaigns to realized revenues.
 */
class OrderToCashService
{
    /**
     * Create an official Sales Quote from a CRM Deal or direct customer.
     */
    public function createQuote(array $data, array $items): SalesQuote
    {
        return DB::transaction(function () use ($data, $items) {
            $quoteNumber = $this->nextNumber('SQ-'.date('Ymd').'-', SalesQuote::class, 'quote_number');

            $subtotal = 0;
            $discountTotal = 0;
            $taxTotal = 0;

            foreach ($items as $item) {
                $qty = (float) ($item['quantity'] ?? 1);
                $price = (float) ($item['unit_price'] ?? 0);
                $disc = (float) ($item['discount_amount'] ?? 0);
                $tax = (float) ($item['tax_amount'] ?? (($qty * $price - $disc) * 0.10));

                $subtotal += ($qty * $price);
                $discountTotal += $disc;
                $taxTotal += $tax;
            }

            $total = $subtotal - $discountTotal + $taxTotal;

            $quote = SalesQuote::create([
                'quote_number' => $quoteNumber,
                'customer_id' => $data['customer_id'] ?? null,
                'customer_name' => $data['customer_name'],
                'crm_deal_id' => $data['crm_deal_id'] ?? null,
                'issue_date' => $data['issue_date'] ?? now(),
                'valid_until' => $data['valid_until'] ?? now()->addDays(30),
                'subtotal' => $subtotal,
                'discount_amount' => $discountTotal,
                'tax_amount' => $taxTotal,
                'total_amount' => $total,
                'status' => 'sent',
                'terms_and_conditions' => $data['terms_and_conditions'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($items as $item) {
                $qty = (float) ($item['quantity'] ?? 1);
                $price = (float) ($item['unit_price'] ?? 0);
                $disc = (float) ($item['discount_amount'] ?? 0);
                $tax = (float) ($item['tax_amount'] ?? (($qty * $price - $disc) * 0.10));
                $lineTotal = ($qty * $price) - $disc + $tax;

                $quote->lines()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'item_name' => $item['item_name'],
                    'quantity' => $qty,
                    'uom' => $item['uom'] ?? 'unit',
                    'unit_price' => $price,
                    'discount_rate' => $item['discount_rate'] ?? 0,
                    'discount_amount' => $disc,
                    'tax_rate' => $item['tax_rate'] ?? 10,
                    'tax_amount' => $tax,
                    'line_total' => $lineTotal,
                ]);
            }

            return $quote->load('lines');
        });
    }

    /**
     * Convert an accepted Sales Quote into a formal Sales Invoice with normalized lines.
     */
    public function convertQuoteToInvoice(SalesQuote $quote, ?string $dueDate = null): SalesInvoice
    {
        return DB::transaction(function () use ($quote, $dueDate) {
            $quote->loadMissing('lines');
            $invoiceNumber = $this->nextNumber('INV-'.date('Ymd').'-', SalesInvoice::class, 'number');

            $invoice = SalesInvoice::create([
                'number' => $invoiceNumber,
                'invoice_number' => $invoiceNumber,
                'customer_name' => $quote->customer_name,
                'customer_id' => $quote->customer_id,
                'crm_deal_id' => $quote->crm_deal_id,
                'subtotal' => $quote->subtotal,
                'discount' => $quote->discount_amount,
                'tax_amount' => $quote->tax_amount,
                'total' => $quote->total_amount,
                'status' => 'approved',
                'issue_date' => now(),
                'due_date' => $dueDate ?: now()->addDays(15),
                'payment_status' => 'unpaid',
                'notes' => "صادر شده از پیش‌فاکتور رسمی {$quote->quote_number}",
                'created_by' => auth()->id(),
            ]);

            foreach ($quote->lines as $line) {
                SalesInvoiceLine::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $line->product_id,
                    'item_name' => $line->item_name,
                    'quantity' => $line->quantity,
                    'uom' => $line->uom,
                    'unit_price' => $line->unit_price,
                    'discount_rate' => $line->discount_rate,
                    'discount_amount' => $line->discount_amount,
                    'tax_rate' => $line->tax_rate,
                    'tax_amount' => $line->tax_amount,
                    'line_total' => $line->line_total,
                ]);
            }

            $quote->update(['status' => 'converted']);

            // اگر منشأ لید یا کمپین مارکتینگ ثبت شده باشد، درآمد محقق‌شده را به کمپین نسبت می‌دهیم
            if ($quote->crm_deal_id) {
                MarketingGrowthAttribution::where('crm_deal_id', $quote->crm_deal_id)
                    ->update([
                        'sales_quote_id' => $quote->id,
                        'sales_invoice_id' => $invoice->id,
                        'realized_revenue' => $invoice->total,
                        'converted_at' => now(),
                    ]);
            }

            return $invoice;
        });
    }

    /**
     * Compute end-to-end Marketing attribution to Sales revenue.
     */
    public function getGrowthAttributionReport(): array
    {
        $rows = MarketingGrowthAttribution::query()
            ->select(
                'utm_campaign',
                'campaign_source',
                DB::raw('COUNT(crm_lead_id) as total_leads'),
                DB::raw('COUNT(crm_deal_id) as total_deals'),
                DB::raw('COUNT(sales_invoice_id) as total_invoices'),
                DB::raw('SUM(realized_revenue) as total_revenue')
            )
            ->groupBy('utm_campaign', 'campaign_source')
            ->orderByDesc('total_revenue')
            ->get();

        return [
            'data' => $rows,
            'summary' => [
                'total_pipeline_revenue' => (float) $rows->sum('total_revenue'),
                'total_converted_invoices' => (int) $rows->sum('total_invoices'),
            ],
        ];
    }

    /**
     * Daily sequential number (PREFIX-0001, ...) that never collides with an existing row.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    private function nextNumber(string $prefix, string $model, string $column): string
    {
        $n = $model::query()->where($column, 'like', $prefix.'%')->count() + 1;
        do {
            $candidate = $prefix.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
            $n++;
        } while ($model::query()->where($column, $candidate)->exists());

        return $candidate;
    }
}

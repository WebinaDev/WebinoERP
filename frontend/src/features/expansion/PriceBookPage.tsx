'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { expansionApi, type Row } from '@/features/expansion/api';
import { AiAssistPanel } from '@/features/expansion/AiAssistPanel';

export function PriceBookPage() {
  const t = useTranslations('expansion');
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [products, setProducts] = useState<Row[]>([]);
  const [books, setBooks] = useState<Row[]>([]);
  const [product, setProduct] = useState({ name: '', sku: '', tax_percent: '10' });
  const [book, setBook] = useState({ name: '', currency_code: 'IRR', product_id: '', min_qty: '1', unit_price: '', discount_percent: '0' });
  const [quote, setQuote] = useState({ deal_id: '', price_book_id: '', product_id: '', qty: '1', total: '' });
  const [rule, setRule] = useState({ product_id: '', related_product_id: '', kind: 'requires' });
  const [discount, setDiscount] = useState({ scope: 'product', scope_key: '', name: '', percent: '5', parent_id: '' });
  const [approvals, setApprovals] = useState<Row[]>([]);

  const load = useCallback(async () => {
    try {
      const [p, b, pending] = await Promise.all([expansionApi.products(), expansionApi.priceBooks(), expansionApi.approvals()]);
      setProducts(p);
      setBooks(b);
      setApprovals(pending);
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <CrmPageLayout title={t('price.title')} description={t('price.description')} {...layoutProps}>
      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader><CardTitle className="text-base">{t('price.product')}</CardTitle></CardHeader>
          <CardContent className="space-y-2">
            <Input placeholder={t('price.name')} value={product.name} onChange={(e) => setProduct({ ...product, name: e.target.value })} />
            <Input placeholder="SKU" value={product.sku} onChange={(e) => setProduct({ ...product, sku: e.target.value })} />
            <Input placeholder={t('price.tax')} value={product.tax_percent} onChange={(e) => setProduct({ ...product, tax_percent: e.target.value })} />
            <Button type="button" onClick={() => void expansionApi.saveProduct({ ...product, tax_percent: Number(product.tax_percent) }).then(() => { setSuccess(t('common.saved')); return load(); }).catch(applyAxiosError)}>{t('common.save')}</Button>
            <ul className="space-y-1 text-sm">
              {products.map((row) => <li key={String(row.id)}>{String(row.name)} · {String(row.sku || '')}</li>)}
            </ul>
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-base">{t('price.book')}</CardTitle></CardHeader>
          <CardContent className="space-y-2">
            <Input placeholder={t('price.bookName')} value={book.name} onChange={(e) => setBook({ ...book, name: e.target.value })} />
            <Input placeholder={t('companies.currency')} value={book.currency_code} onChange={(e) => setBook({ ...book, currency_code: e.target.value.toUpperCase() })} />
            <Input placeholder={t('price.productId')} value={book.product_id} onChange={(e) => setBook({ ...book, product_id: e.target.value })} />
            <div className="grid grid-cols-3 gap-2">
              <Input placeholder={t('price.minQty')} value={book.min_qty} onChange={(e) => setBook({ ...book, min_qty: e.target.value })} />
              <Input placeholder={t('price.unit')} value={book.unit_price} onChange={(e) => setBook({ ...book, unit_price: e.target.value })} />
              <Input placeholder={t('price.discount')} value={book.discount_percent} onChange={(e) => setBook({ ...book, discount_percent: e.target.value })} />
            </div>
            <Button
              type="button"
              onClick={() => void expansionApi.savePriceBook({
                name: book.name,
                currency_code: book.currency_code,
                items: book.product_id ? [{ product_id: Number(book.product_id), min_qty: Number(book.min_qty || 1), unit_price: Number(book.unit_price), discount_percent: Number(book.discount_percent || 0) }] : [],
              }).then(() => { setSuccess(t('common.saved')); return load(); }).catch(applyAxiosError)}
            >
              {t('common.save')}
            </Button>
            <ul className="space-y-1 text-sm">
              {books.map((row) => <li key={String(row.id)}>#{String(row.id)} {String(row.name)} · {String(row.currency_code)}</li>)}
            </ul>
          </CardContent>
        </Card>
        <Card className="lg:col-span-2">
          <CardHeader><CardTitle className="text-base">{t('price.quote')}</CardTitle></CardHeader>
          <CardContent className="grid gap-2 sm:grid-cols-4">
            <Input placeholder={t('price.dealId')} value={quote.deal_id} onChange={(e) => setQuote({ ...quote, deal_id: e.target.value })} />
            <Input placeholder={t('price.bookId')} value={quote.price_book_id} onChange={(e) => setQuote({ ...quote, price_book_id: e.target.value })} />
            <Input placeholder={t('price.productId')} value={quote.product_id} onChange={(e) => setQuote({ ...quote, product_id: e.target.value })} />
            <Input placeholder={t('price.qty')} value={quote.qty} onChange={(e) => setQuote({ ...quote, qty: e.target.value })} />
            <Button
              type="button"
              className="sm:col-span-4"
              onClick={() => void expansionApi.quote(Number(quote.deal_id), {
                price_book_id: Number(quote.price_book_id),
                lines: [{ product_id: Number(quote.product_id), qty: Number(quote.qty) }],
              }).then((row) => setQuote({ ...quote, total: String((row as { total?: unknown }).total ?? JSON.stringify(row)) })).catch(applyAxiosError)}
            >
              {t('price.quote')}
            </Button>
            {quote.total ? <p className="text-sm sm:col-span-4">{quote.total}</p> : null}
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-base">{t('price.rule')}</CardTitle></CardHeader>
          <CardContent className="space-y-2">
            <Input placeholder={t('price.productId')} value={rule.product_id} onChange={(e) => setRule({ ...rule, product_id: e.target.value })} />
            <Input placeholder={t('price.productId')} value={rule.related_product_id} onChange={(e) => setRule({ ...rule, related_product_id: e.target.value })} />
            <select className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm" value={rule.kind} onChange={(e) => setRule({ ...rule, kind: e.target.value })}>
              <option value="requires">{t('price.requires')}</option>
              <option value="excludes">{t('price.excludes')}</option>
            </select>
            <Button type="button" variant="outline" onClick={() => void expansionApi.saveProductRule({ ...rule, product_id: Number(rule.product_id), related_product_id: Number(rule.related_product_id) }).then(() => setSuccess(t('common.saved'))).catch(applyAxiosError)}>{t('common.save')}</Button>
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-base">{t('price.discountNode')}</CardTitle></CardHeader>
          <CardContent className="space-y-2">
            <Input placeholder={t('price.name')} value={discount.name} onChange={(e) => setDiscount({ ...discount, name: e.target.value })} />
            <Input placeholder={t('price.productId')} value={discount.scope_key} onChange={(e) => setDiscount({ ...discount, scope_key: e.target.value })} />
            <Input placeholder={t('price.discount')} value={discount.percent} onChange={(e) => setDiscount({ ...discount, percent: e.target.value })} />
            <Input placeholder={t('price.discountNode')} value={discount.parent_id} onChange={(e) => setDiscount({ ...discount, parent_id: e.target.value })} />
            <Button type="button" variant="outline" onClick={() => void expansionApi.saveDiscount({ ...discount, percent: Number(discount.percent), parent_id: discount.parent_id ? Number(discount.parent_id) : null }).then(() => setSuccess(t('common.saved'))).catch(applyAxiosError)}>{t('common.save')}</Button>
          </CardContent>
        </Card>
        <Card className="lg:col-span-2">
          <CardHeader><CardTitle className="text-base">{t('price.approval')}</CardTitle></CardHeader>
          <CardContent className="space-y-2 text-sm">
            {approvals.map((row) => (
              <div key={String(row.id)} className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border/60 px-3 py-2">
                <span>#{String(row.id)} {String(row.status)} · {String(row.discount_percent)}%</span>
                {row.status === 'pending' ? (
                  <span className="flex gap-2">
                    <Button type="button" size="sm" onClick={() => void expansionApi.decideApproval(Number(row.id), 'approved').then(() => load()).catch(applyAxiosError)}>{t('price.approve')}</Button>
                    <Button type="button" size="sm" variant="outline" onClick={() => void expansionApi.decideApproval(Number(row.id), 'rejected').then(() => load()).catch(applyAxiosError)}>{t('price.reject')}</Button>
                  </span>
                ) : null}
              </div>
            ))}
          </CardContent>
        </Card>
      </div>
      <AiAssistPanel purpose="deal_risk" context={{ amount: 0, probability: 50 }} />
    </CrmPageLayout>
  );
}

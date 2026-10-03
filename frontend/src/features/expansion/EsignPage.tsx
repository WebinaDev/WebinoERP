'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { expansionApi, type Row } from '@/features/expansion/api';
import { AiAssistPanel } from '@/features/expansion/AiAssistPanel';

export function EsignPage() {
  const t = useTranslations('expansion');
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [envelopes, setEnvelopes] = useState<Row[]>([]);
  const [invoices, setInvoices] = useState<Row[]>([]);
  const [env, setEnv] = useState({ title: '', body: '', signer: '', national_id: '', mobile: '' });
  const [otp, setOtp] = useState<Record<string, string>>({});
  const [inv, setInv] = useState({ description: '', qty: '1', fee: '', vat_rate: '10', buyer_national_id: '', buyer_economic_code: '' });

  const load = useCallback(async () => {
    try {
      const [e, i] = await Promise.all([expansionApi.envelopes(), expansionApi.invoices()]);
      setEnvelopes(e);
      setInvoices(i);
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <CrmPageLayout title={t('esign.title')} description={t('esign.description')} {...layoutProps}>
      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader><CardTitle className="text-base">{t('esign.new')}</CardTitle></CardHeader>
          <CardContent className="space-y-2">
            <Input placeholder={t('esign.contractTitle')} value={env.title} onChange={(e) => setEnv({ ...env, title: e.target.value })} />
            <Textarea placeholder={t('esign.body')} value={env.body} onChange={(e) => setEnv({ ...env, body: e.target.value })} />
            <Input placeholder={t('esign.signer')} value={env.signer} onChange={(e) => setEnv({ ...env, signer: e.target.value })} />
            <Input placeholder={t('companies.national')} value={env.national_id} onChange={(e) => setEnv({ ...env, national_id: e.target.value })} />
            <Input placeholder={t('esign.mobile')} value={env.mobile} onChange={(e) => setEnv({ ...env, mobile: e.target.value })} />
            <Button
              type="button"
              onClick={() => void expansionApi.saveEnvelope({
                title: env.title,
                body: env.body,
                signers: [{ name: env.signer, national_id: env.national_id, mobile: env.mobile, role: 'signer' }],
              }).then(() => { setSuccess(t('common.saved')); return load(); }).catch(applyAxiosError)}
            >
              {t('common.save')}
            </Button>
          </CardContent>
        </Card>
        <div className="space-y-3">
          {envelopes.map((row) => {
            const signers = Array.isArray(row.signers) ? (row.signers as Row[]) : [];
            return (
              <Card key={String(row.id)}>
                <CardContent className="space-y-2 py-4">
                  <p className="font-medium">{String(row.title)}</p>
                  <p className="text-xs text-muted-foreground">{String(row.status)}</p>
                  {signers.map((signer) => {
                    const key = `${row.id}-${signer.id}`;
                    return (
                      <div key={key} className="flex flex-wrap items-center gap-2">
                        <span className="text-sm">{String(signer.name)}</span>
                        <Button type="button" size="sm" variant="outline" onClick={() => void expansionApi.requestOtp(Number(row.id), Number(signer.id)).then(() => setSuccess(t('esign.otpSent'))).catch(applyAxiosError)}>{t('esign.otp')}</Button>
                        <Input className="max-w-32" placeholder={t('esign.code')} value={otp[key] ?? ''} onChange={(e) => setOtp({ ...otp, [key]: e.target.value })} />
                        <Button type="button" size="sm" onClick={() => void expansionApi.sign(Number(row.id), Number(signer.id), otp[key] ?? '').then(() => { setSuccess(t('esign.signed')); return load(); }).catch(applyAxiosError)}>{t('esign.sign')}</Button>
                      </div>
                    );
                  })}
                </CardContent>
              </Card>
            );
          })}
        </div>
        <Card>
          <CardHeader><CardTitle className="text-base">{t('esign.invoice')}</CardTitle></CardHeader>
          <CardContent className="space-y-2">
            <Input placeholder={t('esign.item')} value={inv.description} onChange={(e) => setInv({ ...inv, description: e.target.value })} />
            <div className="grid grid-cols-3 gap-2">
              <Input placeholder={t('price.qty')} value={inv.qty} onChange={(e) => setInv({ ...inv, qty: e.target.value })} />
              <Input placeholder={t('price.unit')} value={inv.fee} onChange={(e) => setInv({ ...inv, fee: e.target.value })} />
              <Input placeholder={t('price.tax')} value={inv.vat_rate} onChange={(e) => setInv({ ...inv, vat_rate: e.target.value })} />
            </div>
            <Input placeholder={t('esign.buyerNational')} value={inv.buyer_national_id} onChange={(e) => setInv({ ...inv, buyer_national_id: e.target.value })} />
            <Input placeholder={t('esign.buyerEconomic')} value={inv.buyer_economic_code} onChange={(e) => setInv({ ...inv, buyer_economic_code: e.target.value })} />
            <Button
              type="button"
              onClick={() => void expansionApi.saveInvoice({
                buyer_type: 'legal',
                buyer_national_id: inv.buyer_national_id,
                buyer_economic_code: inv.buyer_economic_code,
                items: [{ description: inv.description, qty: Number(inv.qty), fee: Number(inv.fee), vat_rate: Number(inv.vat_rate), unit: 'عدد' }],
              }).then(() => { setSuccess(t('common.saved')); return load(); }).catch(applyAxiosError)}
            >
              {t('esign.build')}
            </Button>
          </CardContent>
        </Card>
        <div className="space-y-3">
          {invoices.map((row) => (
            <Card key={String(row.id)}>
              <CardContent className="flex flex-wrap items-center justify-between gap-2 py-4">
                <div>
                  <p className="font-medium">#{String(row.id)} {String(row.status || '')}</p>
                  <p className="text-xs text-muted-foreground">{String((row.document as Row | undefined)?.taxid || '')}</p>
                </div>
                <Button type="button" size="sm" variant="outline" onClick={() => void expansionApi.submitInvoice(Number(row.id)).then(() => { setSuccess(t('esign.submitted')); return load(); }).catch(applyAxiosError)}>{t('esign.submit')}</Button>
              </CardContent>
            </Card>
          ))}
        </div>
      </div>
      <AiAssistPanel purpose="email_draft" />
    </CrmPageLayout>
  );
}

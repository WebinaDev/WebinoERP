'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PageLoadingState } from '@/features/shared/ui/PageStates';
import { getMyRequests, saveMyRequest } from '@/lib/api/hrm';

export function MyRequestsPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [type, setType] = useState('overtime');
  const [hours, setHours] = useState('');
  const [days, setDays] = useState('');
  const [amount, setAmount] = useState('');
  const [date, setDate] = useState('');
  const [notes, setNotes] = useState('');
  const [rows, setRows] = useState<Array<{ id: number; type: string; status: string; notes?: string }>>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await getMyRequests();
      setRows(res?.requests ?? []);
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const submit = async () => {
    const payload: Record<string, unknown> = {};
    if (hours) payload.hours = Number(hours);
    if (days) payload.days = Number(days);
    if (amount) payload.amount = Number(amount);
    if (date) {
      payload.date = date;
      const [y, m] = date.split('-');
      if (y && m) {
        payload.year = Number(y);
        payload.month = Number(m);
      }
    }
    try {
      await saveMyRequest({ type, notes: notes || null, payload });
      setHours('');
      setDays('');
      setAmount('');
      setNotes('');
      setSuccess(t('requests.submitted'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <CrmPageLayout title={tNav('nav.erp.hrm.myRequests')} {...layoutProps}>
      {loading ? <PageLoadingState /> : (
        <div className="grid gap-4 text-start md:grid-cols-2">
          <Card>
            <CardHeader><CardTitle className="text-base">{t('requests.new')}</CardTitle></CardHeader>
            <CardContent className="space-y-3">
              <Select value={type} onValueChange={setType}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="overtime">{t('requests.overtime')}</SelectItem>
                  <SelectItem value="mission">{t('requests.mission')}</SelectItem>
                  <SelectItem value="remote_work">{t('requests.remote')}</SelectItem>
                </SelectContent>
              </Select>
              <Input type="number" placeholder={t('requests.hours')} value={hours} onChange={(e) => setHours(e.target.value)} />
              <Input type="number" placeholder={t('requests.days')} value={days} onChange={(e) => setDays(e.target.value)} />
              <Input type="number" placeholder={t('requests.amount')} value={amount} onChange={(e) => setAmount(e.target.value)} />
              <Input type="date" value={date} onChange={(e) => setDate(e.target.value)} />
              <Textarea placeholder={t('reason')} value={notes} onChange={(e) => setNotes(e.target.value)} />
              <Button onClick={() => void submit()}>{tNav('common.save')}</Button>
            </CardContent>
          </Card>
          <Card>
            <CardHeader><CardTitle className="text-base">{t('myRequests')}</CardTitle></CardHeader>
            <CardContent className="space-y-2 text-sm">
              {rows.length === 0 ? <div className="text-muted-foreground">{tNav('common.empty')}</div> : rows.map((r) => (
                <div key={r.id} className="rounded border px-3 py-2">
                  <div className="flex justify-between"><span>{t(`requests.${r.type === 'remote_work' ? 'remote' : r.type}` as 'requests.overtime')}</span><span>{r.status}</span></div>
                  {r.notes ? <div className="text-muted-foreground">{r.notes}</div> : null}
                </div>
              ))}
            </CardContent>
          </Card>
        </div>
      )}
    </CrmPageLayout>
  );
}

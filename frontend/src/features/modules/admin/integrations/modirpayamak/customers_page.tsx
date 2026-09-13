'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useFormatter, useTranslations } from 'next-intl';
import { ExternalLink, MinusCircle, PlusCircle, ScrollText } from 'lucide-react';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { PmEmptyState } from '@/features/shared/pm';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  adjustModirPayamakBalance,
  getModirPayamakCustomerLedger,
  getModirPayamakCustomers,
  type ModirPayamakAccount,
  type ModirPayamakLedgerRow,
} from '@/lib/api/modirpayamak';
import { getAxiosMessage } from '@/lib/api-helpers';
import { dashboardHref } from '@/lib/route-resolver';
import { ModirPayamakStatusBadge } from './components/status-badge';
import { ModirPayamakBreadcrumb, ModirPayamakNotConfigured } from './components/shared';
import { useModirPayamakConfigured } from './hooks/useModirPayamakConfigured';

type CreditDialog = { domain: string; mode: 'credit' | 'debit' } | null;

export function ModirpayamakCustomersPage() {
  const t = useTranslations('modirpayamak');
  const tNav = useTranslations();
  const tCommon = useTranslations('common');
  const format = useFormatter();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const { layoutProps, setError, setSuccess } = useCrmFeedback();
  const { configured, loading: configLoading } = useModirPayamakConfigured();
  const [accounts, setAccounts] = useState<ModirPayamakAccount[]>([]);
  const [loading, setLoading] = useState(true);
  const [creditDlg, setCreditDlg] = useState<CreditDialog>(null);
  const [amount, setAmount] = useState('');
  const [note, setNote] = useState('');
  const [saving, setSaving] = useState(false);
  const [ledgerDomain, setLedgerDomain] = useState<string | null>(null);
  const [ledger, setLedger] = useState<ModirPayamakLedgerRow[]>([]);
  const [ledgerLoading, setLedgerLoading] = useState(false);

  const load = useCallback(async () => {
    if (!configured) {
      setLoading(false);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await getModirPayamakCustomers();
      setAccounts(res.accounts ?? []);
    } catch (e) {
      setAccounts([]);
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, [configured, setError]);

  useEffect(() => {
    void load();
  }, [load]);

  const openCredit = (domain: string, mode: 'credit' | 'debit') => {
    setCreditDlg({ domain, mode });
    setAmount('');
    setNote('');
  };

  const submitCredit = async () => {
    if (!creditDlg) return;
    const raw = Math.abs(parseFloat(amount) || 0);
    if (raw <= 0) {
      setError(t('amountRequired'));
      return;
    }
    setSaving(true);
    setError(null);
    const signed = creditDlg.mode === 'debit' ? -raw : raw;
    try {
      const res = await adjustModirPayamakBalance(creditDlg.domain, signed, note);
      setSuccess(res.message ?? tCommon('saved'));
      setCreditDlg(null);
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setSaving(false);
    }
  };

  const openLedger = async (domain: string) => {
    setLedgerDomain(domain);
    setLedgerLoading(true);
    setError(null);
    try {
      const res = await getModirPayamakCustomerLedger(domain);
      setLedger(res.ledger ?? []);
    } catch (e) {
      setLedger([]);
      setError(getAxiosMessage(e));
    } finally {
      setLedgerLoading(false);
    }
  };

  const lineLabel = (a: ModirPayamakAccount) => {
    const nums = a.numbers ?? [];
    if (!nums.length) return '—';
    return nums.map((n) => `${n.role}: ${n.number}`).join(' · ');
  };

  const formatDate = (raw?: string | null) => {
    if (!raw) return '—';
    try {
      return format.dateTime(new Date(raw.replace(/-/g, '/').slice(0, 16)), {
        dateStyle: 'short',
        timeStyle: 'short',
      });
    } catch {
      return raw.slice(0, 16);
    }
  };

  return (
    <CrmPageLayout title={tNav('nav.erp.admin.mpCustomers')} {...layoutProps}>
      <ModirPayamakBreadcrumb current={tNav('nav.erp.admin.mpCustomers')} />
      {configLoading ? null : !configured ? <ModirPayamakNotConfigured /> : null}

      {!configured ? null : loading ? (
        <Card>
          <CardContent className="h-40 animate-pulse bg-muted/30" />
        </Card>
      ) : accounts.length === 0 ? (
        <PmEmptyState title={t('customersEmpty')} />
      ) : (
        <Card>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t('domain')}</TableHead>
                  <TableHead>{t('balance')}</TableHead>
                  <TableHead>{t('attachedLines')}</TableHead>
                  <TableHead>{t('status')}</TableHead>
                  <TableHead>{t('creditExpiry')}</TableHead>
                  <TableHead className="w-[200px]">{tCommon('actions')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {accounts.map((a) => (
                  <TableRow key={a.id}>
                    <TableCell className="font-mono">{a.domain}</TableCell>
                    <TableCell>{format.number(a.balance)}</TableCell>
                    <TableCell className="max-w-xs truncate text-sm" dir="ltr" title={lineLabel(a)}>
                      {lineLabel(a)}
                    </TableCell>
                    <TableCell>
                      <ModirPayamakStatusBadge status={a.status} />
                    </TableCell>
                    <TableCell className="text-sm text-muted-foreground">
                      {formatDate(a.expires_at)}
                    </TableCell>
                    <TableCell>
                      <div className="flex flex-wrap gap-1">
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          title={t('credit')}
                          onClick={() => openCredit(a.domain, 'credit')}
                        >
                          <PlusCircle className="h-4 w-4 text-green-600" />
                        </Button>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          title={t('debit')}
                          onClick={() => openCredit(a.domain, 'debit')}
                        >
                          <MinusCircle className="h-4 w-4 text-destructive" />
                        </Button>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          title={t('ledger')}
                          onClick={() => void openLedger(a.domain)}
                        >
                          <ScrollText className="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="icon" asChild title={t('viewReports')}>
                          <Link
                            href={dashboardHref(
                              locale,
                              `admin/integrations/modirpayamak/reports?domain=${encodeURIComponent(a.domain)}`,
                            )}
                          >
                            <ExternalLink className="h-4 w-4" />
                          </Link>
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      )}

      <Dialog open={!!creditDlg} onOpenChange={(o) => !o && setCreditDlg(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>
              {creditDlg?.mode === 'debit' ? t('debit') : t('credit')}
              {creditDlg ? ` — ${creditDlg.domain}` : ''}
            </DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <div>
              <Label>{t('amount')}</Label>
              <Input
                className="mt-1"
                type="number"
                min={1}
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
              />
            </div>
            <div>
              <Label>{t('adjustNote')}</Label>
              <Textarea className="mt-1" rows={2} value={note} onChange={(e) => setNote(e.target.value)} />
            </div>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setCreditDlg(null)}>
              {tCommon('cancel')}
            </Button>
            <Button type="button" disabled={saving} onClick={() => void submitCredit()}>
              {tCommon('save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Sheet open={!!ledgerDomain} onOpenChange={(o) => !o && setLedgerDomain(null)}>
        <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
          <SheetHeader>
            <SheetTitle>
              {t('ledger')}
              {ledgerDomain ? ` — ${ledgerDomain}` : ''}
            </SheetTitle>
          </SheetHeader>
          {ledgerLoading ? (
            <p className="mt-4 text-sm text-muted-foreground">{tCommon('loading')}</p>
          ) : ledger.length === 0 ? (
            <p className="mt-4 text-sm text-muted-foreground">{t('ledgerEmpty')}</p>
          ) : (
            <ul className="mt-4 space-y-2 text-sm">
              {ledger.map((row) => (
                <li key={row.id} className="rounded border px-3 py-2">
                  <div className="flex justify-between gap-2">
                    <span className="font-medium">{row.type}</span>
                    <span dir="ltr">{format.number(row.amount)}</span>
                  </div>
                  <div className="flex justify-between gap-2 text-xs text-muted-foreground">
                    <span>{row.note || '—'}</span>
                    <span>{formatDate(row.created_at)}</span>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </SheetContent>
      </Sheet>
    </CrmPageLayout>
  );
}

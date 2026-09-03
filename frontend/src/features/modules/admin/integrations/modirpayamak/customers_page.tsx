'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Card, CardContent } from '@/components/ui/card';
import { adjustModirPayamakBalance, getModirPayamakCustomers } from '@/lib/api/modirpayamak';
import { ModirPayamakBreadcrumb, ModirPayamakNotConfigured } from './components/shared';
import { useModirPayamakConfigured } from './hooks/useModirPayamakConfigured';
import { useModirPayamakTenantDomain } from './hooks/useModirPayamakTenantDomain';

export function ModirpayamakCustomersPage() {
  const t = useTranslations('modirpayamak');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, setError, applyAxiosError } = useCrmFeedback();
  const { configured, loading: configLoading } = useModirPayamakConfigured();
  const { domain, setDomain, domains } = useModirPayamakTenantDomain();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [amount, setAmount] = useState('');

  const load = useCallback(async () => {
    try {
      const res = await getModirPayamakCustomers();
      setRows(Array.isArray(res) ? res : []);
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    if (configured) void load();
  }, [configured, load]);

  const adjust = async () => {
    if (!domain.trim()) {
      setError(t('domainRequired'));
      return;
    }
    try {
      await adjustModirPayamakBalance(domain.trim(), Number(amount));
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <CrmPageLayout title={tNav('nav.erp.admin.mpCustomers')} {...layoutProps}>
      <ModirPayamakBreadcrumb current={tNav('nav.erp.admin.mpCustomers')} />
      {configLoading ? null : !configured ? (
        <ModirPayamakNotConfigured />
      ) : (
        <>
          <Card>
            <CardContent className="flex flex-wrap items-end gap-2 pt-6">
              <div className="grid gap-1">
                <Label>{t('domain')}</Label>
                <Input
                  list="mp-cust-domains"
                  placeholder={t('domain')}
                  value={domain}
                  onChange={(e) => setDomain(e.target.value)}
                  dir="ltr"
                />
                <datalist id="mp-cust-domains">
                  {domains.map((d) => (
                    <option key={d} value={d} />
                  ))}
                </datalist>
              </div>
              <div className="grid gap-1">
                <Label>Amount</Label>
                <Input placeholder="amount" value={amount} onChange={(e) => setAmount(e.target.value)} dir="ltr" />
              </div>
              <Button onClick={() => void adjust()}>Adjust balance</Button>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="pt-6">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Domain</TableHead>
                    <TableHead>Balance</TableHead>
                    <TableHead>Status</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {rows.map((r) => (
                    <TableRow
                      key={String(r.id)}
                      className="cursor-pointer"
                      onClick={() => setDomain(String(r.domain ?? ''))}
                    >
                      <TableCell>{String(r.domain ?? '')}</TableCell>
                      <TableCell>{String(r.balance ?? '')}</TableCell>
                      <TableCell>{String(r.status ?? '')}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </>
      )}
    </CrmPageLayout>
  );
}

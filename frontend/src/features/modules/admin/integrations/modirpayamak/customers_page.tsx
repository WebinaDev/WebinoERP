'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Card, CardContent } from '@/components/ui/card';
import { adjustModirPayamakBalance, getModirPayamakCustomers } from '@/lib/api/modirpayamak';
import { ModirPayamakBreadcrumb, ModirPayamakNotConfigured } from './components/shared';
import { useModirPayamakConfigured } from './hooks/useModirPayamakConfigured';

export function ModirpayamakCustomersPage() {
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const { configured, loading: configLoading } = useModirPayamakConfigured();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [domain, setDomain] = useState('');
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
    try {
      await adjustModirPayamakBalance(domain, Number(amount));
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <CrmPageLayout title={tNav('nav.erp.admin.mpCustomers')} {...layoutProps}>
      <ModirPayamakBreadcrumb current={tNav('nav.erp.admin.mpCustomers')} />
      {configLoading ? null : !configured ? <ModirPayamakNotConfigured /> : (
        <>
          <Card>
            <CardContent className="flex flex-wrap gap-2 pt-6">
              <Input placeholder="domain" value={domain} onChange={(e) => setDomain(e.target.value)} />
              <Input placeholder="amount" value={amount} onChange={(e) => setAmount(e.target.value)} />
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
                    <TableRow key={String(r.id)}>
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

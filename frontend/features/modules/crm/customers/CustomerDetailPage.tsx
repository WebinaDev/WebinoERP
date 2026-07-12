'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Separator } from '@/components/ui/separator';
import { Button } from '@/components/ui/button';
import { useLocale } from '@/hooks/use-locale-next';
import { dashboardHref } from '@/lib/route-resolver';

type Props = { id: string };

export function CustomerDetailPage({ id }: Props) {
  const t = useTranslations('crm.customer360');
  const tC = useTranslations('crm.customers');
  const tNav = useTranslations();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const { formatDate } = useLocale();
  const { layoutProps, applyAxiosError } = useCrmFeedback();
  const [account, setAccount] = useState<Record<string, unknown> | null>(null);
  const [deals, setDeals] = useState<Record<string, unknown>[]>([]);
  const [tickets, setTickets] = useState<Record<string, unknown>[]>([]);
  const [contacts, setContacts] = useState<Record<string, unknown>[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [accRes, dealsRes, ticketsRes, contactsRes] = await Promise.all([
        apiClient.get(`/v1/crm/accounts/${id}`),
        apiClient.get('/v1/crm/deals', { params: { account_id: id, per_page: 20 } }),
        apiClient.get('/v1/crm/tickets', { params: { account_id: id, per_page: 20 } }),
        apiClient.get('/v1/crm/contacts', { params: { account_id: id, per_page: 20 } }),
      ]);
      setAccount(unwrapData(accRes) as Record<string, unknown>);
      const dealsBody = dealsRes.data as { data?: unknown[] };
      const ticketsBody = ticketsRes.data as { data?: unknown[] };
      const contactsBody = contactsRes.data as { data?: unknown[] };
      setDeals(Array.isArray(dealsBody.data) ? (dealsBody.data as Record<string, unknown>[]) : []);
      setTickets(Array.isArray(ticketsBody.data) ? (ticketsBody.data as Record<string, unknown>[]) : []);
      setContacts(Array.isArray(contactsBody.data) ? (contactsBody.data as Record<string, unknown>[]) : []);
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [id, applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <CrmPageLayout
      title={String(account?.name ?? account?.company_name ?? tC('title'))}
      actions={
        <Button variant="outline" size="sm" asChild>
          <Link href={dashboardHref(locale, 'crm/customers')}>{tNav('common.back')}</Link>
        </Button>
      }
      {...layoutProps}
    >
      {loading ? (
        <p className="text-sm text-muted-foreground">{tNav('common.loading')}</p>
      ) : account ? (
        <div className="space-y-4">
          <Card>
            <CardHeader><CardTitle className="text-lg">{t('title')}</CardTitle></CardHeader>
            <CardContent className="space-y-2 text-sm">
              <div className="flex flex-wrap gap-2">
                <Badge variant="secondary">{String(account.type ?? account.status ?? '—')}</Badge>
                {account.website ? <Badge variant="outline">{String(account.website)}</Badge> : null}
              </div>
              <p><span className="text-muted-foreground">{tC('email')}: </span>{String(account.email ?? '—')}</p>
              <p><span className="text-muted-foreground">{tC('phone')}: </span>{String(account.phone ?? account.mobile ?? '—')}</p>
              {account.description ? <p className="text-muted-foreground">{String(account.description)}</p> : null}
            </CardContent>
          </Card>
          <Separator />
          <Tabs defaultValue="deals">
            <TabsList className="grid w-full grid-cols-3">
              <TabsTrigger value="deals">{t('deals')}</TabsTrigger>
              <TabsTrigger value="tickets">{t('tickets')}</TabsTrigger>
              <TabsTrigger value="contacts">{t('contacts')}</TabsTrigger>
            </TabsList>
            <TabsContent value="deals" className="space-y-2">
              {deals.length === 0 ? (
                <p className="text-sm text-muted-foreground">{tNav('common.noData')}</p>
              ) : (
                deals.map((d) => (
                  <div key={String(d.id)} className="rounded-md border p-3 text-sm">
                    <p className="font-medium">{String(d.title ?? d.name ?? d.id)}</p>
                    <p className="text-muted-foreground">{String(d.status ?? '—')}</p>
                  </div>
                ))
              )}
            </TabsContent>
            <TabsContent value="tickets" className="space-y-2">
              {tickets.length === 0 ? (
                <p className="text-sm text-muted-foreground">{tNav('common.noData')}</p>
              ) : (
                tickets.map((tk) => (
                  <div key={String(tk.id)} className="rounded-md border p-3 text-sm">
                    <p className="font-medium">{String(tk.subject ?? tk.title ?? tk.id)}</p>
                    <p className="text-muted-foreground">{String(tk.status ?? '—')}</p>
                  </div>
                ))
              )}
            </TabsContent>
            <TabsContent value="contacts" className="space-y-2">
              {contacts.length === 0 ? (
                <p className="text-sm text-muted-foreground">{tNav('common.noData')}</p>
              ) : (
                contacts.map((c) => (
                  <div key={String(c.id)} className="rounded-md border p-3 text-sm">
                    <p className="font-medium">{String(c.name ?? c.id)}</p>
                    <p className="text-muted-foreground">{String(c.email ?? c.phone ?? '—')}</p>
                  </div>
                ))
              )}
            </TabsContent>
          </Tabs>
          {account.created_at ? (
            <p className="text-xs text-muted-foreground">
              {tNav('common.created')}: {formatDate(String(account.created_at))}
            </p>
          ) : null}
        </div>
      ) : null}
    </CrmPageLayout>
  );
}

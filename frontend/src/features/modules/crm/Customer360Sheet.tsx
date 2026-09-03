'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { getAxiosMessage } from '@/lib/api-helpers';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { useLocale } from '@/hooks/use-locale-next';

type Props = {
  accountId: number | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

export function Customer360Sheet({ accountId, open, onOpenChange }: Props) {
  const t = useTranslations();
  const { formatDate } = useLocale();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [account, setAccount] = useState<Record<string, unknown> | null>(null);
  const [deals, setDeals] = useState<Record<string, unknown>[]>([]);
  const [tickets, setTickets] = useState<Record<string, unknown>[]>([]);
  const [contacts, setContacts] = useState<Record<string, unknown>[]>([]);
  const [activities, setActivities] = useState<Record<string, unknown>[]>([]);

  const load = useCallback(async () => {
    if (!accountId) return;
    setLoading(true);
    setError(null);
    try {
      const [accRes, dealsRes, ticketsRes, contactsRes, activitiesRes] = await Promise.all([
        apiClient.get(`/v1/crm/accounts/${accountId}`),
        apiClient.get('/v1/crm/deals', { params: { account_id: accountId, per_page: 10 } }),
        apiClient.get('/v1/crm/tickets', { params: { account_id: accountId, per_page: 10 } }),
        apiClient.get('/v1/crm/contacts', { params: { account_id: accountId, per_page: 10 } }),
        apiClient.get('/v1/crm/activities', {
          params: { account_id: accountId, related_model: 'Modules\\Crm\\Entities\\CrmAccount', per_page: 20 },
        }),
      ]);
      setAccount((accRes.data as { data?: Record<string, unknown> }).data ?? null);
      const dealsBody = dealsRes.data as { data?: unknown[] };
      const ticketsBody = ticketsRes.data as { data?: unknown[] };
      const contactsBody = contactsRes.data as { data?: unknown[] };
      const activitiesBody = activitiesRes.data as { data?: unknown[] };
      setDeals(Array.isArray(dealsBody.data) ? (dealsBody.data as Record<string, unknown>[]) : []);
      setTickets(Array.isArray(ticketsBody.data) ? (ticketsBody.data as Record<string, unknown>[]) : []);
      setContacts(Array.isArray(contactsBody.data) ? (contactsBody.data as Record<string, unknown>[]) : []);
      setActivities(Array.isArray(activitiesBody.data) ? (activitiesBody.data as Record<string, unknown>[]) : []);
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, [accountId]);

  useEffect(() => {
    if (open && accountId) void load();
  }, [open, accountId, load]);

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
        <SheetHeader>
          <SheetTitle>{t('crm.customer360.title')}</SheetTitle>
        </SheetHeader>
        {loading ? (
          <p className="py-8 text-center text-sm text-muted-foreground">{t('common.loading')}</p>
        ) : error ? (
          <p className="py-4 text-sm text-destructive">{error}</p>
        ) : account ? (
          <div className="mt-4 space-y-4">
            <div>
              <h3 className="text-lg font-semibold">{String(account.name ?? '—')}</h3>
              <div className="mt-1 flex flex-wrap gap-2">
                <Badge variant="secondary">{String(account.type ?? '—')}</Badge>
                {account.website ? <Badge variant="outline">{String(account.website)}</Badge> : null}
              </div>
              {account.description ? (
                <p className="mt-2 text-sm text-muted-foreground">{String(account.description)}</p>
              ) : null}
            </div>
            <Separator />
            <Tabs defaultValue="deals">
              <TabsList className="grid w-full grid-cols-4">
                <TabsTrigger value="deals">{t('crm.customer360.deals')}</TabsTrigger>
                <TabsTrigger value="tickets">{t('crm.customer360.tickets')}</TabsTrigger>
                <TabsTrigger value="contacts">{t('crm.customer360.contacts')}</TabsTrigger>
                <TabsTrigger value="activities">{t('crm.customer360.activities')}</TabsTrigger>
              </TabsList>
              <TabsContent value="deals" className="space-y-2">
                {deals.length === 0 ? (
                  <p className="text-sm text-muted-foreground">{t('common.noData')}</p>
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
                  <p className="text-sm text-muted-foreground">{t('common.noData')}</p>
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
                  <p className="text-sm text-muted-foreground">{t('common.noData')}</p>
                ) : (
                  contacts.map((c) => (
                    <div key={String(c.id)} className="rounded-md border p-3 text-sm">
                      <p className="font-medium">{String(c.name ?? c.id)}</p>
                      <p className="text-muted-foreground">{String(c.email ?? c.phone ?? '—')}</p>
                    </div>
                  ))
                )}
              </TabsContent>
              <TabsContent value="activities" className="space-y-2">
                {activities.length === 0 ? (
                  <p className="text-sm text-muted-foreground">{t('common.noData')}</p>
                ) : (
                  activities.map((a) => (
                    <div key={String(a.id)} className="rounded-md border p-3 text-sm">
                      <p className="font-medium">{String(a.subject ?? a.type ?? a.id)}</p>
                      <p className="text-muted-foreground">{String(a.description ?? '—')}</p>
                      {a.created_at ? (
                        <p className="mt-1 text-xs text-muted-foreground">{formatDate(String(a.created_at))}</p>
                      ) : null}
                    </div>
                  ))
                )}
              </TabsContent>
            </Tabs>
            {account.created_at ? (
              <p className="text-xs text-muted-foreground">
                {t('common.created')}: {formatDate(String(account.created_at))}
              </p>
            ) : null}
          </div>
        ) : null}
      </SheetContent>
    </Sheet>
  );
}

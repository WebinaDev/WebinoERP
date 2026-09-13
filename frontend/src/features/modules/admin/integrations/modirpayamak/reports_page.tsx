'use client';

import { useCallback, useEffect, useState } from 'react';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';
import { useFormatter, useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { PmEmptyState, PmFilterBar } from '@/features/shared/pm';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { getModirPayamakMessages } from '@/lib/api/modirpayamak';
import {
  edgeField,
  edgeReportInbox,
  edgeReportOutbox,
  edgeReportOutboxDetail,
  type EdgeRow,
} from '@/lib/api/modirpayamak-edge';
import { getAxiosMessage } from '@/lib/api-helpers';
import { ModirPayamakOutboxTable } from './components/outbox-table';
import { ModirPayamakBreadcrumb, ModirPayamakNotConfigured } from './components/shared';
import { useModirPayamakConfigured } from './hooks/useModirPayamakConfigured';

export function ModirpayamakReportsPage() {
  const t = useTranslations('modirpayamak');
  const tNav = useTranslations();
  const tCommon = useTranslations('common');
  const format = useFormatter();
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const domainFilter = searchParams.get('domain') ?? '';
  const { layoutProps, setError } = useCrmFeedback();
  const { configured, loading: configLoading } = useModirPayamakConfigured();
  const [tab, setTab] = useState<'outbox' | 'inbox' | 'local'>('outbox');
  const [page, setPage] = useState(1);
  const [recipient, setRecipient] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [domainInput, setDomainInput] = useState(domainFilter);
  const [items, setItems] = useState<EdgeRow[]>([]);
  const [localMessages, setLocalMessages] = useState<Array<Record<string, unknown>>>([]);
  const [loading, setLoading] = useState(true);
  const [detailOpen, setDetailOpen] = useState(false);
  const [detail, setDetail] = useState<EdgeRow | null>(null);

  useEffect(() => {
    setDomainInput(domainFilter);
  }, [domainFilter]);

  const applyDomain = () => {
    const next = domainInput.trim();
    const params = new URLSearchParams(searchParams.toString());
    if (next) params.set('domain', next);
    else params.delete('domain');
    const qs = params.toString();
    router.replace(qs ? `${pathname}?${qs}` : pathname);
    setPage(1);
  };

  const load = useCallback(async () => {
    if (!configured) {
      setLoading(false);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      if (tab === 'local') {
        const res = await getModirPayamakMessages(domainFilter || undefined, page);
        setLocalMessages(res.messages ?? []);
        setItems([]);
      } else {
        const filters: Record<string, unknown> = {};
        if (recipient.trim()) filters.number = recipient.trim();
        if (statusFilter.trim()) filters.state_id = statusFilter.trim();
        if (domainFilter.trim()) filters.username = domainFilter.trim();
        if (dateFrom.trim()) {
          const ts = Math.floor(new Date(`${dateFrom.trim()}T00:00:00`).getTime() / 1000);
          if (Number.isFinite(ts)) filters.create_from_date = String(ts);
        }
        if (dateTo.trim()) {
          const ts = Math.floor(new Date(`${dateTo.trim()}T23:59:59`).getTime() / 1000);
          if (Number.isFinite(ts)) filters.create_to_date = String(ts);
        }
        const res =
          tab === 'outbox'
            ? await edgeReportOutbox(page, 20, filters)
            : await edgeReportInbox(page, 20, filters);
        if (res.error) {
          setItems([]);
          setLocalMessages([]);
          setError(res.error);
        } else {
          setItems(res.items);
          setLocalMessages([]);
        }
      }
    } catch (e) {
      setItems([]);
      setLocalMessages([]);
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, [configured, dateFrom, dateTo, domainFilter, page, recipient, setError, statusFilter, tab]);

  useEffect(() => {
    void load();
  }, [load]);

  const openDetail = async (row: EdgeRow) => {
    const id = edgeField(row, 'id', 'messages_outbox_id', 'outbox_id');
    if (id === '—') {
      setDetail(row);
      setDetailOpen(true);
      return;
    }
    const res = await edgeReportOutboxDetail(id);
    setDetail(res.item ?? row);
    setDetailOpen(true);
  };

  const formatDate = (raw: unknown) => {
    if (!raw) return '—';
    const s = String(raw);
    try {
      return format.dateTime(new Date(s.replace(/-/g, '/').slice(0, 16)), {
        dateStyle: 'short',
        timeStyle: 'short',
      });
    } catch {
      return s.slice(0, 16);
    }
  };

  const tabTitle =
    tab === 'outbox' ? t('outbox') : tab === 'inbox' ? t('inbox') : t('localMessages');

  return (
    <CrmPageLayout title={tNav('nav.erp.admin.mpReports')} {...layoutProps}>
      <ModirPayamakBreadcrumb current={tNav('nav.erp.admin.mpReports')} />
      {configLoading ? null : !configured ? <ModirPayamakNotConfigured /> : null}

      {configured ? (
        <Tabs
          value={tab}
          onValueChange={(v) => {
            setTab(v as 'outbox' | 'inbox' | 'local');
            setPage(1);
          }}
        >
          <TabsList>
            <TabsTrigger value="outbox">{t('outbox')}</TabsTrigger>
            <TabsTrigger value="inbox">{t('inbox')}</TabsTrigger>
            <TabsTrigger value="local">{t('localMessages')}</TabsTrigger>
          </TabsList>

          <TabsContent value={tab} className="mt-4 space-y-4">
            <PmFilterBar
              applyLabel={tCommon('apply')}
              onApply={() => {
                applyDomain();
                void load();
              }}
            >
              <div className="space-y-1">
                <Label>{t('domain')}</Label>
                <Input
                  dir="ltr"
                  value={domainInput}
                  onChange={(e) => setDomainInput(e.target.value)}
                  className="w-[200px]"
                  placeholder="example.com"
                />
              </div>
              {tab !== 'local' ? (
                <>
                  <div className="space-y-1">
                    <Label>{t('colRecipient')}</Label>
                    <Input
                      dir="ltr"
                      value={recipient}
                      onChange={(e) => setRecipient(e.target.value)}
                      className="w-[180px]"
                    />
                  </div>
                  <div className="space-y-1">
                    <Label>{t('status')}</Label>
                    <Input
                      value={statusFilter}
                      onChange={(e) => setStatusFilter(e.target.value)}
                      className="w-[140px]"
                      placeholder="state_id"
                    />
                  </div>
                  {tab === 'outbox' ? (
                    <>
                      <div className="space-y-1">
                        <Label>{t('dateFrom')}</Label>
                        <Input
                          type="date"
                          value={dateFrom}
                          onChange={(e) => setDateFrom(e.target.value)}
                          className="w-[160px]"
                        />
                      </div>
                      <div className="space-y-1">
                        <Label>{t('dateTo')}</Label>
                        <Input
                          type="date"
                          value={dateTo}
                          onChange={(e) => setDateTo(e.target.value)}
                          className="w-[160px]"
                        />
                      </div>
                    </>
                  ) : null}
                </>
              ) : null}
            </PmFilterBar>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between gap-4">
                <CardTitle>
                  {tabTitle}
                  {domainFilter ? (
                    <span className="ms-2 font-mono text-sm font-normal text-muted-foreground" dir="ltr">
                      ({domainFilter})
                    </span>
                  ) : null}
                </CardTitle>
                <div className="flex gap-2">
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={page <= 1}
                    onClick={() => setPage((p) => p - 1)}
                  >
                    {tCommon('prev')}
                  </Button>
                  <Button type="button" variant="outline" size="sm" onClick={() => setPage((p) => p + 1)}>
                    {tCommon('next')}
                  </Button>
                </div>
              </CardHeader>
              <CardContent className="p-0">
                {loading ? (
                  <div className="h-40 animate-pulse bg-muted/30" />
                ) : tab === 'local' ? (
                  localMessages.length === 0 ? (
                    <div className="p-6">
                      <PmEmptyState title={t('reportsEmpty')} />
                    </div>
                  ) : (
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>{t('domain')}</TableHead>
                          <TableHead>{t('status')}</TableHead>
                          <TableHead>{t('cost')}</TableHead>
                          <TableHead>{t('date')}</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {localMessages.map((m, i) => (
                          <TableRow key={String(m.id ?? i)}>
                            <TableCell className="font-mono text-xs" dir="ltr">
                              {String(m.domain ?? '—')}
                            </TableCell>
                            <TableCell>{String(m.status ?? m.sending_type ?? '—')}</TableCell>
                            <TableCell dir="ltr">{String(m.cost ?? '—')}</TableCell>
                            <TableCell className="text-xs text-muted-foreground">
                              {formatDate(m.created_at)}
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  )
                ) : items.length === 0 ? (
                  <div className="p-6">
                    <PmEmptyState title={t('reportsEmpty')} />
                  </div>
                ) : (
                  <ModirPayamakOutboxTable items={items} onRowClick={(row) => void openDetail(row)} />
                )}
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      ) : null}

      <Sheet open={detailOpen} onOpenChange={setDetailOpen}>
        <SheetContent className="overflow-y-auto sm:max-w-lg">
          <SheetHeader>
            <SheetTitle>{t('reportDetail')}</SheetTitle>
          </SheetHeader>
          {detail ? (
            <dl className="mt-4 space-y-2 text-sm">
              <div>
                <dt className="text-muted-foreground">{t('colRecipient')}</dt>
                <dd dir="ltr">{edgeField(detail, 'recipient', 'to', 'mobile')}</dd>
              </div>
              <div>
                <dt className="text-muted-foreground">{t('status')}</dt>
                <dd>{edgeField(detail, 'status', 'state')}</dd>
              </div>
              <div>
                <dt className="text-muted-foreground">{t('message')}</dt>
                <dd className="whitespace-pre-wrap">{edgeField(detail, 'message', 'text', 'body')}</dd>
              </div>
            </dl>
          ) : null}
        </SheetContent>
      </Sheet>
    </CrmPageLayout>
  );
}

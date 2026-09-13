'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { Copy, Link2, Unlink } from 'lucide-react';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { PmEmptyState } from '@/features/shared/pm';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  attachModirPayamakNumber,
  detachModirPayamakNumber,
  getModirPayamakCustomers,
  type ModirPayamakDomainNumber,
} from '@/lib/api/modirpayamak';
import { edgeField, edgeListNumbers } from '@/lib/api/modirpayamak-edge';
import { getAxiosMessage } from '@/lib/api-helpers';
import { dashboardHref } from '@/lib/route-resolver';
import { ModirPayamakStatusBadge } from './components/status-badge';
import { ModirPayamakBreadcrumb, ModirPayamakNotConfigured } from './components/shared';
import { useModirPayamakConfigured } from './hooks/useModirPayamakConfigured';
import { useModirPayamakEdge } from './hooks/useModirPayamakEdge';

type AttachmentMap = Record<string, ModirPayamakDomainNumber[]>;

function roleLabel(t: (key: string) => string, role: string): string {
  if (role === 'service') return t('roleService');
  if (role === 'personal' || role === 'marketing') return t('rolePersonal');
  return role;
}

export function ModirpayamakNumbersPage() {
  const t = useTranslations('modirpayamak');
  const tNav = useTranslations();
  const tCommon = useTranslations('common');
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const { layoutProps, setSuccess, setError } = useCrmFeedback();
  const { configured, loading: configLoading } = useModirPayamakConfigured();
  const loader = useCallback(() => edgeListNumbers({ page: 1, per_page: 100 }), []);
  const { items, loading, error: edgeError } = useModirPayamakEdge(loader, {
    enabled: Boolean(configured),
  });
  const [copied, setCopied] = useState<string | null>(null);
  const [attachOpen, setAttachOpen] = useState(false);
  const [detachOpen, setDetachOpen] = useState(false);
  const [attachNumber, setAttachNumber] = useState('');
  const [attachDomain, setAttachDomain] = useState('');
  const [attachRole, setAttachRole] = useState<'service' | 'personal'>('service');
  const [detachDomain, setDetachDomain] = useState('');
  const [detachRole, setDetachRole] = useState<'service' | 'personal'>('service');
  const [detachNumber, setDetachNumber] = useState('');
  const [domains, setDomains] = useState<string[]>([]);
  const [attachmentsByNumber, setAttachmentsByNumber] = useState<AttachmentMap>({});
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (edgeError) setError(edgeError);
  }, [edgeError, setError]);

  const refreshAttachments = useCallback(async () => {
    try {
      const res = await getModirPayamakCustomers();
      const map: AttachmentMap = {};
      for (const account of res.accounts) {
        const domain = account.domain;
        if (!domain) continue;
        for (const n of account.numbers ?? []) {
          const num = (n.number ?? '').trim();
          if (!num) continue;
          const row: ModirPayamakDomainNumber = { ...n, domain };
          if (!map[num]) map[num] = [];
          map[num].push(row);
        }
      }
      setAttachmentsByNumber(map);
      setDomains(res.accounts.map((a) => a.domain).filter(Boolean));
    } catch {
      setAttachmentsByNumber({});
    }
  }, []);

  useEffect(() => {
    if (configured) void refreshAttachments();
  }, [configured, refreshAttachments, items]);

  const copyNumber = async (num: string) => {
    try {
      await navigator.clipboard.writeText(num);
      setCopied(num);
      setSuccess(t('copied'));
      setTimeout(() => setCopied(null), 2000);
    } catch {
      /* ignore */
    }
  };

  const openAttach = async (num: string) => {
    setAttachNumber(num);
    setAttachRole('service');
    setAttachDomain('');
    setAttachOpen(true);
    try {
      const res = await getModirPayamakCustomers();
      setDomains(res.accounts.map((a) => a.domain).filter(Boolean));
    } catch {
      /* ignore */
    }
  };

  const openDetach = async (num?: string) => {
    setDetachDomain('');
    setDetachRole('service');
    setDetachNumber(num ?? '');
    setDetachOpen(true);
    try {
      const res = await getModirPayamakCustomers();
      setDomains(res.accounts.map((a) => a.domain).filter(Boolean));
    } catch {
      /* ignore */
    }
  };

  const submitDetach = async () => {
    if (!detachDomain.trim()) {
      setError(t('domainRequired'));
      return;
    }
    setSaving(true);
    setError(null);
    try {
      const res = await detachModirPayamakNumber(
        detachDomain.trim(),
        detachRole,
        detachNumber.trim() || undefined,
      );
      setSuccess(res.message ?? t('numberDetached'));
      setDetachOpen(false);
      void refreshAttachments();
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setSaving(false);
    }
  };

  const submitAttach = async () => {
    if (!attachDomain.trim() || !attachNumber.trim()) {
      setError(t('domainRequired'));
      return;
    }
    setSaving(true);
    setError(null);
    try {
      const res = await attachModirPayamakNumber({
        domain: attachDomain.trim(),
        number: attachNumber.trim(),
        role: attachRole,
      });
      setSuccess(res.message ?? t('numberAttached'));
      setAttachOpen(false);
      void refreshAttachments();
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setSaving(false);
    }
  };

  return (
    <CrmPageLayout
      title={tNav('nav.erp.admin.mpNumbers')}
      {...layoutProps}
      actions={
        <div className="flex flex-wrap gap-2">
          <Button variant="outline" size="sm" type="button" onClick={() => void openDetach()} disabled={!configured}>
            <Unlink className="me-1 h-4 w-4" />
            {t('detachLine')}
          </Button>
          <Button variant="outline" size="sm" asChild>
            <Link href={dashboardHref(locale, 'admin/integrations/modirpayamak/customers')}>
              {tNav('nav.erp.admin.mpCustomers')}
            </Link>
          </Button>
        </div>
      }
    >
      <ModirPayamakBreadcrumb current={tNav('nav.erp.admin.mpNumbers')} />
      {configLoading ? null : !configured ? <ModirPayamakNotConfigured /> : null}
      {configured ? <p className="mb-2 text-sm text-muted-foreground">{t('numbersPoolHint')}</p> : null}

      {!configured ? null : loading ? (
        <Card>
          <CardContent className="h-40 animate-pulse bg-muted/30" />
        </Card>
      ) : items.length === 0 ? (
        <PmEmptyState title={t('numbersEmpty')} />
      ) : (
        <Card>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t('fromNumber')}</TableHead>
                  <TableHead>{t('lineType')}</TableHead>
                  <TableHead>{t('status')}</TableHead>
                  <TableHead>{t('attachedDomains')}</TableHead>
                  <TableHead className="w-[120px]">{tCommon('actions')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {items.map((row, i) => {
                  const num = edgeField(row, 'number', 'line', 'from_number', 'sender');
                  const attached = attachmentsByNumber[num] ?? [];
                  return (
                    <TableRow key={`${num}-${i}`}>
                      <TableCell dir="ltr" className="font-mono">
                        {num}
                      </TableCell>
                      <TableCell>{edgeField(row, 'type', 'line_type', 'title', 'name')}</TableCell>
                      <TableCell>
                        <ModirPayamakStatusBadge status={edgeField(row, 'status', 'state')} />
                      </TableCell>
                      <TableCell>
                        {attached.length === 0 ? (
                          <span className="text-xs text-muted-foreground">—</span>
                        ) : (
                          <div className="flex max-w-md flex-wrap gap-1">
                            {attached.map((a) => (
                              <Badge
                                key={`${a.domain}-${a.role}-${a.number}`}
                                variant="secondary"
                                className="font-normal"
                              >
                                <span dir="ltr">{a.domain}</span>
                                <span className="ms-1 text-muted-foreground">
                                  ({roleLabel(t, String(a.role ?? ''))})
                                </span>
                              </Badge>
                            ))}
                          </div>
                        )}
                      </TableCell>
                      <TableCell>
                        <div className="flex gap-1">
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => void copyNumber(num)}
                            title={t('copyLine')}
                          >
                            <Copy className={`h-4 w-4 ${copied === num ? 'text-green-600' : ''}`} />
                          </Button>
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => void openAttach(num)}
                            title={t('attachToDomain')}
                          >
                            <Link2 className="h-4 w-4" />
                          </Button>
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => void openDetach(num)}
                            title={t('detachLine')}
                          >
                            <Unlink className="h-4 w-4" />
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  );
                })}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      )}

      <Dialog open={attachOpen} onOpenChange={setAttachOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('attachToDomain')}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <div>
              <Label>{t('fromNumber')}</Label>
              <Input className="mt-1 font-mono" dir="ltr" value={attachNumber} readOnly />
            </div>
            <div>
              <Label>{t('domain')}</Label>
              {domains.length > 0 ? (
                <Select value={attachDomain} onValueChange={setAttachDomain}>
                  <SelectTrigger className="mt-1">
                    <SelectValue placeholder="example.com" />
                  </SelectTrigger>
                  <SelectContent>
                    {domains.map((d) => (
                      <SelectItem key={d} value={d}>
                        {d}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              ) : (
                <Input
                  className="mt-1"
                  placeholder="example.com"
                  value={attachDomain}
                  onChange={(e) => setAttachDomain(e.target.value)}
                />
              )}
            </div>
            <div>
              <Label>{t('lineRole')}</Label>
              <Select value={attachRole} onValueChange={(v) => setAttachRole(v as 'service' | 'personal')}>
                <SelectTrigger className="mt-1">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="service">{t('roleService')}</SelectItem>
                  <SelectItem value="personal">{t('rolePersonal')}</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setAttachOpen(false)}>
              {tCommon('cancel')}
            </Button>
            <Button type="button" disabled={saving} onClick={() => void submitAttach()}>
              {tCommon('save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={detachOpen} onOpenChange={setDetachOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('detachLine')}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <div>
              <Label>{t('domain')}</Label>
              {domains.length > 0 ? (
                <Select value={detachDomain} onValueChange={setDetachDomain}>
                  <SelectTrigger className="mt-1">
                    <SelectValue placeholder="example.com" />
                  </SelectTrigger>
                  <SelectContent>
                    {domains.map((d) => (
                      <SelectItem key={d} value={d}>
                        {d}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              ) : (
                <Input
                  className="mt-1"
                  placeholder="example.com"
                  value={detachDomain}
                  onChange={(e) => setDetachDomain(e.target.value)}
                />
              )}
            </div>
            <div>
              <Label>{t('lineNumber')}</Label>
              <Input
                className="mt-1 font-mono"
                dir="ltr"
                value={detachNumber}
                onChange={(e) => setDetachNumber(e.target.value)}
                placeholder="+98…"
              />
            </div>
            <div>
              <Label>{t('lineRole')}</Label>
              <Select value={detachRole} onValueChange={(v) => setDetachRole(v as 'service' | 'personal')}>
                <SelectTrigger className="mt-1">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="service">{t('roleService')}</SelectItem>
                  <SelectItem value="personal">{t('rolePersonal')}</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setDetachOpen(false)}>
              {tCommon('cancel')}
            </Button>
            <Button type="button" disabled={saving} onClick={() => void submitDetach()}>
              {t('detachLine')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </CrmPageLayout>
  );
}

'use client';

import { Building2, Copy, MoreVertical, Pencil, RefreshCw, Trash2, XCircle } from 'lucide-react';
import { useTranslations } from 'next-intl';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useLocale } from '@/hooks/use-locale-next';
import { cn } from '@/lib/utils';

export type LicenseCardRow = {
  id?: number;
  license_key?: string;
  project_name?: string | null;
  domain?: string | null;
  logo_url?: string | null;
  status?: string;
  start_date?: string | null;
  expires_at?: string | null;
  created_at?: string;
  max_users?: number;
  meta?: Record<string, unknown> | null;
};

function remainingDays(lic: LicenseCardRow): number | null {
  if (!lic.expires_at) return null;
  const exp = new Date(String(lic.expires_at)).getTime();
  return Math.ceil((exp - Date.now()) / 86400000);
}

function licenseProgress(lic: LicenseCardRow): number {
  if (!lic.expires_at) return 100;
  const exp = new Date(String(lic.expires_at)).getTime();
  const start = lic.start_date
    ? new Date(String(lic.start_date)).getTime()
    : lic.created_at
      ? new Date(String(lic.created_at)).getTime()
      : exp - 365 * 86400000;
  if (exp <= start) return 50;
  const t = (exp - Date.now()) / (exp - start);
  return Math.max(0, Math.min(100, Math.round(t * 100)));
}

function cardTone(lic: LicenseCardRow): string {
  const days = remainingDays(lic);
  if (lic.status === 'cancelled' || lic.status === 'revoked' || lic.status === 'inactive') {
    return 'border-foreground/30';
  }
  if (days === null) return 'border-green-500/40';
  if (days < 0) return 'border-destructive/40';
  if (days <= 30) return 'border-yellow-500/40';
  return 'border-green-500/40';
}

function modulesSummary(lic: LicenseCardRow): string {
  const m = lic.meta;
  if (!m || typeof m !== 'object') return '—';
  const mods = (m.modules ?? m.licensed_modules) as unknown;
  if (Array.isArray(mods)) return mods.filter((x) => typeof x === 'string').join(', ');
  return '—';
}

type Props = {
  license: LicenseCardRow;
  onEdit: (license: LicenseCardRow) => void;
  onRenew: (id: number) => void;
  onCancel: (id: number) => void;
  onDelete: (id: number) => void;
  onCopyKey?: (key: string) => void;
};

export function LicenseCard({ license, onEdit, onRenew, onCancel, onDelete, onCopyKey }: Props) {
  const t = useTranslations();
  const tl = useTranslations('licenses');
  const { isRtl, formatDate } = useLocale();
  const days = remainingDays(license);
  const progress = licenseProgress(license);

  const statusLabel = (status: string) => {
    const map: Record<string, string> = {
      active: tl('status') + ': active',
      inactive: tl('status') + ': inactive',
      expired: 'expired',
      cancelled: tl('cancel'),
    };
    return map[status] ?? status;
  };

  const formatExpiry = (d: string | null | undefined) =>
    !d || String(d).startsWith('0000-00-00') ? tl('vipUnlimited') : formatDate(String(d));

  return (
    <Card className={cn('overflow-hidden border-2', cardTone(license))}>
      <CardContent className="pt-6">
        <div className="mb-4 flex flex-col items-center text-center">
          {license.logo_url ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={String(license.logo_url)}
              alt={String(license.project_name ?? '')}
              className="mb-2 h-16 w-16 object-contain"
            />
          ) : (
            <div className="mb-2 flex h-16 w-16 items-center justify-center rounded-lg bg-muted">
              <Building2 className="text-muted-foreground h-8 w-8" />
            </div>
          )}
          <h5 className="font-medium">{String(license.project_name || license.domain || '—')}</h5>
          <p className="text-muted-foreground text-sm" dir="ltr">
            {String(license.domain ?? '')}
          </p>
        </div>

        {license.license_key ? (
          <div className="mb-3 flex items-center justify-center gap-1">
            <p className="text-muted-foreground truncate font-mono text-xs" dir="ltr">
              {String(license.license_key)}
            </p>
            {onCopyKey ? (
              <Button
                type="button"
                size="icon"
                variant="ghost"
                className="size-7"
                onClick={() => onCopyKey(String(license.license_key))}
              >
                <Copy className="size-3.5" />
              </Button>
            ) : null}
          </div>
        ) : null}

        <div className="mb-4 space-y-2">
          <div className="flex justify-between text-sm">
            <span className="text-muted-foreground">{tl('expires')}</span>
            <span className="font-medium">{progress.toFixed(0)}%</span>
          </div>
          <Progress value={progress} className="h-1.5" />
        </div>

        <div className="mb-2 flex justify-between text-sm">
          <span className="text-muted-foreground">{tl('expiryDate')}</span>
          <span className="font-medium">{formatExpiry(license.expires_at)}</span>
        </div>
        <div className="mb-2 flex justify-between text-sm">
          <span className="text-muted-foreground">{tl('remainingDays', { days: days ?? 0 })}</span>
          {days === null ? (
            <Badge variant="secondary">{tl('vipUnlimited')}</Badge>
          ) : (
            <Badge variant="outline">{days}</Badge>
          )}
        </div>
        <p className="text-muted-foreground mb-4 line-clamp-2 text-xs">
          {tl('modules')}: {modulesSummary(license)}
        </p>

        <div className="flex items-center justify-between">
          <Badge
            variant={
              license.status === 'active'
                ? 'default'
                : license.status === 'expired'
                  ? 'destructive'
                  : 'secondary'
            }
          >
            {statusLabel(String(license.status ?? ''))}
          </Badge>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" size="icon" className="h-8 w-8">
                <MoreVertical className="h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem onClick={() => onEdit(license)}>
                <Pencil className={cn('h-4 w-4 shrink-0', isRtl ? 'ms-2' : 'me-2')} />
                {tl('editMeta')}
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => license.id && onRenew(license.id)}>
                <RefreshCw className={cn('h-4 w-4 shrink-0', isRtl ? 'ms-2' : 'me-2')} />
                {tl('renew')}
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => license.id && onCancel(license.id)}>
                <XCircle className={cn('h-4 w-4 shrink-0', isRtl ? 'ms-2' : 'me-2')} />
                {tl('cancel')}
              </DropdownMenuItem>
              <DropdownMenuItem
                className="text-destructive"
                onClick={() => license.id && onDelete(license.id)}
              >
                <Trash2 className={cn('h-4 w-4 shrink-0', isRtl ? 'ms-2' : 'me-2')} />
                {t('common.delete')}
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </CardContent>
    </Card>
  );
}
